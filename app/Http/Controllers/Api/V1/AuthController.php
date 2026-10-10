<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\AccessScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * saas       -> anyone can register; creates a new company (tenant) + its admin.
     * standalone -> only allowed while no company exists (first-time setup).
     */
    public function register(Request $request)
    {
        $mode = config('spms.mode');

        if ($mode === 'standalone' && Company::query()->exists()) {
            abort(403, 'Registration is closed.');
        }

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $company = new Company();
            $company->forceFill([
    'name' => $data['company_name'],
    'slug' => $this->uniqueSlug($data['company_name']),
])->save();

            $user = new User();
            $user->forceFill([
                'company_id' => $company->id,
                'name'       => $data['name'],
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']),
            ])->save();

            Role::findOrCreate('admin');
            $user->assignRole('admin');

            return $user;
        });

        return response()->json($this->payload($user, $user->createToken('spa')->plainTextToken), 201);
    }

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

    private function uniqueSlug(string $name): string
    {
       $base = Str::slug($name) ?: 'company';
    $slug = $base;
    $i = 2;

         while (Company::where('slug', $slug)->exists()) {
        $slug = $base.'-'.$i++;
    }

        return $slug;
    }

    public function payload(User $user, ?string $token = null): array
    {
        $company = $user->company_id ? Company::find($user->company_id) : null;

        return array_filter([
            'token' => $token,
            'mode'  => config('spms.mode'),
            'user'  => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'company_id' => $user->company_id,
                'roles'      => $user->getRoleNames()->values(),
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
            ] : null,
        ], fn ($v) => $v !== null);
    }
}
