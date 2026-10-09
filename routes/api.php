<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BscPerspectiveController;
use App\Http\Controllers\Api\V1\CoreValueController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\KpiController;
use App\Http\Controllers\Api\V1\KpiEntryController;
use App\Http\Controllers\Api\V1\KpiHistoryController;
use App\Http\Controllers\Api\V1\KpiTargetController;
use App\Http\Controllers\Api\V1\StrategicObjectiveController;
use App\Http\Controllers\Api\V1\StrategyHouseController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\CorrectiveProposalController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\InitiativeController;
use App\Http\Controllers\Api\V1\{TeamController, CompanyController};
use App\Http\Controllers\Api\V1\ProfileController;


Route::prefix('v1')->group(function () {

    // Public
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('auth/invitations/{token}', [TeamController::class, 'showInvite'])->middleware('throttle:20,1');
    Route::post('auth/accept-invite', [TeamController::class, 'accept'])->middleware('throttle:10,1');
   

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
        Route::put('perspectives/weights', [BscPerspectiveController::class, 'updateWeights']);
        Route::apiResource('perspectives', BscPerspectiveController::class);
        Route::apiResource('objectives', StrategicObjectiveController::class);
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('kpis', KpiController::class);
        Route::apiResource('kpi-targets', KpiTargetController::class);
        Route::get('kpis/{id}/history', [KpiHistoryController::class, 'index'])->whereNumber('id');
        Route::get('corrective-proposals', [CorrectiveProposalController::class, 'index']);
        Route::post('corrective-proposals', [CorrectiveProposalController::class, 'store']);
        Route::put('corrective-proposals/{id}', [CorrectiveProposalController::class, 'update'])->whereNumber('id');
        Route::delete('corrective-proposals/{id}', [CorrectiveProposalController::class, 'destroy'])->whereNumber('id');
        Route::post('corrective-proposals/{id}/review', [CorrectiveProposalController::class, 'review'])->whereNumber('id');
        Route::get('dashboard/projects', [ProjectController::class, 'performance']);
        Route::get('projects', [ProjectController::class, 'index']);
        Route::post('projects', [ProjectController::class, 'store']);
        Route::get('projects/{id}', [ProjectController::class, 'show'])->whereNumber('id');
        Route::put('projects/{id}', [ProjectController::class, 'update'])->whereNumber('id');
        Route::delete('projects/{id}', [ProjectController::class, 'destroy'])->whereNumber('id');
        Route::put('projects/{id}/progress', [ProjectController::class, 'progress'])->whereNumber('id');

        Route::get('dashboard/initiatives', [InitiativeController::class, 'performance']);
        Route::get('initiatives', [InitiativeController::class, 'index']);
        Route::post('initiatives', [InitiativeController::class, 'store']);
        Route::get('initiatives/{id}', [InitiativeController::class, 'show'])->whereNumber('id');
        Route::put('initiatives/{id}', [InitiativeController::class, 'update'])->whereNumber('id');
        Route::delete('initiatives/{id}', [InitiativeController::class, 'destroy'])->whereNumber('id');
        Route::put('initiatives/{id}/progress', [InitiativeController::class, 'progress'])->whereNumber('id');
        Route::post('initiatives/{id}/tasks', [InitiativeController::class, 'storeTask'])->whereNumber('id');
        Route::put('execution-plan-tasks/{id}', [InitiativeController::class, 'updateTask'])->whereNumber('id');
        Route::delete('execution-plan-tasks/{id}', [InitiativeController::class, 'destroyTask'])->whereNumber('id');
        Route::get('users', [TeamController::class, 'users']);
        Route::put('users/{id}', [TeamController::class, 'updateUser'])->whereNumber('id');
        Route::get('invitations', [TeamController::class, 'invitations']);
        Route::post('invitations', [TeamController::class, 'invite']);
        Route::post('invitations/{id}/resend', [TeamController::class, 'resend'])->whereNumber('id');
        Route::delete('invitations/{id}', [TeamController::class, 'revoke'])->whereNumber('id');
        Route::get('company', [CompanyController::class, 'show']);
        Route::put('company', [CompanyController::class, 'update']);
        Route::post('company/logo', [CompanyController::class, 'uploadLogo']);
        Route::delete('company/logo', [CompanyController::class, 'removeLogo']);
        Route::post('users', [TeamController::class, 'createUser']);
        Route::put('auth/profile', [ProfileController::class, 'update']);
        Route::post('auth/change-password', [ProfileController::class, 'changePassword']);
                
        
    });
});
