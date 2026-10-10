<?php

namespace App\Services;

use App\Models\Kpi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Which departments a user may see and edit. A department always includes its sub-departments.
 *
 * SEE  : admin or access_scope "all" -> everything; "own" -> the user's own departments; "selected" -> the chosen list.
 * EDIT : admin -> everything; others -> their own departments. A user with no own departments edits whatever they can see,
 *        so users who existed before this feature keep working until the admin assigns departments.
 * Returned id lists are null when unrestricted.
 */
class AccessScope
{
    private array $view = [];
    private array $edit = [];

    public function viewIds(User $u): ?array
    {
        if (array_key_exists($u->id, $this->view)) return $this->view[$u->id];

        if ($u->hasRole('admin') || ($u->access_scope ?? 'all') === 'all') return $this->view[$u->id] = null;

        $kind = $u->access_scope === 'selected' ? 'access' : 'member';

        return $this->view[$u->id] = $this->expand($u, $this->assigned($u, $kind));
    }

    public function editIds(User $u): ?array
    {
        if (array_key_exists($u->id, $this->edit)) return $this->edit[$u->id];

        if ($u->hasRole('admin')) return $this->edit[$u->id] = null;

        $members = $this->assigned($u, 'member');
        $view    = $this->viewIds($u);
        if (! $members) return $this->edit[$u->id] = $view;

        $own = $this->expand($u, $members);

        return $this->edit[$u->id] = $view === null ? $own : array_values(array_intersect($own, $view));
    }

    public function canView(User $u, ?int $departmentId): bool
    {
        $ids = $this->viewIds($u);

        return $ids === null || ($departmentId !== null && in_array($departmentId, $ids, true));
    }

    public function canEdit(User $u, ?int $departmentId): bool
    {
        $ids = $this->editIds($u);

        return $ids === null || ($departmentId !== null && in_array($departmentId, $ids, true));
    }

    /** Limit a query on a table with a department_id column. */
    public function scopeByDepartment($query, User $u, string $column = 'department_id')
    {
        $ids = $this->viewIds($u);

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    /** Limit a query on a table with a kpi_id column to the KPIs the user can see. */
    public function scopeByKpi($query, User $u, string $column = 'kpi_id')
    {
        $ids = $this->viewIds($u);
        if ($ids === null) return $query;

        return $query->whereIn($column, Kpi::withoutGlobalScopes()
            ->where('company_id', $u->company_id)->whereIn('department_id', $ids)->select('id'));
    }

    /**
     * Limit a query on a table that has a department column and an owner column (projects, initiatives, proposals).
     * Restricted users see their departments' items plus any item they own / submitted themselves.
     */
    public function scopeVisible($query, User $u, string $departmentColumn = 'department_id', string $ownColumn = 'owner_id')
    {
        $ids = $this->viewIds($u);
        if ($ids === null) return $query;

        return $query->where(function ($w) use ($ids, $u, $departmentColumn, $ownColumn) {
            $w->whereIn($departmentColumn, $ids)->orWhere($ownColumn, $u->id);
        });
    }

    /** Same rule as scopeVisible, for one loaded record. */
    public function canSee(User $u, ?int $departmentId, ?int $ownerId = null): bool
    {
        return $this->canView($u, $departmentId) || ($ownerId !== null && $ownerId === $u->id);
    }

    /** Admins and managers may change items in the departments they can edit. Everyone else (employees, viewers) may not. */
    public function canManage(User $u, ?int $departmentId): bool
    {
        return $u->hasAnyRole(['admin', 'manager']) && $this->canEdit($u, $departmentId);
    }

    /** Owners (not read-only viewers) may report progress and maintain the plan of what they own. */
    public function canWorkOn(User $u, ?int $departmentId, ?int $ownerId): bool
    {
        return $this->canManage($u, $departmentId) || ($ownerId !== null && $ownerId === $u->id && ! $u->hasRole('viewer'));
    }

    /** The departments a user belongs to (kind member), with their sub-departments. Empty when none are assigned. Used for team reports. */
    public function memberIds(User $u): array
    {
        return $this->expand($u, $this->assigned($u, 'member'));
    }

    /** The departments a user belongs to, without sub-departments. */
    public function memberIdsDirect(User $u): array
    {
        return $this->assigned($u, 'member');
    }

    public function kpiDepartment(User $u, int $kpiId): ?int
    {
        $d = Kpi::withoutGlobalScopes()->where('company_id', $u->company_id)->where('id', $kpiId)->value('department_id');

        return $d === null ? null : (int) $d;
    }

    private function assigned(User $u, string $kind): array
    {
        return DB::table('department_user')->where('user_id', $u->id)->where('kind', $kind)
            ->pluck('department_id')->map(fn ($v) => (int) $v)->all();
    }

    private function expand(User $u, array $seed): array
    {
        if (! $seed) return [];

        $children = DB::table('departments')->where('company_id', $u->company_id)->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $out = [];
        $queue = $seed;
        while ($queue) {
            $id = (int) array_shift($queue);
            if (isset($out[$id])) continue;
            $out[$id] = true;
            foreach ($children->get($id, collect()) as $c) $queue[] = (int) $c->id;
        }

        return array_keys($out);
    }
}
