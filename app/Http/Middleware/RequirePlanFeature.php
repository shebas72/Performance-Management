<?php

namespace App\Http\Middleware;

use App\Services\PlanLimits;
use Closure;
use Illuminate\Http\Request;

/** Route middleware: ->middleware(RequirePlanFeature::class.':projects'). Refuses the request when the company's plan lacks the feature. */
class RequirePlanFeature
{
    public function __construct(private PlanLimits $limits) {}

    public function handle(Request $request, Closure $next, string $feature)
    {
        $cid = $request->user()?->company_id;
        if ($cid) $this->limits->assertFeature((int) $cid, $feature);

        return $next($request);
    }
}
