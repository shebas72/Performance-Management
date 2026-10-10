<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Kpi;
use App\Models\KpiChangeLog;
use App\Services\AccessScope;
use Illuminate\Http\Request;

class KpiHistoryController extends Controller
{
    use ResolvesTenant;

    public function __construct(private AccessScope $access) {}

    /** GET /kpis/{id}/history: newest first, plus who created the KPI. */
    public function index(Request $request, int $id)
    {
        $cid = $this->companyId($request);
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $kpi = Kpi::withoutGlobalScopes()->with('creator:id,name')->where('company_id', $cid)->findOrFail($id);

        abort_unless($this->access->canView($request->user(), $kpi->department_id === null ? null : (int) $kpi->department_id), 404);

        $page = KpiChangeLog::with('user:id,name')
            ->where('company_id', $cid)->where('kpi_id', $kpi->id)
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'data' => $page->getCollection()->map(fn ($r) => [
                'id'      => $r->id,
                'action'  => $r->action,
                'field'   => $r->field,
                'year'    => $r->period_year,
                'month'   => $r->period_month,
                'old'     => $r->old_value,
                'new'     => $r->new_value,
                'user'    => $r->user?->name,
                'at'      => $r->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'creator' => $kpi->created_by ? ['name' => $kpi->creator?->name, 'at' => $kpi->created_at?->toIso8601String()] : null,
        ]);
    }
}
