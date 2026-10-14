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
     * Roles with kpis.manage may write.
     * NOTE: if your Phase 3 controllers check roles differently, change only this method.
     */
    protected function authorizeWrite(Request $request): void
    {
        $user = $request->user();

        $allowed = $user->hasPermission('kpis.manage');

        abort_if(! $allowed, 403, 'You do not have permission to modify KPI entries.');
    }
}
