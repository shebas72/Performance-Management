<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\CompanyRole;
use App\Models\User;
use App\Services\PlanLimits;
use App\Services\RolePermissions;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Roles and their permission matrix, per company. Needs team.manage.
 * Writing needs the plan's custom_roles feature (route middleware). The admin role is locked: it always has every permission.
 */
class RoleController extends Controller
{
    use ResolvesTenant;

    public function __construct(private PlanLimits $limits) {}

    public function index(Request $request)
    {
        $cid = $this->authorizeRoles($request);
        $roles = RolePermissions::resolve();
        $roles->ensureDefaults($cid);

        $counts = User::where('company_id', $cid)->where('is_super_admin', false)->with('roles:id,name')->get()
            ->countBy(fn (User $u) => $roles->keyFor($u));

        $rows = CompanyRole::where('company_id', $cid)->orderByDesc('is_system')->orderBy('id')->get()
            ->sortBy(fn ($r) => $r->is_system ? array_search($r->key, array_keys(Permissions::SYSTEM), true) : 100 + $r->id)->values();

        return response()->json([
            'data' => $rows->map(fn ($r) => $this->shape($r, (int) ($counts[$r->key] ?? 0)))->values(),
            'meta' => ['groups' => Permissions::GROUPS, 'can_customize' => $this->limits->feature($cid, 'custom_roles')],
        ]);
    }

    public function store(Request $request)
    {
        $cid = $this->authorizeRoles($request);
        RolePermissions::resolve()->ensureDefaults($cid);
        $data = $this->validated($request, $cid);

        $role = CompanyRole::create([
            'company_id' => $cid, 'key' => 'role-'.Str::lower(Str::random(8)), 'name' => $data['name'], 'name_ar' => $data['name_ar'] ?? null,
            'permissions' => array_values($data['permissions'] ?? []), 'is_system' => false,
        ]);
        RolePermissions::resolve()->flush();

        return response()->json(['data' => $this->shape($role, 0)], 201);
    }

    public function update(Request $request, int $id)
    {
        $cid  = $this->authorizeRoles($request);
        $role = CompanyRole::where('company_id', $cid)->findOrFail($id);
        $data = $this->validated($request, $cid, $role);

        $role->name = $data['name'];
        $role->name_ar = $data['name_ar'] ?? null;
        if ($role->key !== 'admin') $role->permissions = array_values($data['permissions'] ?? []); // the admin role keeps every permission
        $role->save();
        RolePermissions::resolve()->flush();

        return response()->json(['data' => $this->shape($role, $this->userCount($cid, $role->key))]);
    }

    public function destroy(Request $request, int $id)
    {
        $cid  = $this->authorizeRoles($request);
        $role = CompanyRole::where('company_id', $cid)->findOrFail($id);
        abort_if($role->is_system, 422, 'The built-in roles cannot be deleted.');
        abort_if($this->userCount($cid, $role->key) > 0, 409, 'Move the people in this role to another role first.');

        $role->delete();
        RolePermissions::resolve()->flush();

        return response()->noContent();
    }

    private function authorizeRoles(Request $request): int
    {
        abort_unless($request->user()->hasPermission('team.manage'), 403, 'You do not have permission to manage roles.');

        return $this->companyId($request);
    }

    private function validated(Request $request, int $cid, ?CompanyRole $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('company_roles', 'name')->where('company_id', $cid)->ignore($role?->id)],
            'name_ar' => ['nullable', 'string', 'max:60'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Permissions::all())],
        ]);
    }

    private function userCount(int $cid, string $key): int
    {
        $roles = RolePermissions::resolve();

        return User::where('company_id', $cid)->where('is_super_admin', false)->with('roles:id,name')->get()->filter(fn (User $u) => $roles->keyFor($u) === $key)->count();
    }

    private function shape(CompanyRole $r, int $users): array
    {
        return [
            'id' => $r->id, 'key' => $r->key, 'name' => $r->name, 'name_ar' => $r->name_ar, 'is_system' => (bool) $r->is_system,
            'locked' => $r->key === 'admin', 'users_count' => $users,
            'permissions' => $r->key === 'admin' ? Permissions::all() : array_values(array_intersect($r->permissions ?? [], Permissions::all())),
        ];
    }
}
