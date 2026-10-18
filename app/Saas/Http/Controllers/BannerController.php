<?php

namespace App\Saas\Http\Controllers;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Saas\Services\BillingService;
use App\Saas\Services\PlanLimits;
use Illuminate\Http\Request;

/** Things an organization admin must not miss. Computed live, so a banner disappears once the problem is fixed. */
class BannerController extends Controller
{
    use ResolvesTenant;

    public function index(Request $request, BillingService $billing, PlanLimits $limits)
    {
        if (! $request->user()->hasPermission('settings.manage')) {
            return response()->json(['data' => []]);
        }

        $cid = $this->companyId($request);
        $sub = $billing->current($cid);
        $banners = [];

        if ($sub?->status === 'trialing' && $sub->trial_ends_at) {
            $days = (int) ceil(now()->diffInHours($sub->trial_ends_at, false) / 24);
            if ($days <= 7) {
                $banners[] = ['id' => 'trial', 'level' => $days <= 2 ? 'danger' : 'warning', 'code' => 'trial', 'params' => ['days' => max(0, $days)], 'cta_url' => '/billing'];
            }
        }

        if ($sub?->status === 'past_due') {
            $banners[] = ['id' => 'past_due', 'level' => 'danger', 'code' => 'past_due', 'params' => [], 'cta_url' => '/billing'];
        }

        if ($sub?->cancel_at_period_end && $sub->current_period_ends_at) {
            $banners[] = ['id' => 'ending', 'level' => 'info', 'code' => 'ending', 'params' => ['date' => $sub->current_period_ends_at->toDateString()], 'cta_url' => '/billing'];
        }

        foreach ($limits->summary($cid)['limits'] as $resource => $row) {
            if ($row['limit'] && $row['used'] / $row['limit'] >= 0.9) {
                $banners[] = [
                    'id' => 'limit_'.$resource,
                    'level' => $row['used'] >= $row['limit'] ? 'danger' : 'warning',
                    'code' => 'limit',
                    'params' => ['resource' => $resource, 'used' => $row['used'], 'limit' => $row['limit']],
                    'cta_url' => '/billing',
                ];
            }
        }

        return response()->json(['data' => $banners]);
    }
}