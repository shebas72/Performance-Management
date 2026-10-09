<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Kpi;
use App\Models\KpiEntry;
use App\Services\KpiChangeLogger;
use App\Services\KpiEntryService;
use App\Services\SnapshotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KpiEntryController extends Controller
{
    use ResolvesTenant;

    private const KPI_FIELDS = 'kpi:id,code,name,name_ar,unit,value_type,direction';

    public function __construct(
        private KpiEntryService $entries,
        private SnapshotService $snapshots,
        private KpiChangeLogger $changes,
    ) {}

    public function index(Request $request)
    {
        $cid = $this->companyId($request);

        $request->validate([
            'kpi_id'        => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'year'          => ['nullable', 'integer'],
            'month'         => ['nullable', 'integer', 'between:1,12'],
            'status'        => ['nullable', Rule::in(['on_track', 'at_risk', 'behind', 'not_entered'])],
            'data_status'   => ['nullable', Rule::in(['complete', 'incomplete'])],
            'per_page'      => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $q = KpiEntry::with(self::KPI_FIELDS)->where('company_id', $cid);

        foreach (['kpi_id', 'year', 'month', 'status', 'data_status'] as $f) {
            if ($request->filled($f)) $q->where($f, $request->input($f));
        }
        if ($request->filled('department_id')) {
            $q->whereIn('kpi_id', Kpi::withoutGlobalScopes()
                ->where('company_id', $cid)
                ->where('department_id', $request->input('department_id'))
                ->select('id'));
        }

        return $q->orderBy('year')->orderBy('month')->orderBy('kpi_id')
            ->paginate((int) $request->input('per_page', 50));
    }

    public function show(Request $request, int $kpi_entry)
    {
        $entry = KpiEntry::with(self::KPI_FIELDS)
            ->where('company_id', $this->companyId($request))
            ->findOrFail($kpi_entry);

        return response()->json(['data' => $entry]);
    }

    /** Create-or-update the entry for (kpi_id, year, month). */
    public function store(Request $request)
    {
        $this->authorizeWrite($request);
        $cid = $this->companyId($request);

        $data = $request->validate([
            'kpi_id' => ['required', 'integer', Rule::exists('kpis', 'id')->where('company_id', $cid)],
            'year'   => ['required', 'integer', 'between:2000,2100'],
            'month'  => ['required', 'integer', 'between:1,12'],
        ] + $this->valueRules());

        $kpi   = Kpi::withoutGlobalScopes()->where('company_id', $cid)->findOrFail($data['kpi_id']);
        $entry = $this->entries->save($kpi, $request->user(), (int) $data['year'], (int) $data['month'], $data);

        $this->snapshots->refreshMonth($cid, (int) $entry->year, (int) $entry->month);

        return response()->json(['data' => $entry->load(self::KPI_FIELDS)], $entry->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, int $kpi_entry)
    {
        $this->authorizeWrite($request);
        $cid   = $this->companyId($request);
        $entry = KpiEntry::where('company_id', $cid)->findOrFail($kpi_entry);

        $data = $request->validate($this->valueRules());

        // Typing a value means the data is complete; clearing it means incomplete (reason required).
        if (array_key_exists('actual_value', $data) && ! array_key_exists('data_status', $data)) {
            $data['data_status'] = $data['actual_value'] === null ? 'incomplete' : 'complete';
        }
        $merged = array_merge($entry->only(['actual_value', 'note', 'note_ar', 'data_status', 'incomplete_reason']), $data);

        $kpi   = Kpi::withoutGlobalScopes()->where('company_id', $cid)->findOrFail($entry->kpi_id);
        $saved = $this->entries->save($kpi, $request->user(), (int) $entry->year, (int) $entry->month, $merged);

        $this->snapshots->refreshMonth($cid, (int) $saved->year, (int) $saved->month);

        return response()->json(['data' => $saved->load(self::KPI_FIELDS)]);
    }

    public function destroy(Request $request, int $kpi_entry)
    {
        $this->authorizeWrite($request);
        $cid   = $this->companyId($request);
        $entry = KpiEntry::where('company_id', $cid)->findOrFail($kpi_entry);
        [$year, $month] = [(int) $entry->year, (int) $entry->month];

        $entry->delete();
        $this->changes->entryDeleted($entry, $request->user()->id);
        $this->snapshots->refreshMonth($cid, $year, $month);

        return response()->noContent();
    }

    /** Enter a whole month at once. All-or-nothing: one bad row rolls everything back (422 names the row). */
    public function bulk(Request $request)
    {
        $this->authorizeWrite($request);
        $cid = $this->companyId($request);

        $v = $request->validate([
            'year'                           => ['required', 'integer', 'between:2000,2100'],
            'month'                          => ['required', 'integer', 'between:1,12'],
            'entries'                        => ['required', 'array', 'min:1', 'max:500'],
            'entries.*.kpi_id'               => ['required', 'integer', 'distinct', Rule::exists('kpis', 'id')->where('company_id', $cid)],
            'entries.*.actual_value'         => ['nullable', 'numeric'],
            'entries.*.note'                 => ['nullable', 'string'],
            'entries.*.note_ar'              => ['nullable', 'string'],
            'entries.*.data_status'          => ['nullable', Rule::in(['complete', 'incomplete'])],
            'entries.*.incomplete_reason'    => ['nullable', Rule::in(['unavailable', 'not_recorded', 'cooperation_issue', 'other'])],
        ]);

        [$year, $month] = [(int) $v['year'], (int) $v['month']];
        $kpis = Kpi::withoutGlobalScopes()->where('company_id', $cid)
            ->whereIn('id', collect($v['entries'])->pluck('kpi_id'))->get()->keyBy('id');

        $saved = DB::transaction(function () use ($v, $kpis, $request, $year, $month) {
            $out = [];
            foreach ($v['entries'] as $i => $row) {
                $out[] = $this->entries->save($kpis[$row['kpi_id']], $request->user(), $year, $month, $row, "entries.$i.");
            }
            return $out;
        });

        $this->snapshots->refreshMonth($cid, $year, $month);

        return response()->json(['data' => $saved, 'meta' => ['saved' => count($saved)]], 201);
    }

    private function valueRules(): array
    {
        return [
            'actual_value'      => ['nullable', 'numeric'],
            'note'              => ['nullable', 'string'],
            'note_ar'           => ['nullable', 'string'],
            'data_status'       => ['nullable', Rule::in(['complete', 'incomplete'])],
            'incomplete_reason' => ['nullable', Rule::in(['unavailable', 'not_recorded', 'cooperation_issue', 'other'])],
        ];
    }
}
