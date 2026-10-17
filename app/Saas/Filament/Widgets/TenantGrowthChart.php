<?php

namespace App\Saas\Filament\Widgets;

use App\Models\Company;
use Filament\Widgets\ChartWidget;

class TenantGrowthChart extends ChartWidget
{
    protected static ?string $heading = 'New organizations, last 6 months';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $months = collect(range(5, 0))->map(fn (int $i) => now()->subMonths($i)->startOfMonth());

        $counts = $months->map(
            fn ($month) => Company::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])->count()
        );

        return [
            'datasets' => [[
                'label' => 'Organizations',
                'data' => $counts->all(),
                'fill' => true,
                'tension' => 0.35,
            ]],
            'labels' => $months->map(fn ($month) => $month->format('M Y'))->all(),
        ];
    }
}
