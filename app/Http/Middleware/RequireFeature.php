<?php

namespace App\Http\Middleware;

use App\Services\Limits;
use Closure;
use Illuminate\Http\Request;

/** Route middleware: ->middleware(RequireFeature::class.':projects'). Refuses the request when the feature is not available to the company (never in standalone; in SaaS it follows the plan). */
class RequireFeature
{
    public function __construct(private Limits $limits) {}

    public function handle(Request $request, Closure $next, string $feature)
    {
        $cid = $request->user()?->company_id;
        if ($cid) $this->limits->assertFeature((int) $cid, $feature);

        return $next($request);
    }
}
