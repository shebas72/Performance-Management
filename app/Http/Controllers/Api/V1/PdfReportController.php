<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\Kpi;
use App\Models\KpiChangeLog;
use App\Models\KpiEntry;
use App\Models\KpiTarget;
use App\Services\AccessScope;
use App\Services\KpiCalculator;
use App\Services\PlanLimits;
use App\Services\Pdf\PdfContext;
use App\Services\Pdf\PdfRenderer;
use App\Services\TeamPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * GET /reports/pdf/{dashboard|objectives|departments|team-performance|kpis/{id}}?year=&month=&lang=en|ar
 *
 * The routes sit behind RequirePlanFeature:pdf_export. Each report is built from the same code as its on-screen page
 * (the dashboard controllers, TeamPerformanceService), so a user's department scope and the team-report visibility rules
 * apply to the PDF exactly as they do on screen.
 */
class PdfReportController extends Controller
{
    use ResolvesTenant;

    public function __construct(
        private PdfRenderer $renderer,
        private PlanLimits $limits,
        private AccessScope $access,
        private KpiCalculator $calc,
    ) {}

    public function dashboard(Request $request, DashboardController $dash)
    {
        $d = $dash->index($request)->getData(true)['data'];
        $p = $this->context($request);

        return $this->pdf($p, 'pdf.dashboard', ['d' => $d, 'depts' => $this->departmentNames($request)], 'dashboard', $d['period']['year'], $d['period']['month']);
    }

    public function objectives(Request $request, DashboardController $dash)
    {
        $request->merge(['series' => 1]); // the endpoint returns the company overall only together with the monthly series
        $json = $dash->objectives($request)->getData(true);
        $p = $this->context($request);
        $meta = $json['meta'];
        $overall = ['current' => $json['overall']['current'], 'ytd' => $json['overall']['ytd']];

        return $this->pdf($p, 'pdf.objectives', ['objectives' => $json['data'], 'overall' => $overall, 'meta' => $meta], 'objectives', $meta['year'], $meta['month']);
    }

    public function departments(Request $request, DashboardController $dash)
    {
        $json = $dash->departments($request)->getData(true);
        $p = $this->context($request);

        return $this->pdf($p, 'pdf.departments', ['departments' => $json['data'], 'meta' => $json['meta']], 'departments', $json['meta']['year'], $json['meta']['month']);
    }

    public function team(Request $request, TeamPerformanceService $team)
    {
        [$year, $month] = $this->period($request);
        $t = $team->build($request->user(), $year, $month);
        $p = $this->context($request);

        return $this->pdf($p, 'pdf.team', ['t' => $t], 'team-performance', $year, $month, 'landscape');
    }

    public function kpi(Request $request, int $id)
    {
        $request->validate(['year' => ['nullable', 'integer'], 'month' => ['nullable', 'integer', 'between:1,12']]);
        $cid = $this->companyId($request);

        $kpi = Kpi::withoutGlobalScopes()
            ->with(['department:id,name,name_ar', 'owner:id,name', 'strategicObjective:id,code,name,name_ar', 'perspective:id,name,name_ar', 'creator:id,name'])
            ->where('company_id', $cid)->findOrFail($id);
        abort_unless($this->access->canView($request->user(), $kpi->department_id === null ? null : (int) $kpi->department_id), 404);

        $targets = KpiTarget::withoutGlobalScopes()->where('company_id', $cid)->where('kpi_id', $kpi->id)->where('year', $kpi->year)->get()->keyBy('month');
        $entries = KpiEntry::where('company_id', $cid)->where('kpi_id', $kpi->id)->where('year', $kpi->year)->get()->keyBy('month');

        $cap  = $this->calc->scoreCap();
        $rows = [];
        for ($m = 1; $m <= 12; $m++) {
            $e = $entries->get($m);
            $pct = $e && $e->achievement_pct !== null ? (float) $e->achievement_pct : null;
            $score = $pct === null ? null : round(min($pct, $cap), 2);
            $rows[] = [
                'month' => $m,
                'target' => $targets->get($m)?->target_value,
                'actual' => $e?->actual_value,
                'achievement' => $pct,
                'score' => $score,
                'status' => $score === null ? 'not_entered' : $this->calc->status($kpi, $score),
                'incomplete_reason' => $e && $e->data_status === 'incomplete' ? ($e->incomplete_reason ?: '…') : null,
                'note' => $e?->note,
            ];
        }
        $p = $this->context($request);
        if ($p->rtl) {
            foreach ($rows as &$r) {
                $e = $entries->get($r['month']);
                if ($e && filled($e->note_ar)) $r['note'] = $e->note_ar;
            }
            unset($r);
        }

        // Month shown in the headline tile: ?month=, else the last month with a score.
        $last = collect($rows)->filter(fn ($r) => $r['score'] !== null)->max('month') ?: 1;
        $month = $request->integer('month') ?: $last;
        $upto = collect($rows)->filter(fn ($r) => $r['month'] <= $month && $r['score'] !== null);
        $ytd = $upto->isEmpty() ? null : round($upto->avg('score'), 2);
        $cur = $rows[$month - 1];

        $history = KpiChangeLog::with('user:id,name')->where('company_id', $cid)->where('kpi_id', $kpi->id)
            ->orderByDesc('id')->limit(25)->get()->map(fn ($h) => [
                'action' => $h->action, 'field' => $h->field, 'year' => $h->period_year, 'month' => $h->period_month,
                'old' => $h->old_value, 'new' => $h->new_value, 'user' => $h->user?->name, 'at' => $h->created_at?->toIso8601String(),
            ])->all();

        $k = [
            'code' => $kpi->code, 'year' => $kpi->year, 'name' => $kpi->name, 'name_ar' => $kpi->name_ar,
            'description' => $kpi->description, 'description_ar' => $kpi->description_ar,
            'type' => $kpi->type, 'frequency' => $kpi->frequency, 'direction' => $kpi->direction, 'value_type' => $kpi->value_type,
            'unit' => $kpi->unit, 'weight' => $kpi->weight, 'annual_target' => $kpi->annual_target,
            'threshold_red' => $kpi->threshold_red, 'threshold_yellow' => $kpi->threshold_yellow,
            'department' => $kpi->department?->only(['name', 'name_ar']),
            'owner' => $kpi->owner?->name,
            'objective' => $kpi->strategicObjective?->only(['code', 'name', 'name_ar']),
            'perspective' => $kpi->perspective?->only(['name', 'name_ar']),
        ];

        return $this->pdf($p, 'pdf.kpi', [
            'k' => $k, 'rows' => $rows, 'history' => $history,
            'creator' => $kpi->created_by ? ['name' => $kpi->creator?->name ?? '—', 'at' => $kpi->created_at?->toIso8601String()] : null,
            'summary' => [
                'month' => $month, 'score' => $cur['score'], 'status' => $cur['status'],
                'ytd' => $ytd, 'ytd_status' => $ytd === null ? 'not_entered' : $this->calc->statusForScore($ytd),
            ],
        ], 'kpi-'.preg_replace('/[^A-Za-z0-9_-]+/', '', $kpi->code ?: (string) $kpi->id), $kpi->year, $month);
    }

    // ---- helpers -------------------------------------------------------------------------------------------------

    private function departmentNames(Request $request): array
    {
        return Department::withoutGlobalScopes()->where('company_id', $this->companyId($request))
            ->get(['id', 'name', 'name_ar'])->keyBy('id')->map->only(['name', 'name_ar'])->all();
    }

    /** Same defaults as the dashboard: the company's latest KPI year and that year's latest entered month. */
    private function period(Request $request): array
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'month' => ['nullable', 'integer', 'between:1,12']]);
        $cid = $this->companyId($request);

        $year = $request->integer('year')
            ?: (int) Kpi::withoutGlobalScopes()->where('company_id', $cid)->max('year')
            ?: (int) date('Y');
        $month = $request->integer('month')
            ?: (int) KpiEntry::where('company_id', $cid)->where('year', $year)->max('month')
            ?: (int) date('n');

        return [$year, $month];
    }

    /** Language: ?lang=, else the company default. Arabic needs the plan's Arabic feature. */
    private function context(Request $request): PdfContext
    {
        $request->validate(['lang' => ['nullable', 'in:en,ar']]);
        $company = Company::findOrFail($this->companyId($request));

        $lang = $request->input('lang') ?: ($company->default_language === 'ar' ? 'ar' : 'en');
        if ($lang === 'ar' && ! $this->limits->feature((int) $company->id, 'arabic')) $lang = 'en';

        $name = $lang === 'ar' && filled($company->name_ar) ? $company->name_ar : $company->name;

        return new PdfContext($lang, ['name' => $name, 'logo' => $this->logo($company)], now($company->timezone ?: config('app.timezone'))->format('Y-m-d'));
    }

    /** The logo as a PNG data URI (DomPDF cannot read WebP, and a data URI needs no file access). Null if it cannot be read. */
    private function logo(Company $company): ?string
    {
        try {
            if (! $company->logo || ! Storage::disk('public')->exists($company->logo) || ! function_exists('imagecreatefromstring')) return null;

            $img = @imagecreatefromstring(Storage::disk('public')->get($company->logo));
            if (! $img) return null;
            imagealphablending($img, false);
            imagesavealpha($img, true);
            ob_start();
            imagepng($img);
            $png = ob_get_clean();
            imagedestroy($img);

            return 'data:image/png;base64,'.base64_encode($png);
        } catch (\Throwable) {
            return null;
        }
    }

    private function pdf(PdfContext $p, string $view, array $data, string $name, int $year, int $month, string $orientation = 'portrait')
    {
        $bytes = $this->renderer->render($view, $data, $p, $orientation);
        $file  = sprintf('spms-%s-%d-%02d.pdf', $name, $year, $month);

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$file.'"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
