<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BscPerspectiveController;
use App\Http\Controllers\Api\V1\CoreValueController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\KpiController;
use App\Http\Controllers\Api\V1\KpiEntryController;
use App\Http\Controllers\Api\V1\KpiTargetController;
use App\Http\Controllers\Api\V1\StrategicObjectiveController;
use App\Http\Controllers\Api\V1\StrategyHouseController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\DashboardController;


Route::prefix('v1')->group(function () {

    // Public
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
   

    // Authenticated (Sanctum token)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::post('kpi-entries/bulk', [KpiEntryController::class, 'bulk']);
        Route::apiResource('kpi-entries', KpiEntryController::class);

         Route::prefix('dashboard')->group(function () {
            Route::get('/', [DashboardController::class, 'index']);
            Route::get('objectives', [DashboardController::class, 'objectives']);
            Route::get('departments', [DashboardController::class, 'departments']);
            Route::get('classification', [DashboardController::class, 'classification']);
        });



        Route::get('strategy-house', [StrategyHouseController::class, 'show']);
        Route::put('strategy-house', [StrategyHouseController::class, 'update']);

        Route::apiResource('core-values', CoreValueController::class);
        Route::apiResource('perspectives', BscPerspectiveController::class);
        Route::apiResource('objectives', StrategicObjectiveController::class);
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('kpis', KpiController::class);
        Route::apiResource('kpi-targets', KpiTargetController::class);
        Route::put('perspectives/weights', [BscPerspectiveController::class, 'updateWeights']);
    });
});
