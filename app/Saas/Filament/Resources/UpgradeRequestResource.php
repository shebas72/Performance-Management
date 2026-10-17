<?php

namespace App\Saas\Filament\Resources;

use App\Saas\Filament\Resources\UpgradeRequestResource\Pages;
use App\Saas\Models\UpgradeRequest;
use App\Saas\Services\BillingService;
use App\Support\AppMode;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Plan upgrades tenants asked for ("direct upgrade by admin"). Approve changes the company's plan straight away. */
class UpgradeRequestResource extends Resource
{
    protected static ?string $model = UpgradeRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';
    protected static ?string $navigationGroup = 'Billing';
    protected static ?string $navigationLabel = 'Upgrade requests';
    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool { return AppMode::saas(); }
    public static function shouldRegisterNavigation(): bool { return AppMode::saas(); }
    public static function canCreate(): bool { return false; }

    public static function getNavigationBadge(): ?string
    {
        try {
            $count = UpgradeRequest::where('status', 'pending')->count();
        } catch (\Throwable) {
            return null;
        }

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'warning'; }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('company.name')->label('Organization')->searchable(),
                Tables\Columns\TextColumn::make('plan.name')->label('Requested plan')->badge(),
                Tables\Columns\TextColumn::make('billing_cycle')->label('Cycle'),
                Tables\Columns\TextColumn::make('note')->limit(60)->wrap(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'approved' => 'success', 'rejected' => 'danger', default => 'warning',
                }),
                Tables\Columns\TextColumn::make('created_at')->label('Requested')->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (UpgradeRequest $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalDescription('The organization is moved to the requested plan now. Arrange payment with them yourself.')
                    ->action(function (UpgradeRequest $record) {
                        app(BillingService::class)->activate($record->company, $record->plan, $record->billing_cycle, 'manual');
                        $record->forceFill(['status' => 'approved', 'decided_at' => now()])->save();
                        Notification::make()->title('Plan changed')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (UpgradeRequest $record) => $record->status === 'pending')
                    ->form([Forms\Components\Textarea::make('decision_note')->label('Note (optional)')->maxLength(500)])
                    ->action(function (UpgradeRequest $record, array $data) {
                        $record->forceFill(['status' => 'rejected', 'decided_at' => now(), 'decision_note' => $data['decision_note'] ?? null])->save();
                        Notification::make()->title('Request rejected')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListUpgradeRequests::route('/')];
    }
}