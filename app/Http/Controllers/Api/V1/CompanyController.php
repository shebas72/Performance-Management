<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** The signed-in user's own company settings. Reading is open to every member, changing is admin-only. */
class CompanyController extends Controller
{
    use ResolvesTenant;

    public function show(Request $request)
    {
        return response()->json(['data' => $this->shape($this->company($request))]);
    }

    public function update(Request $request)
    {
        $c = $this->admin($request);
        $c->forceFill($request->validate([
            'name' => ['required', 'string', 'max:255'], 'name_ar' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'timezone:all'], 'default_language' => ['nullable', 'in:en,ar'],
            'team_report_visibility' => ['sometimes', 'in:colleagues,own'],
        ]))->save();

        return response()->json(['data' => $this->shape($c)]);
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
        abort_unless($request->user()->hasRole('admin'), 403, 'Only company admins can change the settings.');

        return $this->company($request);
    }

    private function shape(Company $c): array
    {
        return [
            'id' => $c->id, 'name' => $c->name, 'name_ar' => $c->name_ar, 'timezone' => $c->timezone, 'default_language' => $c->default_language,
            'logo_url' => $c->logo ? Storage::disk('public')->url($c->logo) : null,
            'team_report_visibility' => $c->team_report_visibility ?? 'colleagues',
        ];
    }
}
