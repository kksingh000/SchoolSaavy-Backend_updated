<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Admin Routes
|--------------------------------------------------------------------------
| Routes for platform-level management by super administrators
| All routes require super admin authentication
|--------------------------------------------------------------------------
*/

// Super Admin Routes - Platform Management
Route::middleware(['auth:sanctum', 'super.admin'])->prefix('super-admin')->group(function () {

    // School Management APIs
    Route::prefix('schools')->group(function () {
        Route::get('/', [\App\Http\Controllers\SuperAdmin\SchoolController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\SuperAdmin\SchoolController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\SuperAdmin\SchoolController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\SuperAdmin\SchoolController::class, 'update']);
        Route::patch('/{id}/toggle-status', [\App\Http\Controllers\SuperAdmin\SchoolController::class, 'toggleStatus']);
        Route::delete('/{id}', [\App\Http\Controllers\SuperAdmin\SchoolController::class, 'destroy']);

        // School Module Management APIs
        Route::get('/modules/available', [\App\Http\Controllers\SuperAdmin\SchoolController::class, 'getAvailableModules']);
        Route::get('/{id}/modules', [\App\Http\Controllers\SuperAdmin\SchoolController::class, 'getSchoolModules']);
        Route::post('/{id}/modules/assign', [\App\Http\Controllers\SuperAdmin\SchoolController::class, 'assignModules']);
        Route::post('/{id}/modules/remove', [\App\Http\Controllers\SuperAdmin\SchoolController::class, 'removeModules']);
        Route::put('/{id}/modules/{moduleId}/settings', [\App\Http\Controllers\SuperAdmin\SchoolController::class, 'updateModuleSettings']);
        Route::patch('/{id}/modules/{moduleId}/toggle-status', [\App\Http\Controllers\SuperAdmin\SchoolController::class, 'toggleModuleStatus']);
        Route::get('/{id}/modules/analytics', [\App\Http\Controllers\SuperAdmin\SchoolController::class, 'getSchoolModuleAnalytics']);
        Route::post('/modules/bulk-assign', [\App\Http\Controllers\SuperAdmin\SchoolController::class, 'bulkAssignModules']);
    });

    // Analytics & Reporting APIs
    Route::prefix('analytics')->group(function () {
        Route::get('/platform-overview', [\App\Http\Controllers\SuperAdmin\AnalyticsController::class, 'platformOverview']);
        Route::get('/schools', [\App\Http\Controllers\SuperAdmin\AnalyticsController::class, 'schoolAnalytics']);
        Route::get('/schools/{id}/detailed', [\App\Http\Controllers\SuperAdmin\AnalyticsController::class, 'schoolDetailedAnalytics']);
        Route::get('/modules/usage', [\App\Http\Controllers\SuperAdmin\AnalyticsController::class, 'moduleUsage']);
        Route::get('/media/statistics', [\App\Http\Controllers\SuperAdmin\AnalyticsController::class, 'mediaStatistics']);
        Route::get('/users/growth', [\App\Http\Controllers\SuperAdmin\AnalyticsController::class, 'userGrowth']);
        Route::get('/schools/top-performing', [\App\Http\Controllers\SuperAdmin\AnalyticsController::class, 'topSchools']);
    });
});
