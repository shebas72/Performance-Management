<?php

namespace App\Saas\Services;

use App\Models\Company;
use App\Models\User;
use App\Saas\Models\GatewayPlan;
use App\Saas\Models\Invoice;
use App\Saas\Models\Plan;
use App\Saas\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Saas\Models\PlatformSetting;
use Illuminate\Support\Facades\Crypt;
use App\Services\Notifier;

/**
 * Plan changes and the Stripe and PayPal subscription calls, over plain HTTP (no extra packages).
 * The company's plan is always its latest subscription that is not cancelled or expired (see PlanLimits).
 */
class BillingService
{
    public function currency(): string
    {
        return strtoupper($this->cfg('currency', 'USD'));
    }

    /** Providers that can be offered right now. "manual" (ask the platform admin) is always there. */
        /** Providers that can be offered right now: switched on in Platform > Settings and, for payments, with keys. */
    public function providers(): array
    {
        $list = [];
        if ($this->enabled('stripe') && $this->cfg('stripe.secret')) $list[] = 'stripe';
        if ($this->enabled('paypal') && $this->cfg('paypal.client_id') && $this->cfg('paypal.secret')) $list[] = 'paypal';
        if ($this->enabled('manual')) $list[] = 'manual';

        return $list;
    }

    private function enabled(string $provider): bool
    {
        return PlatformSetting::get($provider.'_enabled', '1') !== '0';
    }

    /** A setting from Platform > Settings (secrets are encrypted there); falls back to config/billing.php (.env). */
    public function cfg(string $key, mixed $default = null): mixed
    {
        $map = [
            'stripe.secret' => ['stripe_secret', true],
            'stripe.webhook_secret' => ['stripe_webhook_secret', true],
            'paypal.client_id' => ['paypal_client_id', false],
            'paypal.secret' => ['paypal_secret', true],
            'paypal.webhook_id' => ['paypal_webhook_id', false],
            'paypal.mode' => ['paypal_mode', false],
            'currency' => ['billing_currency', false],
        ];

        if (isset($map[$key])) {
            [$name, $secret] = $map[$key];
            $value = PlatformSetting::get($name);

            if ($value !== null && $secret) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (\Throwable) {
                    $value = null; // unreadable (for example after an APP_KEY change): fall back to .env
                }
            }

            if ($value !== null && $value !== '') return $value;
        }

        return config('billing.'.$key, $default);
    }

    public function price(Plan $plan, string $cycle): float
    {
        return (float) ($cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly);
    }

    public function current(int $companyId): ?Subscription
    {
        return Subscription::with('plan')->where('company_id', $companyId)
            ->whereNotIn('status', ['cancelled', 'expired'])->latest('id')->first();
    }

    // ------------------------------------------------------------------ plan changes

    /** Make $plan the company's plan. The previous subscription is cancelled, and at its provider too (best effort). */
    public function activate(Company $company, Plan $plan, string $cycle, ?string $provider = null, array $ids = [], ?Carbon $periodEnd = null): Subscription
    {
        $old = collect();

        $sub = DB::transaction(function () use ($company, $plan, $cycle, $provider, $ids, $periodEnd, &$old) {
            $old = Subscription::where('company_id', $company->id)->whereNotIn('status', ['cancelled', 'expired'])->get();

            foreach ($old as $o) {
                $o->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
            }

            $company->forceFill(['trial_ends_at' => null])->save();

            return Subscription::create(array_merge([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $cycle,
                'provider' => $provider,
                'current_period_ends_at' => $periodEnd,
                'cancel_at_period_end' => false,
            ], $ids));
        });

        foreach ($old as $o) {
            $same = ($o->stripe_subscription_id && $o->stripe_subscription_id === ($ids['stripe_subscription_id'] ?? null))
                || ($o->paypal_subscription_id && $o->paypal_subscription_id === ($ids['paypal_subscription_id'] ?? null));

            if (! $same) $this->cancelAtProvider($o, true);
        }

        return $sub;
    }

    /** A paid subscription is over: the company drops to the cheapest active plan so it never ends up unrestricted. */
    public function end(Subscription $sub): void
    {
        if (in_array($sub->status, ['cancelled', 'expired'], true)) return;

        $sub->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();

        if ($this->current($sub->company_id)) return;

        $fallback = Plan::where('is_active', true)->orderBy('price_monthly')->orderBy('sort_order')->first();
        if ($fallback && $fallback->id !== $sub->plan_id) {
            Subscription::create([
                'company_id' => $sub->company_id, 'plan_id' => $fallback->id, 'status' => 'active',
                'billing_cycle' => 'monthly', 'provider' => null,
            ]);
        }
    }

    /** Tenant cancels auto-renew: Stripe stops at period end; PayPal stops now; the plan stays until the paid period ends. */
    public function stopRenewal(Subscription $sub): void
    {
        $this->cancelAtProvider($sub, false);
        $sub->forceFill(['cancel_at_period_end' => true])->save();
    }

    private function cancelAtProvider(Subscription $sub, bool $now): void
    {
        try {
            if ($sub->stripe_subscription_id && $this->cfg('stripe.secret')) {
                $url = "https://api.stripe.com/v1/subscriptions/{$sub->stripe_subscription_id}";

                if ($now) {
                    $this->stripe()->delete($url);
                } else {
                    $this->stripe()->asForm()->post($url, ['cancel_at_period_end' => 'true']);
                }
            }

            if ($sub->paypal_subscription_id && $this->cfg('paypal.client_id')) {
                $this->paypal()->post($this->paypalBase()."/v1/billing/subscriptions/{$sub->paypal_subscription_id}/cancel", ['reason' => 'Cancelled by customer']);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function recordInvoice(int $companyId, string $provider, string $externalId, float $amount, string $currency, ?string $pdf = null): void
    {
        if ($amount <= 0 || Invoice::where('provider', $provider)->where('external_id', $externalId)->exists()) return;

        (new Invoice)->forceFill([
            'company_id' => $companyId, 'provider' => $provider, 'external_id' => $externalId,
            'amount' => $amount, 'currency' => strtoupper($currency), 'status' => 'paid', 'pdf_url' => $pdf, 'paid_at' => now(),
        ])->save();
    }

    // ------------------------------------------------------------------ Stripe

    private function stripe(): PendingRequest
    {
        return Http::withToken($this->cfg('stripe.secret'))->acceptJson();
    }

    /** @return string URL of the hosted Stripe Checkout page */
    public function stripeCheckout(Company $company, User $user, Plan $plan, string $cycle, string $successUrl, string $cancelUrl): string
    {
        $priceId = $cycle === 'yearly' ? $plan->stripe_price_id_yearly : $plan->stripe_price_id_monthly;

        $item = $priceId
            ? ['price' => $priceId, 'quantity' => 1]
            : ['quantity' => 1, 'price_data' => [
                'currency' => strtolower($this->currency()),
                'unit_amount' => (int) round($this->price($plan, $cycle) * 100),
                'recurring' => ['interval' => $cycle === 'yearly' ? 'year' : 'month'],
                'product_data' => ['name' => $plan->name.' plan'],
            ]];

        $meta = ['company_id' => $company->id, 'plan_id' => $plan->id, 'cycle' => $cycle];
        $customer = Subscription::where('company_id', $company->id)->whereNotNull('stripe_customer_id')->latest('id')->value('stripe_customer_id');

        $payload = [
            'mode' => 'subscription',
            'line_items' => [$item],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $company->id,
            'metadata' => $meta,
            'subscription_data' => ['metadata' => $meta],
        ];

        if ($customer) {
            $payload['customer'] = $customer;
        } else {
            $payload['customer_email'] = $user->email;
        }

        return $this->stripe()->asForm()->post('https://api.stripe.com/v1/checkout/sessions', $payload)->throw()->json('url');
    }

    /** Stripe signs every webhook; reject anything that does not verify (also old replays). */
    public function stripeVerify(string $payload, ?string $header): bool
    {
        $secret = $this->cfg('stripe.webhook_secret');
        if (! $secret || ! $header) return false;

        $time = null;
        $sigs = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($k === 't') $time = $v;
            if ($k === 'v1') $sigs[] = $v;
        }

        if (! $time || abs(time() - (int) $time) > 300) return false;

        $expected = hash_hmac('sha256', $time.'.'.$payload, $secret);

        foreach ($sigs as $sig) {
            if (hash_equals($expected, (string) $sig)) return true;
        }

        return false;
    }

    public function stripeEvent(array $event): void
    {
        $obj = $event['data']['object'] ?? [];

        switch ($event['type'] ?? '') {
            case 'checkout.session.completed':
                if (($obj['mode'] ?? '') !== 'subscription') return;
                $company = Company::find($obj['metadata']['company_id'] ?? 0);
                $plan = Plan::find($obj['metadata']['plan_id'] ?? 0);
                $subId = $obj['subscription'] ?? null;
                if (! $company || ! $plan || ! $subId) return;
                if (Subscription::where('stripe_subscription_id', $subId)->exists()) return; // already handled (retries)

                $remote = $this->stripe()->get("https://api.stripe.com/v1/subscriptions/{$subId}")->json();
                $end = $remote['items']['data'][0]['current_period_end'] ?? $remote['current_period_end'] ?? null;

                $this->activate($company, $plan, $obj['metadata']['cycle'] ?? 'monthly', 'stripe', [
                    'stripe_subscription_id' => $subId,
                    'stripe_customer_id' => $obj['customer'] ?? null,
                ], $end ? Carbon::createFromTimestamp($end) : null);
                break;

            case 'invoice.paid':
                $subId = $obj['subscription'] ?? ($obj['parent']['subscription_details']['subscription'] ?? null);
                $sub = $subId ? Subscription::where('stripe_subscription_id', $subId)->first() : null;
                if (! $sub) return;
                $end = $obj['lines']['data'][0]['period']['end'] ?? null;
                $sub->forceFill(array_filter([
                    'status' => in_array($sub->status, ['cancelled', 'expired'], true) ? $sub->status : 'active',
                    'current_period_ends_at' => $end ? Carbon::createFromTimestamp($end) : null,
                ], fn ($v) => $v !== null))->save();
                $this->recordInvoice($sub->company_id, 'stripe', $obj['id'], ($obj['amount_paid'] ?? 0) / 100, $obj['currency'] ?? $this->currency(), $obj['invoice_pdf'] ?? null);
                break;

           

            case 'invoice.payment_failed':
                $subId = $obj['subscription'] ?? ($obj['parent']['subscription_details']['subscription'] ?? null);
                Subscription::where('stripe_subscription_id', $subId)->where('status', 'active')->update(['status' => 'past_due']);
                $this->notifyPaymentFailed(Subscription::where('stripe_subscription_id', $subId)->first());
                break;

            case 'customer.subscription.updated':
                Subscription::where('stripe_subscription_id', $obj['id'] ?? '')
                    ->update(['cancel_at_period_end' => (bool) ($obj['cancel_at_period_end'] ?? false)]);
                break;

            case 'customer.subscription.deleted':
                $sub = Subscription::where('stripe_subscription_id', $obj['id'] ?? '')->first();
                if ($sub) $this->end($sub);
                break;
        }
    }

    // ------------------------------------------------------------------ PayPal

    private function paypalBase(): string
    {
        return $this->cfg('paypal.mode') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private function paypal(): PendingRequest
    {
        $token = Cache::remember('paypal_access_token', 480, fn () => Http::asForm()
            ->withBasicAuth($this->cfg('paypal.client_id'), $this->cfg('paypal.secret'))
            ->post($this->paypalBase().'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw()->json('access_token'));

        return Http::withToken($token)->acceptJson()->asJson()->withHeaders(['Prefer' => 'return=representation']);
    }

    /** PayPal's billing plan for our plan and cycle; created the first time, and again if the price changed. */
    private function paypalPlanId(Plan $plan, string $cycle): string
    {
        $amount = $this->price($plan, $cycle);
        $row = GatewayPlan::where(['plan_id' => $plan->id, 'provider' => 'paypal', 'billing_cycle' => $cycle])->first();

        if ($row && (float) $row->amount === $amount && $row->currency === $this->currency()) return $row->external_id;

        $productId = $row->product_id ?? $this->paypal()->post($this->paypalBase().'/v1/catalogs/products', [
            'name' => $plan->name.' plan', 'type' => 'SERVICE', 'category' => 'SOFTWARE',
        ])->throw()->json('id');

        $created = $this->paypal()->post($this->paypalBase().'/v1/billing/plans', [
            'product_id' => $productId,
            'name' => $plan->name.' ('.$cycle.')',
            'status' => 'ACTIVE',
            'billing_cycles' => [[
                'frequency' => ['interval_unit' => $cycle === 'yearly' ? 'YEAR' : 'MONTH', 'interval_count' => 1],
                'tenure_type' => 'REGULAR', 'sequence' => 1, 'total_cycles' => 0,
                'pricing_scheme' => ['fixed_price' => ['value' => number_format($amount, 2, '.', ''), 'currency_code' => $this->currency()]],
            ]],
            'payment_preferences' => ['auto_bill_outstanding' => true, 'payment_failure_threshold' => 3],
        ])->throw()->json('id');

        GatewayPlan::updateOrCreate(
            ['plan_id' => $plan->id, 'provider' => 'paypal', 'billing_cycle' => $cycle],
            ['amount' => $amount, 'currency' => $this->currency(), 'product_id' => $productId, 'external_id' => $created]
        );

        return $created;
    }

    /** @return string URL where the customer approves the PayPal subscription */
    public function paypalSubscribe(Company $company, User $user, Plan $plan, string $cycle, string $returnUrl, string $cancelUrl): string
    {
        $response = $this->paypal()->post($this->paypalBase().'/v1/billing/subscriptions', [
            'plan_id' => $this->paypalPlanId($plan, $cycle),
            'custom_id' => $company->id.'|'.$plan->id.'|'.$cycle,
            'subscriber' => ['email_address' => $user->email],
            'application_context' => [
                'brand_name' => config('app.name'), 'user_action' => 'SUBSCRIBE_NOW',
                'return_url' => $returnUrl, 'cancel_url' => $cancelUrl,
            ],
        ])->throw()->json();

        $link = collect($response['links'] ?? [])->firstWhere('rel', 'approve');
        abort_unless($link, 502, 'PayPal did not return an approval link.');

        return $link['href'];
    }

    /** Called when the customer returns from PayPal. @return string active | pending */
    public function paypalConfirm(Company $company, string $subscriptionId): string
    {
        if (Subscription::where('paypal_subscription_id', $subscriptionId)->where('company_id', $company->id)->exists()) return 'active';

        $remote = $this->paypal()->get($this->paypalBase()."/v1/billing/subscriptions/{$subscriptionId}")->throw()->json();

        return $this->paypalActivate($remote, $company) ? 'active' : 'pending';
    }

    private function paypalActivate(array $remote, ?Company $expected = null): bool
    {
        if (($remote['status'] ?? '') !== 'ACTIVE') return false;

        [$companyId, $planId, $cycle] = array_pad(explode('|', (string) ($remote['custom_id'] ?? '')), 3, null);
        $company = Company::find($companyId);
        $plan = Plan::find($planId);

        if (! $company || ! $plan || ($expected && $company->id !== $expected->id)) return false;
        if (Subscription::where('paypal_subscription_id', $remote['id'])->exists()) return true;

        $next = $remote['billing_info']['next_billing_time'] ?? null;
        $this->activate($company, $plan, $cycle ?: 'monthly', 'paypal', ['paypal_subscription_id' => $remote['id']], $next ? Carbon::parse($next) : null);

        return true;
    }

    public function paypalVerify(array $headers, string $payload): bool
    {
        $webhookId = $this->cfg('paypal.webhook_id');
        if (! $webhookId) return false;

        $get = fn (string $name) => $headers[strtolower($name)][0] ?? null;

        $result = $this->paypal()->post($this->paypalBase().'/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $get('paypal-auth-algo'),
            'cert_url' => $get('paypal-cert-url'),
            'transmission_id' => $get('paypal-transmission-id'),
            'transmission_sig' => $get('paypal-transmission-sig'),
            'transmission_time' => $get('paypal-transmission-time'),
            'webhook_id' => $webhookId,
            'webhook_event' => json_decode($payload, true),
        ])->json('verification_status');

        return $result === 'SUCCESS';
    }

    public function paypalEvent(array $event): void
    {
        $res = $event['resource'] ?? [];

        switch ($event['event_type'] ?? '') {
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                $this->paypalActivate($res);
                break;

            case 'PAYMENT.SALE.COMPLETED':
                $subId = $res['billing_agreement_id'] ?? null;
                $sub = $subId ? Subscription::where('paypal_subscription_id', $subId)->first() : null;
                if (! $sub) return;
                $remote = $this->paypal()->get($this->paypalBase()."/v1/billing/subscriptions/{$subId}")->json();
                $next = $remote['billing_info']['next_billing_time'] ?? null;
                $sub->forceFill(array_filter([
                    'status' => in_array($sub->status, ['cancelled', 'expired'], true) ? $sub->status : 'active',
                    'current_period_ends_at' => $next ? Carbon::parse($next) : null,
                ], fn ($v) => $v !== null))->save();
                $this->recordInvoice($sub->company_id, 'paypal', $res['id'], (float) ($res['amount']['total'] ?? 0), $res['amount']['currency'] ?? $this->currency());
                break;

            case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
            case 'BILLING.SUBSCRIPTION.SUSPENDED':
                
                 Subscription::where('paypal_subscription_id', $res['id'] ?? '')->where('status', 'active')->update(['status' => 'past_due']);
                $this->notifyPaymentFailed(Subscription::where('paypal_subscription_id', $res['id'] ?? '')->first());
                break;

            case 'BILLING.SUBSCRIPTION.CANCELLED':
                Subscription::where('paypal_subscription_id', $res['id'] ?? '')->update(['cancel_at_period_end' => true]);
                break;

            case 'BILLING.SUBSCRIPTION.EXPIRED':
                $sub = Subscription::where('paypal_subscription_id', $res['id'] ?? '')->first();
                if ($sub) $this->end($sub);
                break;
        }
    }

        private function notifyPaymentFailed(?Subscription $sub): void
    {
        if (! $sub) return;

        Notifier::to(
            Notifier::companyAdmins($sub->company_id), 'plan',
            'Payment failed',
            'We could not charge your last payment. Update your payment method to keep your plan.',
            '/billing', 'danger', 'payment_failed:'.$sub->id.':'.now()->format('Y-m-d')
        );
    }
}