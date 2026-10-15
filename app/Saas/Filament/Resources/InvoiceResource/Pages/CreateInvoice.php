<?php

namespace App\Saas\Filament\Resources\InvoiceResource\Pages;

use App\Saas\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;
}
