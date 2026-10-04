<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Multi-tenant scoping. Add to every model whose table has company_id.
 * Do NOT add to User or Company (would recurse / is the tenant itself).
 *
 * - Authenticated tenant user  -> queries limited to their company_id
 * - Super admin (Filament)     -> sees everything
 * - No auth (seeders, CLI)     -> unscoped
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $user = Auth::user();

            if (! $user || $user->is_super_admin) {
                return;
            }

            $table = $builder->getModel()->getTable();

            if ($user->company_id) {
                $builder->where("{$table}.company_id", $user->company_id);
            } else {
                // Authenticated but not attached to a company: see nothing.
                $builder->whereRaw('1 = 0');
            }
        });

        static::creating(function ($model) {
            if (empty($model->company_id)) {
                $user = Auth::user();
                if ($user && $user->company_id) {
                    $model->company_id = $user->company_id;
                }
            }
        });
    }
}
