<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\ExecutionPlanTask;
use App\Models\Initiative;
use App\Models\ProgressUpdate;
use App\Services\ProgressRollup;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Initiatives (linked to projects and KPIs), their execution plan tasks, monthly progress and the performance data.
 * The models have no tenant scope, so every query filters on company_id explicitly.
 */
class InitiativeController extends Controller
{
    use ResolvesTenant;

    private const STATUSES = ['not_started', 'in_progress', 'completed', 'delayed', 'cancelled'];
    private const WITH     = ['department:id,name,name_ar', 'objective:id,code,name,name_ar', 'owner:id,name', 'tasks'];

    public function __construct(private ProgressRollup $rollup) {}

    public function index(Request $request)
    {
        $request->validate([
            'year' => ['nullable', 'integer'], 'department_id' => ['nullable', 'integer'], 'strategic_objective_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);

        $rows = Initiative::with(self::WITH)->where('company_id', $this->companyId($request))
            ->when($request->filled('year'), fn ($q) => $q->where('year', $request->integer('year')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('strategic_objective_id'), fn ($q) => $q->where('strategic_objective_id', $request->integer('strategic_objective_id')))
            ->when($request->filled('project_id'), fn ($q) => $q->whereIn('id', DB::table('initiative_project')->where('project_id', $request->integer('project_id'))->select('initiative_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('code')->orderBy('id')->limit(500)->get();

        return response()->json(['data' => $this->shapeMany($rows)]);
    }

    public function show(Request $request, int $id)
    {
        return response()->json(['data' => $this->shapeMany(collect([$this->find($request, $id)]))[0]]);
    }

    public function store(Request $request)
    {
        $this->authorizeWrite($request);
        $cid  = $this->companyId($request);
        $data = $request->validate($this->rules($cid));
        [$projectIds, $kpiIds] = $this->links($request, $data);

        $i = new Initiative();
        DB::transaction(function () use ($i, $data, $cid, $request, $projectIds, $kpiIds) {
            $i->forceFill(array_merge($data, ['company_id' => $cid, 'owner_id' => $data['owner_id'] ?? $request->user()->id]))->save();
            $this->syncLinks($i, $projectIds ?? [], $kpiIds ?? []);
        });
        $this->rollup->projects($projectIds ?? []);

        return response()->json(['data' => $this->shapeMany(collect([$i->load(self::WITH)]))[0]], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeWrite($request);
        $i    = $this->find($request, $id);
        $data = $request->validate($this->rules($i->company_id));
        [$projectIds, $kpiIds] = $this->links($request, $data);
        $before = DB::table('initiative_project')->where('initiative_id', $i->id)->pluck('project_id')->all();

        DB::transaction(function () use ($i, $data, $projectIds, $kpiIds) {
            $i->forceFill($data)->save();
            $this->syncLinks($i, $projectIds, $kpiIds);
        });
        $this->rollup->projects(array_unique(array_merge($before, $projectIds ?? [])));

        return response()->json(['data' => $this->shapeMany(collect([$i->fresh(self::WITH)]))[0]]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeWrite($request);
        $i      = $this->find($request, $id);
        $linked = DB::table('initiative_project')->where('initiative_id', $i->id)->pluck('project_id')->all();

        DB::transaction(function () use ($i) {
            ProgressUpdate::where('subject_type', 'initiative')->where('subject_id', $i->id)->delete();
            $i->delete(); // tasks and links cascade
        });
        $this->rollup->projects($linked);

        return response()->noContent();
    }

    /** Report an initiative's completion for one month (admins/managers or the owner). Overrides the automatic value for that month. */
    public function progress(Request $request, int $id)
    {
        $i = $this->find($request, $id);
        $u = $request->user();
        abort_unless($u->hasAnyRole(['admin', 'manager']) || $i->owner_id === $u->id, 403, 'You do not have permission to update this initiative.');

        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'], 'month' => ['required', 'integer', 'between:1,12'],
            'completion_pct' => ['required', 'numeric', 'between:0,100'], 'note' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($i, $data, $u) {
            ProgressUpdate::updateOrCreate(
                ['subject_type' => 'initiative', 'subject_id' => $i->id, 'year' => $data['year'], 'month' => $data['month']],
                ['company_id' => $i->company_id, 'completion_pct' => $data['completion_pct'], 'note' => $data['note'] ?? null, 'source' => 'manual', 'updated_by' => $u->id]
            );
            $this->rollup->applyLatest('initiative', $i);
        });
        $this->rollup->projectsOfInitiative($i->id);

        return response()->json(['data' => $this->shapeMany(collect([$i->fresh(self::WITH)]))[0]]);
    }

    /* ---- Execution plan tasks ---- */

    public function storeTask(Request $request, int $id)
    {
        $i = $this->find($request, $id);
        $this->authorizeTask($request, $i);
        $data = $request->validate($this->taskRules());

        $t = new ExecutionPlanTask();
        $t->forceFill(array_merge($data, [
            'initiative_id' => $i->id, 'company_id' => $i->company_id, 'status' => $this->taskStatus((float) $data['completion_pct']),
            'sort_order' => (int) ExecutionPlanTask::where('initiative_id', $i->id)->max('sort_order') + 1,
        ]))->save();
        $this->rollup->initiative($i);

        return response()->json(['data' => $this->shapeMany(collect([$i->fresh(self::WITH)]))[0]], 201);
    }

    public function updateTask(Request $request, int $id)
    {
        $t = $this->findTask($request, $id);
        $this->authorizeTask($request, $t->initiative);
        $data = $request->validate($this->taskRules());
        $t->forceFill($data + ['status' => $this->taskStatus((float) $data['completion_pct'])])->save();
        $this->rollup->initiative($t->initiative);

        return response()->json(['data' => $this->shapeMany(collect([$t->initiative->fresh(self::WITH)]))[0]]);
    }

    public function destroyTask(Request $request, int $id)
    {
        $t = $this->findTask($request, $id);
        $i = $t->initiative;
        $this->authorizeTask($request, $i);
        $t->delete();
        $this->rollup->initiative($i);

        return response()->noContent();
    }

    /** GET /dashboard/initiatives?year=&month=&department_id= — same shape as /dashboard/projects. */
    public function performance(Request $request)
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'month' => ['nullable', 'integer', 'between:1,12'], 'department_id' => ['nullable', 'integer']]);
        $cid  = $this->companyId($request);
        $year = $request->integer('year') ?: (int) Initiative::where('company_id', $cid)->max('year') ?: (int) date('Y');

        $items = Initiative::with('department:id,name,name_ar')->where('company_id', $cid)->where('year', $year)
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->orderBy('code')->orderBy('id')->get();

        $base    = ProgressUpdate::where('company_id', $cid)->where('subject_type', 'initiative')->where('year', $year);
        $updates = (clone $base)->whereIn('subject_id', $items->pluck('id'))->get()->groupBy('subject_id');
        $month   = $request->integer('month') ?: (int) (clone $base)->max('month') ?: (int) date('n');

        $carry = fn (array $s, int $m) => collect(array_slice($s, 0, $m))->filter(fn ($v) => $v !== null)->last();

        $rows = $items->map(function ($i) use ($updates, $month, $carry) {
            $by     = ($updates[$i->id] ?? collect())->keyBy('month');
            $series = array_map(fn ($m) => isset($by[$m]) ? (float) $by[$m]->completion_pct : null, range(1, 12));

            return [
                'id' => $i->id, 'code' => $i->code, 'name' => $i->name, 'name_ar' => $i->name_ar,
                'department' => $i->department?->only(['id', 'name', 'name_ar']),
                'status' => $i->status, 'is_on_timeline' => (bool) $i->is_on_timeline,
                'month_pct' => $carry($series, $month), 'series' => $series,
            ];
        })->values();

        $avg   = fn ($vals) => ($v = collect($vals)->filter(fn ($x) => $x !== null))->isEmpty() ? null : round($v->avg(), 2);
        $trend = array_map(fn ($m) => $avg($rows->map(fn ($r) => $carry($r['series'], $m))), range(1, 12));

        return response()->json(['data' => [
            'period' => ['year' => $year, 'month' => $month],
            'overall' => ['month' => $trend[$month - 1], 'ytd' => $avg(array_slice($trend, 0, $month))],
            'trend' => $trend, 'initiatives' => $rows,
        ]]);
    }

    /* ---- helpers ---- */

    private function rules(int $cid): array
    {
        $in = fn ($table) => Rule::exists($table, 'id')->where('company_id', $cid);

        return [
            'code' => ['nullable', 'string', 'max:255'], 'name' => ['required', 'string', 'max:255'], 'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'], 'description_ar' => ['nullable', 'string'],
            'status' => ['required', Rule::in(self::STATUSES)], 'is_on_timeline' => ['required', 'boolean'],
            'planned_start_date' => ['nullable', 'date'], 'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'strategic_objective_id' => ['nullable', 'integer', $in('strategic_objectives')],
            'department_id' => ['nullable', 'integer', $in('departments')],
            'owner_id' => ['nullable', 'integer', $in('users')],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'project_ids' => ['nullable', 'array'], 'project_ids.*' => ['integer', 'distinct', $in('projects')],
            'kpi_ids' => ['nullable', 'array'], 'kpi_ids.*' => ['integer', 'distinct', $in('kpis')],
        ];
    }

    private function taskRules(): array
    {
        return [
            'task_name' => ['required', 'string', 'max:255'], 'task_name_ar' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'], 'completion_pct' => ['required', 'numeric', 'between:0,100'],
        ];
    }

    private function taskStatus(float $pct): string
    {
        return $pct >= 100 ? 'completed' : ($pct > 0 ? 'in_progress' : 'pending');
    }

    /** Pull project_ids / kpi_ids out of the validated data. null = not sent (leave the links alone). */
    private function links(Request $request, array &$data): array
    {
        $projects = $request->has('project_ids') ? array_values($data['project_ids'] ?? []) : null;
        $kpis     = $request->has('kpi_ids') ? array_values($data['kpi_ids'] ?? []) : null;
        unset($data['project_ids'], $data['kpi_ids']);

        return [$projects, $kpis];
    }

    private function syncLinks(Initiative $i, ?array $projectIds, ?array $kpiIds): void
    {
        $now = now();
        foreach ([['initiative_project', 'project_id', $projectIds], ['initiative_kpi', 'kpi_id', $kpiIds]] as [$table, $col, $ids]) {
            if ($ids === null) {
                continue;
            }
            DB::table($table)->where('initiative_id', $i->id)->delete();
            DB::table($table)->insert(array_map(fn ($id) => ['initiative_id' => $i->id, $col => $id, 'created_at' => $now, 'updated_at' => $now], $ids));
        }
    }

    private function find(Request $request, int $id): Initiative
    {
        return Initiative::with(self::WITH)->where('company_id', $this->companyId($request))->findOrFail($id);
    }

    private function findTask(Request $request, int $id): ExecutionPlanTask
    {
        return ExecutionPlanTask::with('initiative')->where('company_id', $this->companyId($request))->findOrFail($id);
    }

    private function authorizeWrite(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'manager']), 403, 'You do not have permission to modify this resource.');
    }

    private function authorizeTask(Request $request, Initiative $i): void
    {
        $u = $request->user();
        abort_unless($u->hasAnyRole(['admin', 'manager']) || $i->owner_id === $u->id, 403, 'You do not have permission to change this initiative\'s plan.');
    }

    /** Loads the linked projects and KPIs for a batch of initiatives in two queries. */
    private function shapeMany(Collection $items): array
    {
        $ids      = $items->pluck('id');
        $projects = DB::table('initiative_project as ip')->join('projects as p', 'p.id', '=', 'ip.project_id')->leftJoin('departments as d', 'd.id', '=', 'p.department_id')
            ->whereIn('ip.initiative_id', $ids)->orderBy('ip.id')
            ->get(['ip.initiative_id', 'p.id', 'p.code', 'p.name', 'p.name_ar', 'p.completion_pct', 'd.name as department_name', 'd.name_ar as department_name_ar'])->groupBy('initiative_id');
        $kpis = DB::table('initiative_kpi as ik')->join('kpis as k', 'k.id', '=', 'ik.kpi_id')
            ->whereIn('ik.initiative_id', $ids)->orderBy('ik.id')->get(['ik.initiative_id', 'k.id', 'k.code', 'k.name', 'k.name_ar'])->groupBy('initiative_id');

        return $items->map(fn (Initiative $i) => array_merge(
            $i->only(['id', 'department_id', 'strategic_objective_id', 'owner_id', 'code', 'name', 'name_ar', 'description', 'description_ar', 'status', 'year']),
            [
                'planned_start_date' => $i->planned_start_date?->toDateString(), 'planned_end_date' => $i->planned_end_date?->toDateString(),
                'completion_pct' => (float) $i->completion_pct, 'is_on_timeline' => (bool) $i->is_on_timeline,
                'department' => $i->department?->only(['id', 'name', 'name_ar']),
                'objective' => $i->objective?->only(['id', 'code', 'name', 'name_ar']),
                'owner' => $i->owner?->only(['id', 'name']),
                'projects' => ($projects[$i->id] ?? collect())->map(fn ($p) => [
                    'id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'name_ar' => $p->name_ar, 'completion_pct' => (float) $p->completion_pct,
                    'department_name' => $p->department_name, 'department_name_ar' => $p->department_name_ar,
                ])->values()->all(),
                'kpis' => ($kpis[$i->id] ?? collect())->map(fn ($k) => ['id' => $k->id, 'code' => $k->code, 'name' => $k->name, 'name_ar' => $k->name_ar])->values()->all(),
                'tasks' => $i->tasks->map(fn ($t) => [
                    'id' => $t->id, 'task_name' => $t->task_name, 'task_name_ar' => $t->task_name_ar, 'due_date' => $t->due_date?->toDateString(),
                    'completion_pct' => (float) $t->completion_pct,
                    // 'overdue' is worked out when read, so it never goes stale.
                    'status' => $t->status !== 'completed' && $t->due_date && $t->due_date->isPast() && ! $t->due_date->isToday() ? 'overdue' : $t->status,
                ])->values()->all(),
            ]
        ))->values()->all();
    }
}
