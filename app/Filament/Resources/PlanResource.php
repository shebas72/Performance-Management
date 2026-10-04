<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Filament\Resources\PlanResource\RelationManagers;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function shouldRegisterNavigation(): bool
    {
        return config('spms.mode') === 'saas';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('name_ar')
                    ->maxLength(255),
                Forms\Components\TextInput::make('stripe_price_id_monthly')
                    ->maxLength(255),
                Forms\Components\TextInput::make('stripe_price_id_yearly')
                    ->maxLength(255),
                Forms\Components\TextInput::make('kpi_limit')
                    ->required()
                    ->numeric()
                    ->default(50),
                Forms\Components\TextInput::make('user_limit')
                    ->required()
                    ->numeric()
                    ->default(10),
                Forms\Components\TextInput::make('department_limit')
                    ->required()
                    ->numeric()
                    ->default(5),
                Forms\Components\TextInput::make('price_monthly')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                Forms\Components\TextInput::make('price_yearly')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                Forms\Components\Toggle::make('has_pdf_export')
                    ->required(),
                Forms\Components\Toggle::make('has_arabic')
                    ->required(),
                Forms\Components\Toggle::make('has_projects')
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
                Forms\Components\TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name_ar')
                    ->searchable(),
                Tables\Columns\TextColumn::make('stripe_price_id_monthly')
                    ->searchable(),
                Tables\Columns\TextColumn::make('stripe_price_id_yearly')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kpi_limit')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_limit')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('department_limit')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_monthly')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_yearly')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('has_pdf_export')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_arabic')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_projects')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
