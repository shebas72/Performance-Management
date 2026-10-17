<?php

namespace App\Saas\Filament\Resources\TenantUserResource\Pages;

use App\Saas\Filament\Resources\TenantUserResource;
use Filament\Resources\Pages\ListRecords;

class ListTenantUsers extends ListRecords
{
    protected static string $resource = TenantUserResource::class;
}
