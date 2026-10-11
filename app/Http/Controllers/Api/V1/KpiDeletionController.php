<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\KpiDeletionLog;
use App\Services\AccessScope;
use Illuminate\Http\Request;

/** GET /kpi-deletions: who deleted which KPI and when. Admins and managers; a user limited to some departments sees only those. */
class KpiDeletionController extends Controller
{
    use ResolvesTenant;

    public function __construct(private AccessScope $access) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'manager']), 403, 'Only admins and managers can see deleted KPIs.');
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100'], 'year' => ['nullable', 'integer']]);
        $cid = $this->companyId($request);

        $ids = $this->access->viewIds($request->user());

        $page = KpiDeletionLog::with('user:id,name')->where('company_id', $cid)
            ->when($ids !== null, fn ($q) => $q->whereIn('department_id', $ids))
            ->when($request->filled('year'), fn ($q) => $q->where('year', $request->integer('year')))
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'data' => $page->getCollection()->map(fn ($r) => [
                'id' => $r->id, 'code' => $r->code, 'name' => $r->name, 'name_ar' => $r->name_ar, 'year' => $r->year,
                'department' => $r->department_name, 'entries' => $r->entries_count, 'targets' => $r->targets_count,
                'user' => $r->user?->name, 'at' => $r->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
