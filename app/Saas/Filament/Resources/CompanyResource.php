<?php

namespace App\Saas\Filament\Resources;

use App\Saas\Filament\Resources\CompanyResource\Pages;
use App\Models\Company;
use App\Saas\Filament\Resources\CompanyResource\RelationManagers\UsersRelationManager;
use App\Saas\Models\Plan;
use App\Saas\Services\TenantSignup;
use App\Support\AppMode;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Saas\Models\Subscription;
use Illuminate\Support\Facades\DB;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Tenants';
    protected static ?int $navigationSort = 1;

    /** Number of sign-ups waiting for a decision, shown next to Companies in the sidebar. */
    public static function getNavigationBadge(): ?string
    {
        try {
            $count = Company::where('approval_status', 'pending')->count();
        } catch (\Throwable) {
            return null;
        }

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** Standalone is one organisation, made by the installer: no adding or deleting companies. */
    public static function canCreate(): bool { return AppMode::saas(); }

    public static function canDelete($record): bool { return AppMode::saas(); }

    public static function canDeleteAny(): bool { return AppMode::saas(); }

    public static function form(Form $form): Form
    {
        $saas = AppMode::saas();

        return $form->schema(array_values(array_filter([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('name_ar')->maxLength(255),
            Forms\Components\TextInput::make('slug')->required()->maxLength(255),
            Forms\Components\TextInput::make('logo')->maxLength(255),
            Forms\Components\TextInput::make('timezone')->required()->maxLength(255)->default('Asia/Riyadh'),
            Forms\Components\TextInput::make('default_language')->required()->maxLength(255)->default('en'),
            Forms\Components\Toggle::make('arabic_enabled')->label('Arabic enabled'),
            Forms\Components\TextInput::make('fiscal_year_start')->required()->maxLength(255)->default('01-01'),
            Forms\Components\Toggle::make('is_active')->required(),
            // Plans and trials exist only in the SaaS product.
            // $saas ? Forms\Components\TextInput::make('plan')->required() : null,
            $saas ? Forms\Components\TextInput::make('app_mode')->required() : null,
            $saas ? Forms\Components\DateTimePicker::make('trial_ends_at') : null,
        ])));
    }

    public static function table(Table $table): Table
    {
        $saas = AppMode::saas();

        return $table
            ->columns(array_values(array_filter([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('name_ar')->searchable(),
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('timezone')->searchable(),
                Tables\Columns\TextColumn::make('default_language')->searchable(),
                Tables\Columns\TextColumn::make('fiscal_year_start')->searchable(),
                // $saas ? Tables\Columns\TextColumn::make('plan') : null,
                $saas ? Tables\Columns\TextColumn::make('current_plan')
    ->label('Current plan')
    ->badge()
    ->state(fn (Company $record) => self::currentSubscription($record)?->plan?->name ?? 'No plan')
    ->color(fn (string $state) => $state === 'No plan' ? 'danger' : 'primary') : null,
$saas ? Tables\Columns\TextColumn::make('plan_status')
    ->label('Plan status')
    ->badge()
    ->state(fn (Company $record) => self::currentSubscription($record)?->status ?? '-') : null,
                $saas ? Tables\Columns\TextColumn::make('app_mode') : null,
                $saas ? Tables\Columns\TextColumn::make('trial_ends_at')->dateTime()->sortable() : null,
                $saas ? Tables\Columns\TextColumn::make('registration')
                    ->label('Registration')
                    ->badge()
                    ->state(fn (Company $record) => TenantSignup::label(TenantSignup::status($record)))
                    ->color(fn (string $state) => match ($state) {
                        'Approved' => 'success',
                        'Rejected' => 'danger',
                        default => 'warning',
                    }) : null,
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])))
            ->defaultSort('created_at', 'desc')
            ->filters($saas ? [
                Tables\Filters\SelectFilter::make('approval_status')->label('Registration')->options([
                    'pending' => 'Pending (email or approval)',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
            ] : [])
            ->actions(array_values(array_filter([
                $saas ? self::changePlanAction() : null,
                $saas ? self::approveAction() : null,
                $saas ? self::rejectAction() : null,
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])))
            ->bulkActions($saas ? [
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ] : []);
    }

    public static function getRelations(): array
    {
        return [UsersRelationManager::class];
    }

    private static function currentSubscription(Company $company): ?Subscription
{
    return Subscription::with('plan')
        ->where('company_id', $company->id)
        ->whereNotIn('status', ['cancelled', 'expired'])
        ->latest('id')
        ->first();
}

/** Move a company to another plan (or restart its trial). The old subscription is kept as cancelled history. */
private static function changePlanAction(): Tables\Actions\Action
{
    return Tables\Actions\Action::make('changePlan')
        ->label('Change plan')
        ->icon('heroicon-o-arrow-path')
        ->color('primary')
        ->modalHeading('Change plan')
        ->fillForm(function (Company $record) {
            $current = self::currentSubscription($record);

            return [
                'plan_id' => $current?->plan_id,
                'status' => $current?->status === 'trialing' ? 'trialing' : 'active',
                'billing_cycle' => $current?->billing_cycle ?? 'monthly',
                'trial_days' => 14,
            ];
        })
        ->form([
            Forms\Components\Select::make('plan_id')
                ->label('Plan')
                ->options(fn () => Plan::orderBy('sort_order')->pluck('name', 'id'))
                ->required()
                ->native(false),
            Forms\Components\Select::make('status')
                ->options(['active' => 'Active', 'trialing' => 'Trial'])
                ->required()
                ->live()
                ->native(false),
            Forms\Components\TextInput::make('trial_days')
                ->label('Trial days from today')
                ->numeric()->minValue(1)->maxValue(365)
                ->visible(fn (Forms\Get $get) => $get('status') === 'trialing'),
            Forms\Components\Select::make('billing_cycle')
                ->options(['monthly' => 'Monthly', 'yearly' => 'Yearly'])
                ->required()
                ->native(false),
        ])
        ->action(function (Company $record, array $data) {
            DB::transaction(function () use ($record, $data) {
                Subscription::where('company_id', $record->id)
                    ->whereNotIn('status', ['cancelled', 'expired'])
                    ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

                $trialEnds = $data['status'] === 'trialing' ? now()->addDays((int) ($data['trial_days'] ?? 14)) : null;

                Subscription::create([
                    'company_id' => $record->id,
                    'plan_id' => $data['plan_id'],
                    'status' => $data['status'],
                    'billing_cycle' => $data['billing_cycle'],
                    'trial_ends_at' => $trialEnds,
                ]);

                $record->forceFill(['trial_ends_at' => $trialEnds])->save();
            });

            Notification::make()->title('Plan changed')->success()->send();
        });
}

    /** Approve a sign-up: pick the plan and trial length; the organization is activated and the admin emailed. */
    private static function approveAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (Company $record) => TenantSignup::status($record) === 'pending_approval')
            ->modalHeading('Approve organization')
            ->modalDescription('The organization becomes active and its admin is emailed. Choose the plan it starts on.')
            ->form([
                Forms\Components\Select::make('plan_id')
                    ->label('Plan')
                    ->options(fn () => Plan::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                    ->default(fn () => Plan::where('is_active', true)->orderBy('sort_order')->value('id'))
                    ->required()
                    ->native(false),
                Forms\Components\TextInput::make('trial_days')
                    ->label('Trial days')
                    ->numeric()->minValue(0)->maxValue(365)->default(14)->required()
                    ->helperText('0 starts an active subscription straight away, without a trial.'),
            ])
            ->action(function (Company $record, array $data) {
                TenantSignup::approve($record, (int) $data['plan_id'], (int) $data['trial_days']);
                Notification::make()->title('Organization approved')->success()->send();
            });
    }

    private static function rejectAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Company $record) => in_array(TenantSignup::status($record), ['pending_approval', 'awaiting_email'], true))
            ->modalHeading('Reject registration')
            ->form([
                Forms\Components\Textarea::make('reason')->label('Reason (shown to the applicant)')->maxLength(500),
            ])
            ->action(function (Company $record, array $data) {
                TenantSignup::reject($record, $data['reason'] ?? null);
                Notification::make()->title('Registration rejected')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'view'   => Pages\ViewCompany::route('/{record}'),
            'edit'   => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
