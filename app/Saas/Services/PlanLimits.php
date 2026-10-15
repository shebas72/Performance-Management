<?php

namespace App\Saas\Services;

use App\Models\Company;
use App\Models\Department;
use App\Models\Invitation;
use App\Models\Kpi;
use App\Models\User;
use App\Saas\Models\Plan;
use App\Saas\Models\Subscription;
use App\Services\Limits;
use App\Support\AppMode;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * What a tenant's plan allows: counts (KPIs, users, departments; -1 or empty = unlimited) and feature flags
 * (PDF export, Arabic, projects).
 *
 * Which plan applies: only in saas mode. The company's latest subscription that is not cancelled or expired wins;
 * otherwise the plan whose name matches companies.plan; if neither exists the company is unrestricted, so demo
 * and unconfigured tenants keep working. Standalone installs are always unrestricted.
 */
class PlanLimits extends Limits
{
    private array $plans = [];

    public function plan(int $cid): ?Plan
    {
        if (! AppMode::saas()) return null;
        if (array_key_exists($cid, $this->plans)) return $this->plans[$cid];

        $plan = null;
        $sub  = Subscription::where('company_id', $cid)
            ->whereNotIn('status', ['cancelled', 'canceled', 'expired'])->latest('id')->first();
        if ($sub) $plan = Plan::find($sub->plan_id);

        if (! $plan) {
            $name = Company::whereKey($cid)->value('plan');
            if ($name) {
                $plan = Plan::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
            }
        }

        return $this->plans[$cid] = $plan;
    }

    /** null = unlimited. */
    public function limit(int $cid, string $resource): ?int
    {
        $plan = $this->plan($cid);
        if (! $plan) return null;

        $v = $plan->{$resource === 'kpis' ? 'kpi_limit' : ($resource === 'users' ? 'user_limit' : 'department_limit')};

        return ($v === null || (int) $v < 0) ? null : (int) $v;
    }

    public function used(int $cid, string $resource): int
    {
        return match ($resource) {
            'kpis'        => Kpi::withoutGlobalScopes()->where('company_id', $cid)->where('is_active', true)->count(),
            'departments' => Department::withoutGlobalScopes()->where('company_id', $cid)->count(),
            // Pending invitations hold a seat, otherwise a full company could invite without end and exceed the limit on accept.
            'users'       => User::where('company_id', $cid)->where('is_super_admin', false)->where('is_active', true)->count()
                + Invitation::where('company_id', $cid)->pending()->where('expires_at', '>', now())->count(),
        };
    }

    public function feature(int $cid, string $feature): bool
    {
        $plan = $this->plan($cid);
        if (! $plan) return true;

        return (bool) $plan->{'has_'.$feature};
    }

    /** Refuse (403 with code plan_limit) when adding $adding more would pass the limit. */
    public function assertCanAdd(int $cid, string $resource, int $adding = 1): void
    {
        $limit = $this->limit($cid, $resource);
        if ($limit === null) return;

        $used = $this->used($cid, $resource);
        if ($used + $adding <= $limit) return;

        $label = ['kpis' => 'KPIs', 'users' => 'users', 'departments' => 'departments'][$resource];
        $this->refuse("Your plan allows up to {$limit} {$label} and {$used} are already in use. Upgrade your plan to add more.", 'plan_limit', ['resource' => $resource, 'limit' => $limit, 'used' => $used]);
    }

    public function assertFeature(int $cid, string $feature): void
    {
        if ($this->feature($cid, $feature)) return;

        $label = ['pdf_export' => 'PDF export', 'arabic' => 'Arabic', 'projects' => 'Projects and initiatives', 'custom_roles' => 'Custom roles'][$feature] ?? $feature;
        $this->refuse("{$label} is not included in your plan. Upgrade your plan to use it.", 'plan_feature', ['feature' => $feature]);
    }

    /** For the settings page. */
    public function summary(int $cid): array
    {
        $plan = $this->plan($cid);

        // Standalone has no plans at all: the screen shows no plan card and every limit is empty.
        return [
            'mode'     => AppMode::current(),
            'plan'     => $plan ? ['id' => $plan->id, 'name' => $plan->name, 'name_ar' => $plan->name_ar] : null,
            'limits'   => collect(self::RESOURCES)->mapWithKeys(fn ($r) => [$r => ['limit' => $this->limit($cid, $r), 'used' => $this->used($cid, $r)]])->all(),
            'features' => $this->features($cid),
        ];
    }

    private function refuse(string $message, string $code, array $extra): never
    {
        throw new HttpResponseException(response()->json(['message' => $message, 'code' => $code] + $extra, 403));
    }
}
