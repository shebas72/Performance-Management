<?php

namespace App\Saas\Services;

use App\Models\Company;
use App\Models\User;
use App\Saas\Models\Subscription;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * The tenant sign-up life cycle (SaaS only):
 *   register -> verify email -> super admin approves (and picks plan and trial) -> tenant admin can sign in.
 * A company that has not been through approval is "pending" (or "rejected"); every older company is "approved".
 */
class TenantSignup
{
    public const VERIFY_HOURS = 48;

    /** awaiting_email | pending_approval | approved | rejected */
    public static function status(Company $company): string
    {
        $status = $company->approval_status ?: 'approved';

        if ($status === 'pending') {
            return User::where('company_id', $company->id)->whereNotNull('email_verification_token')->exists()
                ? 'awaiting_email' : 'pending_approval';
        }

        return $status;
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'awaiting_email' => 'Awaiting email',
            'pending_approval' => 'Pending approval',
            'rejected' => 'Rejected',
            default => 'Approved',
        };
    }

    /** Message shown on the sign-in page when this person may not sign in yet, or null when they may. */
    public static function loginBlock(User $user): ?string
    {
        if ($user->is_super_admin || ! $user->company_id) {
            return null;
        }

        if ($user->email_verification_token) {
            return __('Please verify your email address first. Check your inbox for the verification link.');
        }

        $company = Company::find($user->company_id);

        if (! $company) {
            return null;
        }

        return match ($company->approval_status ?: 'approved') {
            'pending' => __('Your email is verified. Your organization is waiting for approval; we will email you once it is approved.'),
            'rejected' => $company->rejection_reason
                ? __('Your registration was not approved. Reason: :reason', ['reason' => $company->rejection_reason])
                : __('Your registration was not approved.'),
            default => $company->is_active ? null : __('This organization has been suspended. Please contact support.'),
        };
    }

    public static function sendVerification(User $user): void
    {
        $token = Str::random(48);

        $user->forceFill([
            'email_verification_token' => hash('sha256', $token),
            'email_verification_sent_at' => now(),
        ])->save();

        self::mail($user, 'Verify your email address', [
            ['Hello :name,', ['name' => $user->name]],
            ['Thank you for registering. Please confirm your email address by opening this link (valid for :hours hours):', ['hours' => self::VERIFY_HOURS]],
            url('/api/v1/auth/verify-email/'.$token),
            ['After that, your organization will be reviewed and you will receive another email once it is approved.'],
            ['If you did not register, you can ignore this message.'],
        ]);
    }

    /** @return string ok | expired | invalid */
    public static function verify(string $token): string
    {
        $user = User::where('email_verification_token', hash('sha256', $token))->first();

        if (! $user) {
            return 'invalid';
        }

        if (! $user->email_verification_sent_at || $user->email_verification_sent_at->lt(now()->subHours(self::VERIFY_HOURS))) {
            return 'expired';
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_token' => null,
            'email_verification_sent_at' => null,
        ])->save();

        // Tells the platform owner (super admin panel, English).
        $company = Company::find($user->company_id);
        AdminNotifier::send(
            'Ready for approval: '.($company?->name ?? 'new organization'),
            $user->name.' verified '.$user->email.'. Review and approve the organization.',
            url('/super-admin/companies'),
            'heroicon-o-check-badge',
            'warning'
        );

        return 'ok';
    }

    /** Super admin approves: the organization becomes active and gets a plan, with an optional trial. */
    public static function approve(Company $company, int $planId, int $trialDays): void
    {
        $trialEnds = $trialDays > 0 ? now()->addDays($trialDays) : null;

        $company->forceFill([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'rejection_reason' => null,
            'is_active' => true,
            'trial_ends_at' => $trialEnds,
        ])->save();

        Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $planId,
            'status' => $trialDays > 0 ? 'trialing' : 'active',
            'billing_cycle' => 'monthly',
            'trial_ends_at' => $trialEnds,
        ]);

        $login = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/').'/login';

        foreach (User::where('company_id', $company->id)->where('is_active', true)->get() as $user) {
            self::mail($user, 'Your organization has been approved', [
                ['Hello :name,', ['name' => $user->name]],
                ['Good news: :company has been approved. You can now sign in:', ['company' => $company->name]],
                $login,
            ]);
        }
    }

    public static function reject(Company $company, ?string $reason): void
    {
        $company->forceFill([
            'approval_status' => 'rejected',
            'rejection_reason' => $reason,
            'is_active' => false,
        ])->save();

        foreach (User::where('company_id', $company->id)->get() as $user) {
            $lines = [
                ['Hello :name,', ['name' => $user->name]],
                ['Unfortunately :company could not be approved.', ['company' => $company->name]],
            ];
            if ($reason) {
                $lines[] = ['Reason: :reason', ['reason' => $reason]];
            }

            self::mail($user, 'Your registration was not approved', $lines);
        }
    }

    /**
     * Sends a plain email in the recipient's language. Each line is either a plain string (a link) or
     * [English text with :placeholders, params]. A failed mail never breaks the request; it is logged.
     */
    private static function mail(User $user, string $subject, array $lines): void
    {
        try {
            app()->withLocale($user->preferredLocale(), function () use ($user, $subject, $lines) {
                $body = collect($lines)
                    ->map(fn ($line) => is_array($line) ? __($line[0], $line[1] ?? []) : $line)
                    ->implode("\n\n");

                Mail::raw($body, fn ($message) => $message->to($user->email)->subject(__($subject)));
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }
}