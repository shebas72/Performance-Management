<?php

namespace App\Saas\Filament\Resources\CompanyResource\RelationManagers;

use App\Saas\Filament\Resources\TenantUserResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/** The organization's people, on the company's own page, with the same actions as the Tenant users list. */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';
    protected static ?string $title = 'Users';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns(TenantUserResource::columns(withCompany: false))
            ->actions(TenantUserResource::rowActions(linkToEdit: true));
    }
}
