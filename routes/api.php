<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ContactController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Main API route file that includes other route files for better organization
| - Authentication and common routes are defined here
| - Role-specific routes are defined in separate files
|--------------------------------------------------------------------------
*/

// Health check endpoint
Route::get('/health', function () {
    $isRedisWorking = false;
    $isDatabaseWorking = false;
    // check redis working or not 
    try {
        \Illuminate\Support\Facades\Redis::ping();
        $isRedisWorking = true;
        $isDatabaseWorking = \Illuminate\Support\Facades\DB::connection()->getPdo() !== null;
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Redis connection failed: ' . $e->getMessage(),
            'code' => 'REDIS_CONNECTION_FAILED'
        ], 500);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Unexpected error occurred: ' . $e->getMessage(),
            'code' => 'UNEXPECTED_ERROR'
        ], 500);
    }
    return response()->json([
        'status' => 'ok',
        'message' => 'API is running',
        'redis' => $isRedisWorking ? 'connected' : 'not connected',
        'database' => $isDatabaseWorking ? 'connected' : 'not connected',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
        'server' => 'RoadRunner'
    ]);
});

// Apply JSON response middleware to all API routes
Route::middleware('json.response')->group(function () {
    // Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);

        // Refresh route - allows expired tokens
        Route::post('refresh', [AuthController::class, 'refresh']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::get('check', [AuthController::class, 'checkToken']);
        });
    });

    // Public Module Information (for pricing page)
    // Route::get('modules/{id}', [ModuleController::class, 'show']);

    // Public Contact Form Routes (no authentication required)
    Route::prefix('contact')->group(function () {
        Route::get('form-token', [ContactController::class, 'getFormToken']);
        Route::post('submit', [ContactController::class, 'submit']);

        // Debug endpoint for testing
        Route::post('debug', function (Request $request) {
            return response()->json([
                'headers' => $request->headers->all(),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
                'data' => $request->all()
            ]);
        });
    });

    // School info endpoint
    Route::middleware(['auth:sanctum', 'school.status', 'inject.school'])->group(function () {
        Route::get('school', [ModuleController::class, 'getSchoolModules']);

        Route::get("test", function (Request $request) {
            return response()->json(['message' => 'Test route accessed successfully']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Include Role-Specific Route Files
    |--------------------------------------------------------------------------
    */

    // Include admin/teacher routes (most comprehensive)
    require __DIR__ . '/admin.php';

    // Include parent-specific routes
    require __DIR__ . '/parent.php';

    // Include teacher-specific routes
    require __DIR__ . '/teacher.php';

    // Include super admin routes
    require __DIR__ . '/superadmin.php';
});
