<?php

use App\Saas\Http\Controllers\PlanController;
use App\Saas\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

/*
 * SaaS-only API routes. Loaded by App\Saas\SaasServiceProvider with the same "api" prefix and middleware
 * as routes/api.php, so the URLs are unchanged: /api/v1/auth/register and /api/v1/company/plan.
 */
Route::prefix('v1')->group(function () {
    // Public: a new company signs up and becomes a tenant with its first admin.
    Route::post('auth/register', [RegistrationController::class, 'register'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        // Plan name, limits with current use, and feature flags for the signed-in user's company.
        Route::get('company/plan', [PlanController::class, 'show']);
    });
});
