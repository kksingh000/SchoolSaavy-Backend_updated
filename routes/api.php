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

// Route::get('/health', function () {
//     return response()->json([
//         'redis_client_config' => config('database.redis.client'),
//         'redis_client_env' => env('REDIS_CLIENT'),
//         'predis_exists' => class_exists(\Predis\Client::class),
//         'phpredis_loaded' => extension_loaded('redis'),
//     ]);
// });

Route::get('/health', function () {
    try {

        $client = config('database.redis.client');

        $redisStatus = 'not connected';
        $redisError = null;

        try {
            \Illuminate\Support\Facades\Redis::connection()->ping();
            $redisStatus = 'connected';
        } catch (\Throwable $e) {
            $redisStatus = 'failed';
            $redisError = $e->getMessage();
        }

        return response()->json([
            'status' => 'ok',

            // 🔥 Redis runtime
            'redis_status' => $redisStatus,
            'redis_error' => $redisError,

            // 🔥 Config vs ENV
            'redis_client_config' => $client,
            'redis_client_env' => env('REDIS_CLIENT'),

            // 🔥 CRITICAL DEBUG FLAGS
            'predis_exists' => class_exists(\Predis\Client::class),
            'phpredis_loaded' => extension_loaded('redis'),

            // 🔥 What Laravel actually sees
            'redis_config_full' => config('database.redis'),

            // 🔥 ENV visibility
            'env_all_redis' => [
                'REDIS_CLIENT' => env('REDIS_CLIENT'),
                'REDIS_URL' => env('REDIS_URL'),
                'REDIS_HOST' => env('REDIS_HOST'),
                'REDIS_PORT' => env('REDIS_PORT'),
            ],

            // 🔥 sanity
            'app_env' => app()->environment(),

            'database' => 'connected',
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'fatal',
            'error' => $e->getMessage(),
            'trace' => substr($e->getTraceAsString(), 0, 500)
        ]);
    }
});

// Health check endpoint (Memory Optimized)
// Route::get('/health', function () {
//     $status = 'ok';
//     $redisStatus = 'not connected';
//     $dbStatus = 'not connected';
//     $startTime = microtime(true);

//     // Lightweight Redis check
//     try {
//         \Illuminate\Support\Facades\Redis::connection()->ping();
//         $redisStatus = 'connected';
//     } catch (\Exception $e) {
//         $status = 'error';
//     }
//     $redisDuration = microtime(true) - $startTime;

//     // Lightweight DB check
//     try {
//         \Illuminate\Support\Facades\DB::connection()->getPdo();
//         $dbStatus = 'connected';
//     } catch (\Exception $e) {
//         $status = 'error';
//     }
//     $dbDuration = microtime(true) - $startTime;

//     return response()->json([
//         'status' => $status,
//         'message' => 'API is running',
//         'redis' => $redisStatus,
//         'database' => $dbStatus,
//         'timestamp' => now()->toISOString(),
//         'version' => '1.0.0',
//         'server' => 'OpenSwoole',
//         'redis_duration' => round($redisDuration * 1000, 2) . ' ms',
//         'db_duration' => round($dbDuration * 1000, 2) . ' ms',
//         'memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB'
//     ]);
// });

// Apply JSON response middleware to all API routes
Route::middleware(['json.response'])->group(function () {
    // Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);

        // Refresh route - allows expired tokens
        Route::post('refresh', [AuthController::class, 'refresh']);

        // Password Reset Routes (Public - No Authentication Required)
        Route::post('password/send-otp', [App\Http\Controllers\Auth\PasswordResetController::class, 'sendResetOtp']);
        Route::post('password/verify-otp', [App\Http\Controllers\Auth\PasswordResetController::class, 'verifyOtp']);
        Route::post('password/reset', [App\Http\Controllers\Auth\PasswordResetController::class, 'resetPassword']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::get('check', [AuthController::class, 'checkToken']);
        });
    });

    // Profile Management Routes
    Route::prefix('profile')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [App\Http\Controllers\ProfileController::class, 'show']);
        Route::put('/', [App\Http\Controllers\ProfileController::class, 'update']);
        Route::post('/photo', [App\Http\Controllers\ProfileController::class, 'uploadPhoto']);
        Route::put('/password', [App\Http\Controllers\ProfileController::class, 'changePassword']);
        Route::get('/statistics', [App\Http\Controllers\ProfileController::class, 'getStatistics']);
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
        Route::get('academic-year-check', [App\Http\Controllers\Auth\AcademicYearController::class, 'check']);
        Route::get('academic-year-status', [App\Http\Controllers\Auth\CheckAcademicYearController::class, 'check']);
        Route::get('force-clear-academic-year-cache', [App\Http\Controllers\Auth\CheckAcademicYearController::class, 'clearCache']);

        Route::get("test", function (Request $request) {
            return response()->json(['message' => 'Test route accessed successfully']);
        });
    });

    // Device Token Management for Push Notifications
    Route::prefix('device')->middleware('auth:sanctum')->group(function () {
        // Register device token for push notifications
        Route::post('register-token', [App\Http\Controllers\NotificationController::class, 'registerDeviceToken']);

        // Deactivate device token
        Route::post('deactivate-token', [App\Http\Controllers\NotificationController::class, 'deactivateDeviceToken']);
    });

    // Media Server Routes (Camera Streaming)
    Route::prefix('media')->group(function () {
        // Get streaming credentials (requires auth and school context)
        Route::middleware(['auth:sanctum', 'inject.school'])->post('streaming-credentials', [App\Http\Controllers\MediaController::class, 'getStreamingCredentials']);
        
        // Validate stream (called by media server - no auth middleware)
        Route::post('validate-stream', [App\Http\Controllers\MediaController::class, 'validateStream']);
        
        // Get active streams for school (requires auth and school context)
        Route::middleware(['auth:sanctum', 'inject.school'])->group(function () {
            Route::get('streams', [App\Http\Controllers\MediaController::class, 'getActiveStreams']);
            Route::get('stream/{streamKey}', [App\Http\Controllers\MediaController::class, 'getStreamInfo']);
            Route::delete('stream/{streamKey}', [App\Http\Controllers\MediaController::class, 'endStream']);
        });
    });

    // Public Camera Stream Access (token-based authentication)
    Route::prefix('camera')->group(function () {
        Route::get('stream/{id}', [App\Http\Controllers\CameraController::class, 'publicStreamAccess']);
    });

    // Activity Logs (Admin/Teacher access)
    Route::prefix('activity-logs')->middleware(['auth:sanctum', 'inject.school'])->group(function () {
        Route::get('/', [App\Http\Controllers\ActivityLogController::class, 'index']);
        Route::get('/export', [App\Http\Controllers\ActivityLogController::class, 'export']);
        Route::get('/statistics', [App\Http\Controllers\ActivityLogController::class, 'statistics']);
        Route::get('/my-activity', [App\Http\Controllers\ActivityLogController::class, 'myActivity']);
        Route::get('/entity', [App\Http\Controllers\ActivityLogController::class, 'entityActivity']);
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
    
    // Include fee management routes
    require __DIR__ . '/fee-management.php';
});
