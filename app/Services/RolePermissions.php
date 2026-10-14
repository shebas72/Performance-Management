<?php

namespace App\Services;

use App\Models\CompanyRole;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Collection;

/**
 * Who may do what. A user's role is users.role_key (the Spatie role name for users created before custom roles existed).
 * The role's permissions come from the company's company_roles row; a built-in role with no row yet uses Permissions::DEFAULTS,
 * so nothing changes for a company until it edits a role. The admin role always has every permission.
 * Roles are cached per request: use resolve() to get the shared instance.
 */
final class RolePermissions
{
    private array $rows = [];   // company_id => Collection keyed by role key
    private array $byUser = []; // user_id => string[]

    public static function resolve(): self
    {
        if (app()->bound(self::class)) return app(self::class);

        $self = new self();
        app()->instance(self::class, $self);

        return $self;
    }

    public function flush(): void
    {
        $this->rows = [];
        $this->byUser = [];
    }

    public function keyFor(User $u): string
    {
        return $u->role_key ?: ($u->roles->pluck('name')->first() ?: 'viewer');
    }

    /** @return string[] */
    public function permissionsFor(User $u): array
    {
        if (array_key_exists($u->id, $this->byUser)) return $this->byUser[$u->id];

        if ($u->is_super_admin || ! $u->company_id) return $this->byUser[$u->id] = Permissions::all();

        $key = $this->keyFor($u);
        if ($key === 'admin') return $this->byUser[$u->id] = Permissions::all();

        $row = $this->rows((int) $u->company_id)->get($key);
        $perms = $row ? ($row->permissions ?? []) : Permissions::defaultsFor($key);

        return $this->byUser[$u->id] = array_values(array_intersect($perms, Permissions::all()));
    }

    public function allows(User $u, string $permission): bool
    {
        return in_array($permission, $this->permissionsFor($u), true);
    }

    /** Built-in role keys first, then the company's custom roles. */
    public function keys(int $companyId): array
    {
        return array_values(array_unique(array_merge(array_keys(Permissions::SYSTEM), $this->rows($companyId)->keys()->all())));
    }

    /** @return array<string, array{name: string, name_ar: ?string}> */
    public function labels(int $companyId): array
    {
        $out = [];
        foreach ($this->keys($companyId) as $key) $out[$key] = $this->label($companyId, $key);

        return $out;
    }

    public function label(?int $companyId, string $key): array
    {
        $row = $companyId ? $this->rows($companyId)->get($key) : null;
        if ($row) return ['name' => $row->name, 'name_ar' => $row->name_ar];

        [$en, $ar] = Permissions::SYSTEM[$key] ?? [$key, null];

        return ['name' => $en, 'name_ar' => $ar];
    }

    /** What the SPA needs about a user's role. */
    public function roleInfo(User $u): array
    {
        $key = $this->keyFor($u);

        return ['key' => $key] + $this->label($u->company_id ? (int) $u->company_id : null, $key);
    }

    /** Creates the four built-in role rows for a company if they are missing (so they show up in the matrix and can be edited). */
    public function ensureDefaults(int $companyId): void
    {
        $have = $this->rows($companyId)->keys()->all();
        foreach (Permissions::SYSTEM as $key => [$en, $ar]) {
            if (in_array($key, $have, true)) continue;
            CompanyRole::create(['company_id' => $companyId, 'key' => $key, 'name' => $en, 'name_ar' => $ar, 'permissions' => Permissions::defaultsFor($key), 'is_system' => true]);
        }
        $this->flush();
    }

    private function rows(int $companyId): Collection
    {
        return $this->rows[$companyId] ??= CompanyRole::where('company_id', $companyId)->get()->keyBy('key');
    }
}
