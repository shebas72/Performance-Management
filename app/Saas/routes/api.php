<?php

use App\Saas\Http\Controllers\PlanController;
use App\Saas\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

/*
 * SaaS-only API routes. Loaded by App\Saas\SaasServiceProvider with the same "api" prefix and middleware
 * as routes/api.php, so the URLs are /api/v1/auth/register, /api/v1/auth/verify-email/{token},
 * /api/v1/auth/resend-verification and /api/v1/company/plan.
 */
Route::prefix('v1')->group(function () {
    // Public: a new company signs up (needs email verification, then super-admin approval before sign-in).
    Route::post('auth/register', [RegistrationController::class, 'register'])->middleware('throttle:10,1');
    Route::get('auth/verify-email/{token}', [RegistrationController::class, 'verify'])->middleware('throttle:30,1');
    Route::post('auth/resend-verification', [RegistrationController::class, 'resend'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        // Plan name, limits with current use, and feature flags for the signed-in user's company.
        Route::get('company/plan', [PlanController::class, 'show']);
    });
});
