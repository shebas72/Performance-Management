<?php

namespace App\Services;

/**
 * What a company may do. This base class is the standalone behaviour: nothing is limited and every feature is on.
 *
 * Shared code (controllers, middleware, models) depends on this class only. In SaaS mode the container hands out
 * App\Saas\Services\PlanLimits instead (bound in App\Saas\SaasServiceProvider), which enforces the company's plan.
 * Delete app/Saas for a standalone build and this class keeps working unchanged.
 */
class Limits
{
    public const RESOURCES = ['kpis', 'users', 'departments'];
    public const FEATURES  = ['pdf_export', 'arabic', 'projects', 'custom_roles'];

    /** null = unlimited. */
    public function limit(int $cid, string $resource): ?int
    {
        return null;
    }

    public function feature(int $cid, string $feature): bool
    {
        return true;
    }

    /** Refuse (403) when adding $adding more would pass the limit. Never refuses here. */
    public function assertCanAdd(int $cid, string $resource, int $adding = 1): void
    {
    }

    /** Refuse (403) when the feature is not available. Never refuses here. */
    public function assertFeature(int $cid, string $feature): void
    {
    }

    public function features(int $cid): array
    {
        return collect(self::FEATURES)->mapWithKeys(fn ($f) => [$f => $this->feature($cid, $f)])->all();
    }
}
