<?php

namespace App\Saas\Filament\Widgets;

use App\Models\Company;
use App\Models\User;
use App\Saas\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Safe before the phase25 migration has run.
        try {
            $pending = Company::where('approval_status', 'pending')->count();
        } catch (\Throwable) {
            $pending = 0;
        }

        $active = Subscription::where('status', 'active');

        $mrr = (clone $active)
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->sum('plans.price_monthly');

        return [
            Stat::make('Organizations', Company::count()),
            Stat::make('Users', User::where('is_super_admin', false)->count()),
            Stat::make('Pending approval', $pending)
                ->description('New sign-ups to review')
                ->color($pending > 0 ? 'warning' : 'gray'),
            Stat::make('Active subscriptions', $active->count()),
            Stat::make('MRR', '$' . number_format($mrr, 2)),
        ];
    }
}