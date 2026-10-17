<?php

namespace App\Saas\Console;

use App\Saas\Models\Subscription;
use App\Saas\Services\BillingService;
use Illuminate\Console\Command;

/** Ends subscriptions whose renewal was cancelled once the paid period is over. Run hourly by the scheduler. */
class ExpireSubscriptions extends Command
{
    protected $signature = 'billing:expire';
    protected $description = 'End cancelled subscriptions whose paid period has finished';

    public function handle(BillingService $billing): int
    {
        $due = Subscription::whereNotIn('status', ['cancelled', 'expired'])
            ->where('cancel_at_period_end', true)
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', now())
            ->get();

        foreach ($due as $sub) {
            $billing->end($sub);
        }

        $this->info("Ended {$due->count()} subscription(s).");

        return self::SUCCESS;
    }
}