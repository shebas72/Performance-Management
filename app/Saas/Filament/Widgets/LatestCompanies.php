<?php

namespace App\Saas\Filament\Widgets;

use App\Models\Company;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestCompanies extends BaseWidget
{
    protected static ?string $heading = 'Latest organizations';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Company::query()->latest()->limit(5))
            ->columns([
                TextColumn::make('name')->weight('medium'),
                TextColumn::make('plan')->badge()->placeholder('None'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Joined')->since(),
            ])
            ->paginated(false);
    }
}
