<?php

namespace App\Services;

use App\Models\BscPerspective;
use App\Models\Department;
use App\Models\Kpi;
use App\Models\KpiEntry;
use App\Models\StrategicObjective;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Pure read-side rollup engine. One load() per request, then as many
 * kpiScores()/rollup() passes as needed (month, YTD, trend) with no extra queries.
 *
 * Hierarchy: KPI (weight) -> Objective (weight) -> Perspective (weight) -> Company.
 * Departments roll up their own KPIs plus all sub-departments' KPIs.
 * KPIs that belong to no objective count toward department scores and KPI counts,
 * but not toward the objective -> perspective -> company chain.
 */
class PerformanceService
{
    private const BUCKET = ['on_track' => 'high', 'at_risk' => 'medium', 'behind' => 'low'];

    public function __construct(private KpiCalculator $calc) {}

    /** With a $user, only the KPIs and departments that user may see are loaded (null = whole company). */
    public function load(int $companyId, int $year, ?User $user = null): array
    {
        $ids = $user ? app(AccessScope::class)->viewIds($user) : null;

        $kpis = Kpi::withoutGlobalScopes()
            ->where('company_id', $companyId)->where('year', $year)->where('is_active', true)
            ->when($ids !== null, fn ($q) => $q->whereIn('department_id', $ids))
            ->orderBy('code')->get();

        $objectives = StrategicObjective::withoutGlobalScopes()
            ->where('company_id', $companyId)->where('year', $year)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('code')->get();

        $perspectives = BscPerspective::withoutGlobalScopes()
            ->where('company_id', $companyId)->orderBy('sort_order')->get();

        $departments = Department::withoutGlobalScopes()
            ->where('company_id', $companyId)->where('is_active', true)
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('sort_order')->get();

        if ($ids !== null) {
            // A department whose parent is hidden becomes a top-level one; objectives with no visible KPI are left out.
            $visible = $departments->pluck('id')->all();
            $departments->each(fn ($d) => $d->parent_id = in_array($d->parent_id, $visible) ? $d->parent_id : null);
            $objectives = $objectives->filter(fn ($o) => $kpis->contains('strategic_objective_id', $o->id))->values();
        }

        $entries = KpiEntry::where('company_id', $companyId)->where('year', $year)
            ->whereIn('kpi_id', $kpis->pluck('id'))
            ->get(['id', 'kpi_id', 'month', 'achievement_pct', 'status', 'data_status', 'incomplete_reason'])
            ->groupBy('kpi_id')
            ->map(fn ($rows) => $rows->keyBy('month'));

        $restricted = $ids !== null;

        return compact('kpis', 'objectives', 'perspectives', 'departments', 'entries', 'restricted');
    }

    /** Per-KPI [score, status] for one month, or year-to-date through that month. */
    public function kpiScores(array $ctx, int $month, bool $ytd = false): array
    {
        $cap = $this->calc->scoreCap();
        $out = [];

        foreach ($ctx['kpis'] as $kpi) {
            $rows = $ctx['entries']->get($kpi->id, collect());

            if ($ytd) {
                $vals  = $rows->filter(fn ($e) => (int) $e->month <= $month && $e->achievement_pct !== null)
                              ->map(fn ($e) => min((float) $e->achievement_pct, $cap));
                $score = $vals->isEmpty() ? null : round($vals->avg(), 2);
            } else {
                $e     = $rows->get($month);
                $score = ($e && $e->achievement_pct !== null) ? round(min((float) $e->achievement_pct, $cap), 2) : null;
            }

            $out[$kpi->id] = [
                'score'  => $score,
                'status' => $score === null ? 'not_entered' : $this->calc->status($kpi, $score),
            ];
        }

        return $out;
    }

    public function rollup(array $ctx, array $scores): array
    {
        /** @var Collection $kpis */
        $kpis = $ctx['kpis'];
        $objs = $ctx['objectives']->keyBy('id');
        $kpiScore = fn ($k) => [$scores[$k->id]['score'], $k->weight];

        // Objectives
        $objectives = [];
        foreach ($ctx['objectives'] as $o) {
            $ks = $kpis->where('strategic_objective_id', $o->id);
            $objectives[$o->id] = $this->pack($ks, $scores, $this->wavg($ks->map($kpiScore)));
        }

        // Perspectives (a KPI's perspective = its own, else its objective's)
        $kpiPersp = $kpis->mapWithKeys(fn ($k) => [
            $k->id => $k->bsc_perspective_id ?? $objs->get($k->strategic_objective_id)?->bsc_perspective_id,
        ]);
        $perspectives = [];
        foreach ($ctx['perspectives'] as $p) {
            $ks    = $kpis->filter(fn ($k) => $kpiPersp[$k->id] == $p->id);
            $items = $ctx['objectives']->where('bsc_perspective_id', $p->id)
                        ->map(fn ($o) => [$objectives[$o->id]['score'], $o->weight]);
            $perspectives[$p->id] = $this->pack($ks, $scores, $this->wavg($items) ?? $this->wavg($ks->map($kpiScore)));
        }

        // Departments (include sub-departments)
        $children = $ctx['departments']->groupBy('parent_id');
        $subtree = function (int $id) use (&$subtree, $children): array {
            $ids = [$id];
            foreach ($children->get($id, collect()) as $c) $ids = array_merge($ids, $subtree($c->id));
            return $ids;
        };
        $departments = [];
        foreach ($ctx['departments'] as $d) {
            $ids = $subtree($d->id);
            $ks  = $kpis->filter(fn ($k) => in_array($k->department_id, $ids));
            $departments[$d->id] = $this->pack($ks, $scores, $this->wavg($ks->map($kpiScore)));
        }

        // Company
        $pItems  = $ctx['perspectives']->map(fn ($p) => [$perspectives[$p->id]['score'], $p->weight]);
        $company = $this->pack($kpis, $scores, $this->wavg($pItems) ?? $this->wavg($kpis->map($kpiScore)));

        return compact('company', 'perspectives', 'objectives', 'departments');
    }

    private function pack(Collection $ks, array $scores, ?float $score): array
    {
        $c = ['kpi_count' => $ks->count(), 'high' => 0, 'medium' => 0, 'low' => 0, 'not_entered' => 0];
        foreach ($ks as $k) {
            $c[self::BUCKET[$scores[$k->id]['status']] ?? 'not_entered']++;
        }

        return ['score' => $score, 'status' => $this->calc->statusForScore($score)] + $c;
    }

    /** Weighted average of [score, weight] pairs; null scores skipped; missing weight = 1. */
    private function wavg(iterable $items): ?float
    {
        $sum = 0.0; $sw = 0.0;
        foreach ($items as [$score, $w]) {
            if ($score === null) continue;
            $w = ($w !== null && (float) $w > 0) ? (float) $w : 1.0;
            $sum += $score * $w;
            $sw   += $w;
        }

        return $sw > 0 ? round($sum / $sw, 2) : null;
    }
}
