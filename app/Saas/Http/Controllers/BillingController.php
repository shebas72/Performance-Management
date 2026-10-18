<?php

namespace App\Saas\Http\Controllers;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Saas\Models\Plan;
use App\Saas\Models\UpgradeRequest;
use App\Saas\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Saas\Services\AdminNotifier;

/** The tenant admin's upgrade page: see plans, pay with Stripe or PayPal, or ask the platform admin to change the plan. */
class BillingController extends Controller
{
    use ResolvesTenant;

    public function __construct(private BillingService $billing) {}

    private function company(Request $request): Company
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403, 'You do not have permission to change the plan.');

        return Company::findOrFail($this->companyId($request));
    }

    private function frontend(string $path): string
    {
        return rtrim(config('app.frontend_url', 'http://localhost:5173'), '/').$path;
    }

    public function show(Request $request)
    {
        $company = $this->company($request);
        $sub = $this->billing->current($company->id);

        return response()->json(['data' => [
            'currency' => $this->billing->currency(),
            'providers' => $this->billing->providers(),
            'subscription' => $sub ? [
                'plan_id' => $sub->plan_id,
                'plan' => $sub->plan?->name,
                'status' => $sub->status,
                'billing_cycle' => $sub->billing_cycle,
                'provider' => $sub->provider,
                'trial_ends_at' => $sub->trial_ends_at,
                'current_period_ends_at' => $sub->current_period_ends_at,
                'cancel_at_period_end' => (bool) $sub->cancel_at_period_end,
            ] : null,
            'pending_request' => UpgradeRequest::where('company_id', $company->id)->where('status', 'pending')->latest('id')->first(),
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->orderBy('price_monthly')->get()->map(fn (Plan $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'name_ar' => $p->name_ar,
                'price_monthly' => (float) $p->price_monthly,
                'price_yearly' => (float) $p->price_yearly,
                'kpi_limit' => (int) $p->kpi_limit,
                'user_limit' => (int) $p->user_limit,
                'department_limit' => (int) $p->department_limit,
                'has_pdf_export' => (bool) $p->has_pdf_export,
                'has_arabic' => (bool) $p->has_arabic,
                'has_projects' => (bool) $p->has_projects,
                'custom_roles' => (bool) ($p->custom_roles ?? false),
                'is_current' => $sub?->plan_id === $p->id,
            ])->values(),
        ]]);
    }

    public function checkout(Request $request)
    {
        $company = $this->company($request);

        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_active', true)],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'provider' => ['required', Rule::in(['stripe', 'paypal'])],
        ]);

        abort_unless(in_array($data['provider'], $this->billing->providers(), true), 422, 'This payment method is not available.');

        $plan = Plan::findOrFail($data['plan_id']);
        abort_if($this->billing->price($plan, $data['billing_cycle']) <= 0, 422, 'This plan is free. Switch to it directly.');

        $url = $data['provider'] === 'stripe'
            ? $this->billing->stripeCheckout($company, $request->user(), $plan, $data['billing_cycle'], $this->frontend('/billing?checkout=success'), $this->frontend('/billing?checkout=cancel'))
            : $this->billing->paypalSubscribe($company, $request->user(), $plan, $data['billing_cycle'], $this->frontend('/billing?paypal=return'), $this->frontend('/billing?paypal=cancel'));

        return response()->json(['data' => ['url' => $url]]);
    }

    /** Moving to a free plan needs no payment. */
    public function switchFree(Request $request)
    {
        $company = $this->company($request);
        $data = $request->validate(['plan_id' => ['required', Rule::exists('plans', 'id')->where('is_active', true)]]);
        $plan = Plan::findOrFail($data['plan_id']);

        abort_if($this->billing->price($plan, 'monthly') > 0, 422, 'This plan is not free.');

        $this->billing->activate($company, $plan, 'monthly');

            AdminNotifier::send(
            'Plan changed: '.$company->name,
            'Switched to the free plan "'.$plan->name.'" by the tenant.',
            url('/super-admin/companies'),
            'heroicon-o-arrow-path',
            'gray'
        );

        return response()->json(['message' => 'Plan changed.']);
    }

    public function paypalConfirm(Request $request)
    {
        $company = $this->company($request);
        $data = $request->validate(['subscription_id' => ['required', 'string', 'max:100']]);

        return response()->json(['data' => ['status' => $this->billing->paypalConfirm($company, $data['subscription_id'])]]);
    }

    /** "Direct upgrade by the admin": the request goes to the platform admin, who approves it in Filament. */
    public function requestUpgrade(Request $request)
    {
        $company = $this->company($request);
        abort_unless(in_array('manual', $this->billing->providers(), true), 422, 'Upgrade requests are turned off.');

        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_active', true)],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $row = UpgradeRequest::updateOrCreate(
            ['company_id' => $company->id, 'status' => 'pending'],
            $data + ['user_id' => $request->user()->id]
        );

                $plan = Plan::find($data['plan_id']);
        $current = $this->billing->current($company->id);
        $before = $current?->plan ? $this->billing->price($current->plan, $data['billing_cycle']) : 0.0;
        $after = $this->billing->price($plan, $data['billing_cycle']);
        $kind = $after > $before ? 'Upgrade' : ($after < $before ? 'Downgrade' : 'Plan change');

        AdminNotifier::send(
            "{$kind} request: {$company->name}",
            ($current?->plan?->name ?? 'No plan').' to '.$plan->name.' ('.$data['billing_cycle'].')'
                .(filled($data['note'] ?? null) ? '. Note: '.$data['note'] : ''),
            url('/super-admin/upgrade-requests'),
            'heroicon-o-arrow-trending-up',
            'warning'
        );

        return response()->json(['data' => $row], 201);
    }

    public function cancel(Request $request)
    {
        $company = $this->company($request);
        $sub = $this->billing->current($company->id);

        abort_unless($sub && in_array($sub->provider, ['stripe', 'paypal'], true), 422, 'There is no automatic renewal to cancel. Contact the platform admin.');

        if (! $sub->cancel_at_period_end) $this->billing->stopRenewal($sub);

        return response()->json(['message' => 'Renewal cancelled. Your plan stays active until the end of the paid period.']);
    }
}