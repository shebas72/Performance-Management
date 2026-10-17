<?php

namespace App\Saas\Filament\Resources\TenantUserResource\Pages;

use App\Saas\Filament\Resources\TenantUserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTenantUser extends EditRecord
{
    protected static string $resource = TenantUserResource::class;

    /** is_active and job_title are not mass-assignable on User, so they are set explicitly. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->forceFill($data)->save();

        // A deactivated person must not keep a working session.
        if (array_key_exists('is_active', $data) && ! $data['is_active']) {
            $record->tokens()->delete();
        }

        return $record;
    }
}
