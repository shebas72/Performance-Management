<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\PlanLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** The signed-in user's own company settings. Reading is open to every member, changing is admin-only. */
class CompanyController extends Controller
{
    use ResolvesTenant;

    public function __construct(private PlanLimits $limits) {}

    public function show(Request $request)
    {
        return response()->json(['data' => $this->shape($this->company($request))]);
    }

    public function update(Request $request)
    {
        $c = $this->admin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'name_ar' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'timezone:all'], 'default_language' => ['nullable', 'in:en,ar'],
            'arabic_enabled' => ['sometimes', 'boolean'],
            'team_report_visibility' => ['sometimes', 'in:colleagues,own'],
            // The month the fiscal year starts in, stored as MM-DD. Fiscal years start on the 1st.
            'fiscal_year_start' => ['sometimes', 'required', 'regex:/^(0[1-9]|1[0-2])-01$/'],
        ]);

        // Arabic is a switch: off means English only (language switcher, Arabic fields, PDFs). Turning it on needs the plan's Arabic feature.
        $arabic = array_key_exists('arabic_enabled', $data) ? (bool) $data['arabic_enabled'] : (bool) $c->arabic_enabled;
        if ($arabic && ! $c->arabic_enabled) $this->limits->assertFeature($c->id, 'arabic');
        abort_if(! $arabic && ($data['default_language'] ?? null) === 'ar', 422, 'Turn Arabic on before making it the default language.');
        if ($arabic && ($data['default_language'] ?? null) === 'ar') $this->limits->assertFeature($c->id, 'arabic');
        if (! $arabic && (($data['default_language'] ?? $c->default_language) === 'ar')) $data['default_language'] = 'en'; // switching Arabic off

        $c->forceFill($data)->save();

        return response()->json(['data' => $this->shape($c)]);
    }

    /** Plan name, limits with current use, and feature flags. Admin-only (it shows how close the company is to its limits). */
    public function plan(Request $request)
    {
        $c = $this->admin($request);

        return response()->json(['data' => $this->limits->summary($c->id)]);
    }

    public function uploadLogo(Request $request)
    {
        $c = $this->admin($request);
        // SVG is not accepted: it can carry scripts.
        $request->validate(['logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048']]);

        $path = $request->file('logo')->store("logos/{$c->id}", 'public');
        if ($c->logo) {
            Storage::disk('public')->delete($c->logo);
        }
        $c->forceFill(['logo' => $path])->save();

        return response()->json(['data' => $this->shape($c)]);
    }

    public function removeLogo(Request $request)
    {
        $c = $this->admin($request);
        if ($c->logo) {
            Storage::disk('public')->delete($c->logo);
            $c->forceFill(['logo' => null])->save();
        }

        return response()->json(['data' => $this->shape($c)]);
    }

    private function company(Request $request): Company
    {
        return Company::findOrFail($this->companyId($request));
    }

    private function admin(Request $request): Company
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403, 'You do not have permission to change the settings.');

        return $this->company($request);
    }

    private function shape(Company $c): array
    {
        return [
            'id' => $c->id, 'name' => $c->name, 'name_ar' => $c->name_ar, 'timezone' => $c->timezone, 'default_language' => $c->default_language, 'arabic_enabled' => (bool) $c->arabic_enabled,
            'logo_url' => $c->logo ? Storage::disk('public')->url($c->logo) : null,
            'team_report_visibility' => $c->team_report_visibility ?? 'colleagues',
            'fiscal_year_start' => $c->fiscal_year_start ?: '01-01',
        ];
    }
}
