<?php

namespace App\Saas\Console;

use App\Saas\Models\Subscription;
use App\Services\Notifier;
use Illuminate\Console\Command;

/** Tells a company's admins when the trial ends within 3 days. Once per subscription. */
class TrialEndingReminders extends Command
{
    protected $signature = 'billing:trial-reminders';
    protected $description = 'Notify admins whose trial ends soon';

    public function handle(): int
    {
        $due = Subscription::where('status', 'trialing')->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(3)])->get();

        foreach ($due as $sub) {
            $days = max(0, (int) ceil(now()->diffInHours($sub->trial_ends_at, false) / 24));
                       Notifier::to(
                Notifier::companyAdmins($sub->company_id), 'plan',
                'Your trial ends soon',
                $days <= 0
                    ? 'Your trial ends today. Choose a plan to keep your data and limits.'
                    : 'Your trial ends in :days day(s). Choose a plan to keep your data and limits.',
                '/billing', 'warning', 'trial_end:'.$sub->id,
                ['days' => $days]
            );
        }

        $this->info("Notified for {$due->count()} trial(s).");

        return self::SUCCESS;
    }
}