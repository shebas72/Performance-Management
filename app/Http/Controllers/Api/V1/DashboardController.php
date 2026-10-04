<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Kpi;
use App\Models\KpiEntry;
use App\Services\PerformanceService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ResolvesTenant;

    public function __construct(private PerformanceService $perf) {}

    /** Executive dashboard: overall score, counts, 12-month trend, BSC scorecard, worst KPIs, data completeness. */
    public function index(Request $request)
    {
        [$cid, $year, $month] = $this->period($request);
        $ctx = $this->perf->load($cid, $year);

        $scores    = $this->perf->kpiScores($ctx, $month);
        $cur       = $this->perf->rollup($ctx, $scores);
        $ytd       = $this->perf->rollup($ctx, $this->perf->kpiScores($ctx, $month, true));

        $trend = [];
        for ($m = 1; $m <= 12; $m++) {
    $monthScore = $this->perf->rollup($ctx, $this->perf->kpiScores($ctx, $m))['company']['score'];
    $trend[] = [
        'month'     => $m,
        'score'     => $monthScore,
        'ytd_score' => $monthScore === null
            ? null
            : $this->perf->rollup($ctx, $this->perf->kpiScores($ctx, $m, true))['company']['score'],
    ];
}

        $perspectives = $ctx['perspectives']->map(fn ($p) => [
            'id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'name_ar' => $p->name_ar,
            'color' => $p->color, 'weight' => $p->weight,
            'current' => $cur['perspectives'][$p->id],
            'ytd'     => $ytd['perspectives'][$p->id],
        ])->values();

        $worst = $ctx['kpis']
            ->filter(fn ($k) => $scores[$k->id]['status'] === 'behind')
            ->sortBy(fn ($k) => $scores[$k->id]['score'])
            ->take(5)
            ->map(fn ($k) => [
                'id' => $k->id, 'code' => $k->code, 'name' => $k->name, 'name_ar' => $k->name_ar,
                'department_id' => $k->department_id, 'score' => $scores[$k->id]['score'],
            ])->values();

        $total   = count($scores);
        $entered = count(array_filter($scores, fn ($s) => $s['score'] !== null));
        $reasons = $ctx['entries']->flatMap(fn ($rows) => $rows->values())
            ->filter(fn ($e) => (int) $e->month === $month && $e->data_status === 'incomplete')
            ->groupBy('incomplete_reason')->map->count();

        return response()->json(['data' => [
            'period'            => ['year' => $year, 'month' => $month],
            'overall'           => ['current' => $cur['company'], 'ytd' => $ytd['company']],
            'trend'             => $trend,
            'perspectives'      => $perspectives,
            'worst_kpis'        => $worst,
            'data_completeness' => [
                'expected' => $total,
                'entered'  => $entered,
                'pct'      => $total ? round($entered / $total * 100, 1) : null,
                'incomplete_reasons' => $reasons,
            ],
        ]]);
    }

    /** Strategic objective cards. Filters: perspective_id. */
    public function objectives(Request $request)
    {
        [$cid, $year, $month] = $this->period($request);
        $request->validate(['perspective_id' => ['nullable', 'integer']]);

        $ctx = $this->perf->load($cid, $year);
        $cur = $this->perf->rollup($ctx, $this->perf->kpiScores($ctx, $month));
        $ytd = $this->perf->rollup($ctx, $this->perf->kpiScores($ctx, $month, true));
        $persp = $ctx['perspectives']->keyBy('id');

        $rows = $ctx['objectives']
            ->when($request->filled('perspective_id'), fn ($c) => $c->where('bsc_perspective_id', (int) $request->input('perspective_id')))
            ->map(fn ($o) => [
                'id' => $o->id, 'code' => $o->code, 'name' => $o->name, 'name_ar' => $o->name_ar, 'weight' => $o->weight,
                'perspective' => ($p = $persp->get($o->bsc_perspective_id))
                    ? ['id' => $p->id, 'name' => $p->name, 'name_ar' => $p->name_ar, 'color' => $p->color] : null,
                'current' => $cur['objectives'][$o->id],
                'ytd'     => $ytd['objectives'][$o->id],
            ])->values();

        return response()->json(['data' => $rows, 'meta' => ['year' => $year, 'month' => $month]]);
    }

    /** Department performance (each department includes its sub-departments). */
    public function departments(Request $request)
    {
        [$cid, $year, $month] = $this->period($request);

        $ctx = $this->perf->load($cid, $year);
        $cur = $this->perf->rollup($ctx, $this->perf->kpiScores($ctx, $month));
        $ytd = $this->perf->rollup($ctx, $this->perf->kpiScores($ctx, $month, true));

        $rows = $ctx['departments']->map(fn ($d) => [
            'id' => $d->id, 'parent_id' => $d->parent_id, 'code' => $d->code,
            'name' => $d->name, 'name_ar' => $d->name_ar, 'color' => $d->color,
            'current' => $cur['departments'][$d->id],
            'ytd'     => $ytd['departments'][$d->id],
        ])->values();

        return response()->json(['data' => $rows, 'meta' => ['year' => $year, 'month' => $month]]);
    }

    /** KPI classification: high / medium / low / not_entered. ?basis=ytd for year-to-date. Filters: department_id, objective_id. */
    public function classification(Request $request)
    {
        [$cid, $year, $month] = $this->period($request);
        $request->validate([
            'basis'         => ['nullable', 'in:month,ytd'],
            'department_id' => ['nullable', 'integer'],
            'objective_id'  => ['nullable', 'integer'],
        ]);
        $ytd = $request->input('basis') === 'ytd';

        $ctx    = $this->perf->load($cid, $year);
        $scores = $this->perf->kpiScores($ctx, $month, $ytd);

        $kpis = $ctx['kpis']
            ->when($request->filled('department_id'), fn ($c) => $c->where('department_id', (int) $request->input('department_id')))
            ->when($request->filled('objective_id'), fn ($c) => $c->where('strategic_objective_id', (int) $request->input('objective_id')));

        $groups = ['high' => [], 'medium' => [], 'low' => [], 'not_entered' => []];
        $bucket = ['on_track' => 'high', 'at_risk' => 'medium', 'behind' => 'low'];

        foreach ($kpis as $k) {
            $s = $scores[$k->id];
            $groups[$bucket[$s['status']] ?? 'not_entered'][] = [
                'id' => $k->id, 'code' => $k->code, 'name' => $k->name, 'name_ar' => $k->name_ar,
                'strategic_objective_id' => $k->strategic_objective_id, 'department_id' => $k->department_id,
                'score' => $s['score'], 'status' => $s['status'],
                'incomplete_reason' => $ytd ? null : $ctx['entries']->get($k->id)?->get($month)?->incomplete_reason,
            ];
        }

        foreach ($groups as &$g) {
            usort($g, fn ($a, $b) => ($a['score'] ?? -1) <=> ($b['score'] ?? -1));
        }

        return response()->json(['data' => [
            'period' => ['year' => $year, 'month' => $month, 'basis' => $ytd ? 'ytd' : 'month'],
            'counts' => array_map('count', $groups),
            'groups' => $groups,
        ]]);
    }

    /** year/month default to the company's latest KPI year and that year's latest entered month. */
    private function period(Request $request): array
    {
        $request->validate([
            'year'  => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $cid = $this->companyId($request);

        $year = $request->integer('year')
            ?: (int) Kpi::withoutGlobalScopes()->where('company_id', $cid)->max('year')
            ?: (int) date('Y');

        $month = $request->integer('month')
            ?: (int) KpiEntry::where('company_id', $cid)->where('year', $year)->max('month')
            ?: (int) date('n');

        return [$cid, $year, $month];
    }
}
