<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Initiative;
use App\Models\KpiEntry;
use App\Models\KpiTarget;
use App\Models\ProgressUpdate;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Employee and team performance report.
 *
 * A person's KPIs are the active KPIs they own (kpis.owner_id) that the viewer may see. Their score is the KPI-weight average
 * of those KPIs, with the same score cap and thresholds as everywhere else (PerformanceService / KpiCalculator).
 *
 * Who is listed:
 *   admin                           -> everyone in the company, every team, and the KPIs that have no owner
 *   manager                         -> people in their team departments (own departments with sub-departments, or what they can see
 *                                      when none are assigned), their direct reports, and themselves; their own team
 *   employee / viewer, "colleagues" -> the same rule as a manager, without the teams and the unowned KPIs
 *   employee / viewer, "own"        -> only their own row (company setting team_report_visibility)
 * A person's KPIs, projects and initiatives are always limited to what the viewer can see.
 */
class TeamPerformanceService
{
    public function __construct(private PerformanceService $perf, private AccessScope $access, private KpiCalculator $calc) {}

    public function build(User $viewer, int $year, int $month): array
    {
        $cid      = (int) $viewer->company_id;
        $isAdmin  = $viewer->hasRole('admin');
        $isLead   = $viewer->hasAnyRole(['admin', 'manager']);
        $mode     = Company::whereKey($cid)->value('team_report_visibility') ?: 'colleagues';
        $selfOnly = ! $isLead && $mode === 'own';

        $ctx  = $this->perf->load($cid, $year, $viewer);
        $kpis = $ctx['kpis'];
        $cur  = $this->perf->kpiScores($ctx, $month);
        $ytd  = $this->perf->kpiScores($ctx, $month, true);

        // --- data entry: which months should have a value (a target exists) and which have none
        $targets = KpiTarget::withoutGlobalScopes()->where('company_id', $cid)->where('year', $year)->where('month', '<=', $month)
            ->whereIn('kpi_id', $kpis->pluck('id'))->get(['kpi_id', 'month'])->groupBy('kpi_id');
        $expected = [];
        $missing  = [];
        foreach ($kpis as $k) {
            $months = $targets->get($k->id, collect())->pluck('month')->map(fn ($m) => (int) $m)->unique()->values();
            $have   = $ctx['entries']->get($k->id, collect())->filter(fn ($e) => $e->achievement_pct !== null)->keys()->map(fn ($m) => (int) $m);
            $expected[$k->id] = $months->count();
            $missing[$k->id]  = $months->diff($have)->values()->all();
        }
        $logged = KpiEntry::where('company_id', $cid)->where('year', $year)->where('month', '<=', $month)
            ->whereIn('kpi_id', $kpis->pluck('id'))->whereNotNull('logged_by')
            ->select('logged_by', DB::raw('count(*) as c'))->groupBy('logged_by')->pluck('c', 'logged_by');

        // --- people
        $users = User::where('company_id', $cid)->where('is_super_admin', false)->with('roles:id,name')->orderBy('name')->get();
        $links = DB::table('department_user')->whereIn('user_id', $users->pluck('id'))->where('kind', 'member')->get()->groupBy('user_id');
        $depts = DB::table('departments')->where('company_id', $cid)->get(['id', 'name', 'name_ar'])->keyBy('id');
        $owned = $kpis->whereNotNull('owner_id')->groupBy('owner_id');

        $teamDepts = $isAdmin ? null : $this->access->editIds($viewer);
        $eligible  = function (User $u) use ($viewer, $isAdmin, $selfOnly, $teamDepts, $links, $owned, $kpis) {
            if ($u->id === $viewer->id) return true;
            if ($selfOnly) return false;
            if ($isAdmin || $teamDepts === null) return $u->is_active || $owned->has($u->id);
            if ((int) $u->manager_id === $viewer->id) return true;
            $mine = $links->get($u->id, collect())->pluck('department_id')->map(fn ($d) => (int) $d)->all();
            if (array_intersect($mine, $teamDepts)) return true;

            return $owned->get($u->id, collect())->contains(fn ($k) => in_array((int) $k->department_id, $teamDepts, true));
        };
        $people = $users->filter(fn (User $u) => ($u->is_active || $owned->has($u->id) || $u->id === $viewer->id) && $eligible($u))->values();

        $unowned = collect();
        if ($isLead) {
            $unowned = $kpis->filter(fn ($k) => $k->owner_id === null
                && ($teamDepts === null || in_array((int) $k->department_id, $teamDepts, true)))->values();
        }

        // --- projects and initiatives owned by these people (only what the viewer can see)
        $ids       = $people->pluck('id');
        $projects  = $this->access->scopeVisible(Project::where('company_id', $cid)->where('year', $year)->where('is_active', true)->whereIn('owner_id', $ids), $viewer)
            ->orderBy('code')->get();
        $initiatives = $this->access->scopeVisible(Initiative::where('company_id', $cid)->where('year', $year)->whereIn('owner_id', $ids), $viewer)
            ->orderBy('code')->get();
        $pctP = $this->carried('project', $cid, $year, $month, $projects->pluck('id'));
        $pctI = $this->carried('initiative', $cid, $year, $month, $initiatives->pluck('id'));

        // --- rows
        $rows = $people->map(function (User $u) use ($owned, $cur, $ytd, $expected, $missing, $logged, $links, $depts, $projects, $initiatives, $pctP, $pctI, $viewer) {
            $mine = $owned->get($u->id, collect())->values();

            return [
                'user_id' => $u->id, 'name' => $u->name, 'role' => $u->roles->pluck('name')->first(),
                'is_active' => (bool) ($u->is_active ?? true), 'is_me' => $u->id === $viewer->id, 'unassigned' => false,
                'departments' => $links->get($u->id, collect())->pluck('department_id')->map(fn ($d) => $depts->get((int) $d))->filter()
                    ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'name_ar' => $d->name_ar])->values(),
            ] + $this->metrics($mine, $cur, $ytd, $expected, $missing) + [
                'data' => $this->dataBlock($mine, $expected, $missing) + ['entries_logged' => (int) ($logged[$u->id] ?? 0)],
                'projects'    => $this->workBlock($projects->where('owner_id', $u->id), $pctP),
                'initiatives' => $this->workBlock($initiatives->where('owner_id', $u->id), $pctI),
            ];
        })->values();

        if ($unowned->isNotEmpty()) {
            $rows->push([
                'user_id' => null, 'name' => null, 'role' => null, 'is_active' => true, 'is_me' => false, 'unassigned' => true, 'departments' => [],
            ] + $this->metrics($unowned, $cur, $ytd, $expected, $missing) + [
                'data' => $this->dataBlock($unowned, $expected, $missing) + ['entries_logged' => 0],
                'projects' => $this->workBlock(collect(), []), 'initiatives' => $this->workBlock(collect(), []),
            ]);
        }

        // --- teams: one per manager for admins, the viewer's own for a manager
        $teams = $isLead ? $this->teams($viewer, $isAdmin, $users, $links, $depts, $owned, $cur, $ytd, $expected, $missing) : [];

        // --- summary over every KPI behind the listed rows
        $scopeKpis = $people->flatMap(fn ($u) => $owned->get($u->id, collect()))->merge($unowned)->unique('id')->values();

        return [
            'period'  => ['year' => $year, 'month' => $month],
            'meta'    => [
                'scope' => $selfOnly ? 'self' : ($isAdmin || $teamDepts === null ? 'company' : 'team'),
                'visibility' => $mode, 'is_lead' => $isLead,
            ],
            'summary' => [
                'people' => $people->filter(fn ($u) => $owned->has($u->id))->count(),
                'unowned_kpis' => $unowned->count(),
            ] + collect($this->metrics($scopeKpis, $cur, $ytd, $expected, $missing))->except('kpis')->all()
              + ['data' => $this->dataBlock($scopeKpis, $expected, $missing)],
            'teams'   => $teams,
            'people'  => $rows,
        ];
    }

    /** KPI figures for a set of KPIs: counts by status, month and year-to-date score, and the KPI list. */
    private function metrics(Collection $kpis, array $cur, array $ytd, array $expected, array $missing): array
    {
        $c = ['on_track' => 0, 'at_risk' => 0, 'behind' => 0, 'not_entered' => 0];
        foreach ($kpis as $k) {
            $s = $cur[$k->id]['status'];
            $c[array_key_exists($s, $c) ? $s : 'not_entered']++;
        }
        $m = $this->wavg($kpis, $cur);
        $y = $this->wavg($kpis, $ytd);

        return [
            'kpi_count' => $kpis->count(),
            'current'   => ['score' => $m, 'status' => $this->calc->statusForScore($m)] + $c,
            'ytd'       => ['score' => $y, 'status' => $this->calc->statusForScore($y)],
            'kpis'      => $kpis->map(fn ($k) => [
                'id' => $k->id, 'code' => $k->code, 'name' => $k->name, 'name_ar' => $k->name_ar, 'department_id' => $k->department_id,
                'score' => $cur[$k->id]['score'], 'status' => $cur[$k->id]['status'], 'ytd_score' => $ytd[$k->id]['score'],
                'expected' => $expected[$k->id] ?? 0, 'missing_months' => $missing[$k->id] ?? [],
            ])->values(),
        ];
    }

    private function dataBlock(Collection $kpis, array $expected, array $missing): array
    {
        $exp = $kpis->sum(fn ($k) => $expected[$k->id] ?? 0);
        $mis = $kpis->sum(fn ($k) => count($missing[$k->id] ?? []));

        return ['expected' => $exp, 'missing' => $mis, 'pct' => $exp ? round(($exp - $mis) / $exp * 100, 1) : null];
    }

    /** Projects or initiatives: count, average completion carried forward to the month, late and completed ones, and the list. */
    private function workBlock(Collection $items, array $pct): array
    {
        $rows = $items->map(fn ($i) => [
            'id' => $i->id, 'code' => $i->code, 'name' => $i->name, 'name_ar' => $i->name_ar, 'status' => $i->status,
            'is_on_timeline' => (bool) $i->is_on_timeline, 'pct' => $pct[$i->id] ?? null,
        ])->values();
        $vals = $rows->pluck('pct')->filter(fn ($v) => $v !== null);

        return [
            'count'     => $rows->count(),
            'avg_pct'   => $vals->isEmpty() ? null : round($vals->avg(), 1),
            'completed' => $rows->where('status', 'completed')->count(),
            'late'      => $rows->filter(fn ($r) => ! in_array($r['status'], ['completed', 'cancelled'], true) && ($r['status'] === 'delayed' || ! $r['is_on_timeline']))->count(),
            'items'     => $rows,
        ];
    }

    /** Latest reported completion up to the month, per project / initiative. */
    private function carried(string $type, int $cid, int $year, int $month, Collection $ids): array
    {
        if ($ids->isEmpty()) return [];

        return ProgressUpdate::where('company_id', $cid)->where('subject_type', $type)->where('year', $year)->where('month', '<=', $month)
            ->whereIn('subject_id', $ids)->orderBy('month')->get(['subject_id', 'completion_pct'])
            ->groupBy('subject_id')->map(fn ($g) => (float) $g->last()->completion_pct)->all();
    }

    private function teams(User $viewer, bool $isAdmin, Collection $users, Collection $links, Collection $depts, Collection $owned, array $cur, array $ytd, array $expected, array $missing): array
    {
        $managers = $isAdmin ? $users->filter(fn (User $u) => $u->is_active && $u->hasRole('manager')) : collect([$viewer])->filter(fn (User $u) => $u->hasRole('manager'));
        $out = [];

        foreach ($managers as $m) {
            $teamDepts = $this->access->memberIds($m);
            $members = $users->filter(function (User $u) use ($m, $teamDepts, $links) {
                if (! $u->is_active && $u->id !== $m->id) return false;
                if ($u->id === $m->id || (int) $u->manager_id === $m->id) return true;
                $mine = $links->get($u->id, collect())->pluck('department_id')->map(fn ($d) => (int) $d)->all();

                return (bool) array_intersect($mine, $teamDepts);
            })->values();
            if ($members->count() < 2 && ! $teamDepts) continue; // a manager with nobody assigned has no team to report on

            $kpis = $members->flatMap(fn ($u) => $owned->get($u->id, collect()))->unique('id')->values();
            $out[] = [
                'manager' => ['id' => $m->id, 'name' => $m->name],
                'member_count' => $members->count(),
                'departments'  => collect($this->access->memberIdsDirect($m))->map(fn ($d) => $depts->get($d))->filter()
                    ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'name_ar' => $d->name_ar])->values(),
            ] + collect($this->metrics($kpis, $cur, $ytd, $expected, $missing))->except('kpis')->all()
              + ['data' => $this->dataBlock($kpis, $expected, $missing)];
        }

        return $out;
    }

    /** Weighted average of KPI scores (null scores skipped, missing weight = 1), like PerformanceService. */
    private function wavg(Collection $kpis, array $scores): ?float
    {
        $sum = 0.0; $sw = 0.0;
        foreach ($kpis as $k) {
            $s = $scores[$k->id]['score'] ?? null;
            if ($s === null) continue;
            $w = ($k->weight !== null && (float) $k->weight > 0) ? (float) $k->weight : 1.0;
            $sum += $s * $w;
            $sw  += $w;
        }

        return $sw > 0 ? round($sum / $sw, 2) : null;
    }
}
