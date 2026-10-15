<?php

namespace App\Saas\Http\Controllers;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Saas\Services\PlanLimits;
use Illuminate\Http\Request;

/** The signed-in company's plan, limits with current use, and feature flags. Admin-only: it shows how close the company is to its limits. */
class PlanController extends Controller
{
    use ResolvesTenant;

    public function __construct(private PlanLimits $limits) {}

    public function show(Request $request)
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403, 'You do not have permission to change the settings.');

        return response()->json(['data' => $this->limits->summary($this->companyId($request))]);
    }
}
