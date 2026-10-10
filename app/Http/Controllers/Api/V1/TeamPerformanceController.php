<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Kpi;
use App\Models\KpiEntry;
use App\Services\TeamPerformanceService;
use Illuminate\Http\Request;

/** GET /reports/team-performance?year=&month= — employee and team performance. Open to every member; the service limits what each role sees. */
class TeamPerformanceController extends Controller
{
    use ResolvesTenant;

    public function __construct(private TeamPerformanceService $report) {}

    public function index(Request $request)
    {
        $request->validate([
            'year'  => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $cid = $this->companyId($request);

        // Same defaults as the dashboard: the company's latest KPI year and that year's latest entered month.
        $year = $request->integer('year')
            ?: (int) Kpi::withoutGlobalScopes()->where('company_id', $cid)->max('year')
            ?: (int) date('Y');
        $month = $request->integer('month')
            ?: (int) KpiEntry::where('company_id', $cid)->where('year', $year)->max('month')
            ?: (int) date('n');

        return response()->json(['data' => $this->report->build($request->user(), $year, $month)]);
    }
}
