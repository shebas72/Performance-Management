<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\ProgressUpdate;
use App\Models\Project;
use App\Services\AccessScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Projects, their monthly progress updates, and the Projects Performance data.
 * The models have no tenant scope, so every query below filters on company_id explicitly.
 */
class ProjectController extends Controller
{
    use ResolvesTenant;

    public function __construct(private AccessScope $access) {}

    private const STATUSES = ['not_started', 'in_progress', 'completed', 'delayed', 'cancelled'];
    private const TYPES    = ['strategic', 'digital_transformation', 'operational'];
    private const WITH     = ['department:id,name,name_ar', 'objective:id,code,name,name_ar', 'owner:id,name'];

    public function index(Request $request)
    {
        $request->validate([
            'year' => ['nullable', 'integer'], 'department_id' => ['nullable', 'integer'],
            'strategic_objective_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(self::STATUSES)], 'search' => ['nullable', 'string', 'max:100'],
        ]);

        $rows = $this->access->scopeVisible(Project::with(self::WITH)->where('company_id', $this->companyId($request)), $request->user())
            ->when($request->filled('year'), fn ($q) => $q->where('year', $request->integer('year')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('strategic_objective_id'), fn ($q) => $q->whereIn('id', DB::table('project_strategic_objective')
                ->where('strategic_objective_id', $request->integer('strategic_objective_id'))->select('project_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->input('search').'%')
                ->orWhere('name_ar', 'like', '%'.$request->input('search').'%')->orWhere('code', 'like', '%'.$request->input('search').'%')))
            ->orderBy('code')->orderBy('id')->limit(500)->get();

        $map = $this->objectivesFor($rows);

        return response()->json(['data' => $rows->map(fn ($p) => $this->shape($p, $map))->values()]);
    }

    public function show(Request $request, int $id)
    {
        $p = $this->find($request, $id);

        return response()->json(['data' => $this->shape($p, $this->objectivesFor(collect([$p])))]);
    }

    public function store(Request $request)
    {
        $this->authorizeWrite($request);
        $cid  = $this->companyId($request);
        $data = $request->validate($this->rules($cid));
        $this->guard($request, $data['department_id'] ?? null);

        $ids = $this->objectiveIds($request, $data);
        $p   = new Project();

        DB::transaction(function () use ($p, $data, $ids, $cid, $request) {
            $p->forceFill(array_merge($data, ['company_id' => $cid, 'owner_id' => $data['owner_id'] ?? $request->user()->id]))->save();
            $this->syncObjectives($p, $ids ?? []);
        });

        return response()->json(['data' => $this->shape($p->load(self::WITH), $this->objectivesFor(collect([$p])))], 201);
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeWrite($request);
        $p = $this->find($request, $id);
        $this->guard($request, $p->department_id);
        $data = $request->validate($this->rules($p->company_id));
        if (array_key_exists('department_id', $data)) $this->guard($request, $data['department_id']);
        $ids  = $this->objectiveIds($request, $data);

        DB::transaction(function () use ($p, $data, $ids) {
            $p->forceFill($data)->save();
            $this->syncObjectives($p, $ids);
        });

        return response()->json(['data' => $this->shape($p->load(self::WITH), $this->objectivesFor(collect([$p])))]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeWrite($request);
        $p = $this->find($request, $id);
        $this->guard($request, $p->department_id);

        DB::transaction(function () use ($p) {
            ProgressUpdate::where('subject_type', 'project')->where('subject_id', $p->id)->delete();
            DB::table('project_strategic_objective')->where('project_id', $p->id)->delete();
            $p->delete();
        });

        return response()->noContent();
    }

    /** Report a project's completion for one month. Admins/managers of its department, or the project's owner. */
    public function progress(Request $request, int $id)
    {
        $p = $this->find($request, $id);
        $u = $request->user();
        abort_unless($this->access->canWorkOn($u, $p->department_id, $p->owner_id), 403, 'You do not have permission to update this project.');

        $data = $request->validate([
            'year'           => ['required', 'integer', 'between:2000,2100'],
            'month'          => ['required', 'integer', 'between:1,12'],
            'completion_pct' => ['required', 'numeric', 'between:0,100'],
            'note'           => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($p, $data, $u) {
            ProgressUpdate::updateOrCreate(
                ['subject_type' => 'project', 'subject_id' => $p->id, 'year' => $data['year'], 'month' => $data['month']],
                ['company_id' => $p->company_id, 'completion_pct' => $data['completion_pct'], 'note' => $data['note'] ?? null, 'source' => 'manual', 'updated_by' => $u->id]
            );

            // The project's current completion is its most recent monthly update.
            $latest = ProgressUpdate::where('subject_type', 'project')->where('subject_id', $p->id)->orderByDesc('year')->orderByDesc('month')->first();
            $pct    = (float) $latest->completion_pct;
            $status = $p->status;
            if ($status !== 'cancelled') {
                if ($pct >= 100) $status = 'completed';
                elseif ($status === 'completed') $status = 'in_progress';
                elseif ($pct > 0 && $status === 'not_started') $status = 'in_progress';
            }
            $p->forceFill(['completion_pct' => $pct, 'status' => $status])->save();
        });

        $p = $p->fresh(self::WITH);

        return response()->json(['data' => $this->shape($p, $this->objectivesFor(collect([$p])))]);
    }

    /**
     * GET /dashboard/projects?year=&month=&department_id=
     * Month view: each project's completion carried forward to the selected month. YTD view: the monthly series.
     */
    public function performance(Request $request)
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'month' => ['nullable', 'integer', 'between:1,12'], 'department_id' => ['nullable', 'integer']]);
        $cid  = $this->companyId($request);
        $year = $request->integer('year') ?: (int) Project::where('company_id', $cid)->max('year') ?: (int) date('Y');

        $projects = $this->access->scopeVisible(Project::with('department:id,name,name_ar')->where('company_id', $cid)->where('year', $year)->where('is_active', true), $request->user())
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->orderBy('code')->orderBy('id')->get();

        $base    = ProgressUpdate::where('company_id', $cid)->where('subject_type', 'project')->where('year', $year);
        $updates = (clone $base)->whereIn('subject_id', $projects->pluck('id'))->get()->groupBy('subject_id');
        $month   = $request->integer('month') ?: (int) (clone $base)->max('month') ?: (int) date('n');

        $carry = fn (array $s, int $m) => collect(array_slice($s, 0, $m))->filter(fn ($v) => $v !== null)->last();

        $rows = $projects->map(function ($p) use ($updates, $month, $carry) {
            $by     = ($updates[$p->id] ?? collect())->keyBy('month');
            $series = array_map(fn ($m) => isset($by[$m]) ? (float) $by[$m]->completion_pct : null, range(1, 12));

            return [
                'id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'name_ar' => $p->name_ar,
                'department' => $p->department?->only(['id', 'name', 'name_ar']),
                'status' => $p->status, 'is_on_timeline' => (bool) $p->is_on_timeline,
                'month_pct' => $carry($series, $month), 'series' => $series,
            ];
        })->values();

        $avg   = fn ($vals) => ($v = collect($vals)->filter(fn ($x) => $x !== null)) ->isEmpty() ? null : round($v->avg(), 2);
        $trend = array_map(fn ($m) => $avg($rows->map(fn ($r) => $carry($r['series'], $m))), range(1, 12));

        return response()->json(['data' => [
            'period'   => ['year' => $year, 'month' => $month],
            'overall'  => ['month' => $trend[$month - 1], 'ytd' => $avg(array_slice($trend, 0, $month))],
            'trend'    => $trend,
            'projects' => $rows,
        ]]);
    }

    private function rules(int $cid): array
    {
        $in = fn ($table) => Rule::exists($table, 'id')->where('company_id', $cid);

        return [
            'code' => ['nullable', 'string', 'max:255'], 'name' => ['required', 'string', 'max:255'], 'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'], 'description_ar' => ['nullable', 'string'],
            'type' => ['required', Rule::in(self::TYPES)], 'status' => ['required', Rule::in(self::STATUSES)],
            'planned_start_date' => ['nullable', 'date'], 'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'actual_start_date' => ['nullable', 'date'], 'actual_end_date' => ['nullable', 'date', 'after_or_equal:actual_start_date'],
            'department_id' => ['nullable', 'integer', $in('departments')],
            'strategic_objective_ids' => ['nullable', 'array'], 'strategic_objective_ids.*' => ['integer', 'distinct', $in('strategic_objectives')],
            'owner_id' => ['nullable', 'integer', $in('users')],
            'is_on_timeline' => ['required', 'boolean'], 'year' => ['required', 'integer', 'between:2000,2100'], 'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** Pulls strategic_objective_ids out of the validated data; the first one is kept on projects.strategic_objective_id as the primary objective. */
    private function objectiveIds(Request $request, array &$data): ?array
    {
        $ids = $request->has('strategic_objective_ids') ? array_values($data['strategic_objective_ids'] ?? []) : null;
        unset($data['strategic_objective_ids']);
        if ($ids !== null) {
            $data['strategic_objective_id'] = $ids[0] ?? null;
        }

        return $ids;
    }

    private function syncObjectives(Project $p, ?array $ids): void
    {
        if ($ids === null) {
            return;
        }
        DB::table('project_strategic_objective')->where('project_id', $p->id)->delete();
        $now = now();
        DB::table('project_strategic_objective')->insert(array_map(
            fn ($oid) => ['project_id' => $p->id, 'strategic_objective_id' => $oid, 'created_at' => $now, 'updated_at' => $now], $ids
        ));
    }

    /** project id => list of its strategic objectives. */
    private function objectivesFor(Collection $projects): array
    {
        if ($projects->isEmpty()) {
            return [];
        }

        return DB::table('project_strategic_objective as ps')
            ->join('strategic_objectives as o', 'o.id', '=', 'ps.strategic_objective_id')
            ->whereIn('ps.project_id', $projects->pluck('id'))->orderBy('ps.id')
            ->get(['ps.project_id', 'o.id', 'o.code', 'o.name', 'o.name_ar'])
            ->groupBy('project_id')
            ->map(fn ($g) => $g->map(fn ($o) => ['id' => $o->id, 'code' => $o->code, 'name' => $o->name, 'name_ar' => $o->name_ar])->values()->all())
            ->all();
    }

    /** A project the user may not see is a 404, the same as another company's. */
    private function find(Request $request, int $id): Project
    {
        $p = Project::with(self::WITH)->where('company_id', $this->companyId($request))->findOrFail($id);
        abort_unless($this->access->canSee($request->user(), $p->department_id, $p->owner_id), 404);

        return $p;
    }

    /** Changing a project needs the admin/manager role in that project's department (no department: unrestricted editors only). */
    private function guard(Request $request, $departmentId): void
    {
        abort_unless(
            $this->access->canManage($request->user(), $departmentId === null ? null : (int) $departmentId),
            403, 'You can only manage projects in your own departments.'
        );
    }

    private function authorizeWrite(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'manager']), 403, 'You do not have permission to modify this resource.');
    }

    private function shape(Project $p, array $objectives = []): array
    {
        return array_merge(
            $p->only(['id', 'department_id', 'strategic_objective_id', 'owner_id', 'code', 'name', 'name_ar', 'description', 'description_ar', 'type', 'status', 'year', 'is_active']),
            [
                'planned_start_date' => $p->planned_start_date?->toDateString(), 'planned_end_date' => $p->planned_end_date?->toDateString(),
                'actual_start_date' => $p->actual_start_date?->toDateString(), 'actual_end_date' => $p->actual_end_date?->toDateString(),
                'completion_pct' => (float) $p->completion_pct, 'is_on_timeline' => (bool) $p->is_on_timeline,
                'department' => $p->department?->only(['id', 'name', 'name_ar']),
                'objectives' => $objectives[$p->id] ?? [],
                'owner' => $p->owner?->only(['id', 'name']),
            ]
        );
    }
}
