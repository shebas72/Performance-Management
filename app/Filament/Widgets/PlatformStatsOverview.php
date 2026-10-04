<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $stats = [
            Stat::make('Organizations', Company::count()),
            Stat::make('Users', User::where('is_super_admin', false)->count()),
        ];

        if (config('spms.mode') === 'saas') {
            // ASSUMES: subscriptions.status, subscriptions.plan_id, plans.price_monthly
            $active = Subscription::where('status', 'active');

            $mrr = (clone $active)
                ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
                ->sum('plans.price_monthly');

            $stats[] = Stat::make('Active subscriptions', $active->count());
            $stats[] = Stat::make('MRR', '$' . number_format($mrr, 2));
        }

        return $stats;
    }
}
