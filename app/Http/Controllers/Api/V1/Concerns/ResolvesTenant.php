<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\Request;

trait ResolvesTenant
{
    protected function companyId(Request $request): int
    {
        $id = $request->user()?->company_id;
        abort_if(! $id, 403, 'No company context for this user.');

        return (int) $id;
    }

    /**
     * Admin + manager may write; viewer may not.
     * NOTE: if your Phase 3 controllers check roles differently, change only this method.
     */
    protected function authorizeWrite(Request $request): void
    {
        $user = $request->user();

        $allowed = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'manager'])
            : in_array($user->role ?? null, ['admin', 'manager'], true);

        abort_if(! $allowed, 403, 'You do not have permission to modify KPI entries.');
    }
}
