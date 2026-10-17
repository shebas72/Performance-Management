<?php

namespace App\Saas\Filament\Resources;

use App\Models\User;
use App\Saas\Filament\Resources\TenantUserResource\Pages;
use App\Saas\Services\TenantSignup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

/**
 * The people inside tenant organizations (never super admins): see them, edit details, reset a password,
 * verify an email by hand, deactivate or delete. The list opens filtered to tenant admins.
 */
class TenantUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'tenant-users';
    protected static ?string $modelLabel = 'tenant user';
    protected static ?string $pluralModelLabel = 'Tenant users';
    protected static ?string $navigationLabel = 'Tenant users';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Tenants';
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool { return \App\Support\AppMode::saas(); }

    public static function shouldRegisterNavigation(): bool { return \App\Support\AppMode::saas(); }

    public static function canCreate(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_super_admin', false)
            ->whereNotNull('company_id')
            ->with(['roles']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')
                ->email()->required()->maxLength(255)
                ->unique('users', 'email', ignoreRecord: true),
            Forms\Components\TextInput::make('job_title')->maxLength(255),
            Forms\Components\Toggle::make('is_active')->label('Active (can sign in)'),
        ])->columns(2);
    }

    /** The same columns are used by the Companies page relation manager, which hides the company column. */
    public static function columns(bool $withCompany = true): array
    {
        return array_values(array_filter([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('medium'),
            Tables\Columns\TextColumn::make('email')->searchable()->copyable(),
            $withCompany ? Tables\Columns\TextColumn::make('company_name')
                ->label('Organization')
                ->state(fn (User $record) => \App\Models\Company::whereKey($record->company_id)->value('name'))
                : null,
            Tables\Columns\TextColumn::make('role')
                ->badge()
                ->state(fn (User $record) => $record->role_key ?: ($record->roles->pluck('name')->first() ?: 'viewer')),
            Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            Tables\Columns\IconColumn::make('verified')
                ->label('Email verified')
                ->boolean()
                ->state(fn (User $record) => ! $record->email_verification_token),
            Tables\Columns\TextColumn::make('created_at')->label('Joined')->since()->sortable(),
        ]));
    }

    public static function rowActions(bool $linkToEdit = false): array
    {
        $edit = Tables\Actions\EditAction::make();

        if ($linkToEdit) {
            $edit->url(fn (User $record) => static::getUrl('edit', ['record' => $record]));
        }

        return [
            $edit,
            Tables\Actions\ActionGroup::make([
                static::resetPasswordAction(),
                static::verifyEmailAction(),
                static::resendVerificationAction(),
                static::deleteAction(),
            ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::columns())
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('admins_only')
                    ->label('Tenant admins only')
                    ->default()
                    ->query(fn (Builder $query) => $query->where(
                        fn (Builder $q) => $q->where('role_key', 'admin')
                            ->orWhereHas('roles', fn (Builder $r) => $r->where('name', 'admin'))
                    )),
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Organization')
                    ->options(fn () => \App\Models\Company::orderBy('name')->pluck('name', 'id')),
            ])
            ->actions(static::rowActions());
    }

    private static function isAdmin(User $user): bool
    {
        return $user->role_key === 'admin' || $user->hasRole('admin');
    }

    public static function resetPasswordAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('resetPassword')
            ->label('Reset password')
            ->icon('heroicon-o-key')
            ->modalHeading('Reset password')
            ->modalDescription('Sets a new password and signs the person out everywhere. Tell them the new password yourself.')
            ->form([
                Forms\Components\TextInput::make('password')
                    ->label('New password')
                    ->password()->revealable()->required()->minLength(8)->confirmed(),
                Forms\Components\TextInput::make('password_confirmation')
                    ->label('Confirm new password')
                    ->password()->revealable()->required(),
            ])
            ->action(function (User $record, array $data) {
                // The model's "hashed" cast hashes it.
                $record->forceFill(['password' => $data['password']])->save();
                $record->tokens()->delete();

                Notification::make()->title('Password changed')->success()->send();
            });
    }

    public static function verifyEmailAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('verifyEmail')
            ->label('Mark email verified')
            ->icon('heroicon-o-check-circle')
            ->visible(fn (User $record) => (bool) $record->email_verification_token)
            ->requiresConfirmation()
            ->action(function (User $record) {
                $record->forceFill([
                    'email_verified_at' => now(),
                    'email_verification_token' => null,
                    'email_verification_sent_at' => null,
                ])->save();

                Notification::make()->title('Email marked as verified')->success()->send();
            });
    }

    public static function resendVerificationAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('resendVerification')
            ->label('Resend verification email')
            ->icon('heroicon-o-envelope')
            ->visible(fn (User $record) => (bool) $record->email_verification_token)
            ->action(function (User $record) {
                TenantSignup::sendVerification($record);

                Notification::make()->title('Verification email sent')->success()->send();
            });
    }

    public static function deleteAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('deleteUser')
            ->label('Delete')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete this person?')
            ->modalDescription('This removes them permanently. If they already have records in the system, deactivate them instead.')
            ->action(function (User $record) {
                if (static::isAdmin($record) && $record->is_active) {
                    $otherAdmins = User::where('company_id', $record->company_id)
                        ->where('id', '!=', $record->id)
                        ->where('is_active', true)
                        ->where(fn (Builder $q) => $q->where('role_key', 'admin')->orWhereHas('roles', fn (Builder $r) => $r->where('name', 'admin')))
                        ->count();

                    if ($otherAdmins === 0) {
                        Notification::make()->title('This is the only active admin of the organization')
                            ->body('Add another admin first, or delete the whole organization.')->warning()->send();

                        return;
                    }
                }

                try {
                    $record->tokens()->delete();
                    $record->delete();
                    Notification::make()->title('User deleted')->success()->send();
                } catch (QueryException) {
                    Notification::make()->title('Cannot delete: this person has linked records')
                        ->body('Edit them and turn off Active instead.')->danger()->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantUsers::route('/'),
            'edit'  => Pages\EditTenantUser::route('/{record}/edit'),
        ];
    }
}
