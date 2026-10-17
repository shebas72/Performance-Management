<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\AccessScope;
use App\Services\Limits;
use App\Support\AppMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        if (! (bool) ($user->is_active ?? true)) {
            throw ValidationException::withMessages(['email' => ['This account has been deactivated. Contact your company admin.']]);
        }

        // SaaS: email not verified yet, organisation awaiting approval, rejected or suspended.
if ($reason = \App\Support\LoginGate::reason($user)) {
    return response()->json(['message' => $reason, 'code' => 'login_blocked'], 403);
}

        // SaaS: email not verified yet, organisation awaiting approval, rejected or suspended.
        if ($reason = \App\Support\LoginGate::reason($user)) {
            throw ValidationException::withMessages(['email' => [$reason]]);
        }

        return response()->json($this->payload($user, $user->createToken('spa')->plainTextToken));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return response()->json($this->payload($request->user()));
    }

    public function payload(User $user, ?string $token = null): array
    {
        $company = $user->company_id ? Company::find($user->company_id) : null;

        return array_filter([
            'token' => $token,
            'mode'  => AppMode::current(),
            'user'  => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'company_id' => $user->company_id,
                'roles'      => [\App\Services\RolePermissions::resolve()->keyFor($user)],
                'role'       => $user->company_id ? \App\Services\RolePermissions::resolve()->roleInfo($user) : null,
                'permissions' => \App\Services\RolePermissions::resolve()->permissionsFor($user),
                // Department ids the user can see / edit (including sub-departments); null = all of them.
                'access'     => $user->company_id ? [
                    'scope'               => $user->access_scope ?? 'all',
                    'view_department_ids' => app(AccessScope::class)->viewIds($user),
                    'edit_department_ids' => app(AccessScope::class)->editIds($user),
                ] : null,
            ],
            'company' => $company ? [
                'id' => $company->id, 'name' => $company->name, 'name_ar' => $company->name_ar,
                'logo_url' => $company->logo ? Storage::disk('public')->url($company->logo) : null,
                'features' => app(Limits::class)->features($company->id),
                'languages' => $company->enabledLanguages(),
            ] : null,
        ], fn ($v) => $v !== null);
    }

    /** Languages the sign-in page may offer: standalone = that company's setting; SaaS = both, as the company is unknown until sign-in. */
    public function languages()
    {
        $langs = AppMode::saas() ? ['en', 'ar'] : (\App\Models\Company::query()->orderBy('id')->first()?->enabledLanguages() ?? ['en']);

        // "registration" tells the sign-in page whether to offer a Create account link (SaaS only).
        return response()->json(['data' => ['languages' => $langs, 'mode' => AppMode::current(), 'registration' => AppMode::saas()]]);
    }
}
