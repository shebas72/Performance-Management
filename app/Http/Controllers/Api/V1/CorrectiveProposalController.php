<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\CorrectiveProposal;
use App\Models\Kpi;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Corrective proposals for under-performing KPIs.
 * Any user can propose; admins/managers review (approve -> in_progress, reject, complete).
 * The model has no tenant scope, so every query below filters on company_id explicitly.
 */
class CorrectiveProposalController extends Controller
{
    use ResolvesTenant;

    private const ROOT_CAUSES = ['resources_shortage', 'technical_challenges', 'administrative', 'lack_of_followup', 'other'];

    public function index(Request $request)
    {
        $request->validate([
            'year'   => ['nullable', 'integer'],
            'month'  => ['nullable', 'integer', 'between:1,12'],
            'kpi_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'completed', 'rejected'])],
        ]);

        $rows = CorrectiveProposal::with(['kpi:id,code,name,name_ar', 'submitter:id,name', 'reviewer:id,name'])
            ->where('company_id', $this->companyId($request))
            ->when($request->filled('year'), fn ($q) => $q->where('year', $request->integer('year')))
            ->when($request->filled('month'), fn ($q) => $q->where('month', $request->integer('month')))
            ->when($request->filled('kpi_id'), fn ($q) => $q->where('kpi_id', $request->integer('kpi_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('id')->limit(500)->get();

        return response()->json(['data' => $rows->map(fn ($p) => $this->shape($p))->values()]);
    }

    public function store(Request $request)
    {
        $cid  = $this->companyId($request);
        $data = $request->validate($this->rules($cid));
        $kpi  = Kpi::where('company_id', $cid)->findOrFail($data['kpi_id']);

        $p = new CorrectiveProposal();
        $p->forceFill($data + [
            'company_id'    => $cid,
            'department_id' => $kpi->department_id,
            'submitted_by'  => $request->user()->id,
            'status'        => 'pending',
        ])->save();

        return response()->json(['data' => $this->shape($p->load(['kpi:id,code,name,name_ar', 'submitter:id,name', 'reviewer:id,name']))], 201);
    }

    public function update(Request $request, int $id)
    {
        $p = $this->find($request, $id);
        $this->authorizeModify($request, $p);

        // The KPI and period of a proposal are fixed once it is created.
        $data = $request->validate(Arr::except($this->rules($p->company_id), ['kpi_id', 'year', 'month']));
        $p->forceFill($data)->save();

        return response()->json(['data' => $this->shape($p->load(['kpi:id,code,name,name_ar', 'submitter:id,name', 'reviewer:id,name']))]);
    }

    public function destroy(Request $request, int $id)
    {
        $p = $this->find($request, $id);
        $this->authorizeModify($request, $p);
        $p->delete();

        return response()->noContent();
    }

    /** POST {action: approve|reject|complete}. approve/reject only from pending, complete only from in_progress. */
    public function review(Request $request, int $id)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'manager']), 403, 'You do not have permission to review proposals.');

        $action = $request->validate(['action' => ['required', Rule::in(['approve', 'reject', 'complete'])]])['action'];
        $p      = $this->find($request, $id);

        $from = ['approve' => 'pending', 'reject' => 'pending', 'complete' => 'in_progress'][$action];
        abort_unless($p->status === $from, 422, 'This action is not available for the proposal\'s current status.');

        $p->forceFill([
            'status'      => ['approve' => 'in_progress', 'reject' => 'rejected', 'complete' => 'completed'][$action],
            'reviewed_by' => $request->user()->id,
        ])->save();

        return response()->json(['data' => $this->shape($p->load(['kpi:id,code,name,name_ar', 'submitter:id,name', 'reviewer:id,name']))]);
    }

    private function rules(int $companyId): array
    {
        return [
            'kpi_id'            => ['required', 'integer', Rule::exists('kpis', 'id')->where('company_id', $companyId)],
            'title'             => ['required', 'string', 'max:255'],
            'title_ar'          => ['nullable', 'string', 'max:255'],
            'description'       => ['required', 'string'],
            'description_ar'    => ['nullable', 'string'],
            'root_cause'        => ['required', Rule::in(self::ROOT_CAUSES)],
            'root_cause_detail' => ['nullable', 'string'],
            'due_date'          => ['nullable', 'date'],
            'year'              => ['required', 'integer', 'between:2000,2100'],
            'month'             => ['nullable', 'integer', 'between:1,12'],
        ];
    }

    private function find(Request $request, int $id): CorrectiveProposal
    {
        return CorrectiveProposal::where('company_id', $this->companyId($request))->findOrFail($id);
    }

    /** Admins/managers can change any proposal; the submitter only while it is still pending. */
    private function authorizeModify(Request $request, CorrectiveProposal $p): void
    {
        $u = $request->user();
        abort_unless(
            $u->hasAnyRole(['admin', 'manager']) || ($p->submitted_by === $u->id && $p->status === 'pending'),
            403, 'You do not have permission to modify this proposal.'
        );
    }

    private function shape(CorrectiveProposal $p): array
    {
        return array_merge(
            $p->only(['id', 'kpi_id', 'department_id', 'submitted_by', 'reviewed_by', 'title', 'title_ar', 'description', 'description_ar',
                'root_cause', 'root_cause_detail', 'status', 'year', 'month']),
            [
                'due_date'  => $p->due_date?->toDateString(),
                'kpi'       => $p->kpi?->only(['id', 'code', 'name', 'name_ar']),
                'submitter' => $p->submitter?->only(['id', 'name']),
                'reviewer'  => $p->reviewer?->only(['id', 'name']),
            ]
        );
    }
}
