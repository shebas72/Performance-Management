<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\Company;
use App\Support\AppMode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
            $saas ? Forms\Components\TextInput::make('plan')->required() : null,
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
                $saas ? Tables\Columns\TextColumn::make('plan') : null,
                $saas ? Tables\Columns\TextColumn::make('app_mode') : null,
                $saas ? Tables\Columns\TextColumn::make('trial_ends_at')->dateTime()->sortable() : null,
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])))
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions($saas ? [
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ] : []);
    }

    public static function getRelations(): array
    {
        return [];
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
