<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Admin Routes
|--------------------------------------------------------------------------
| Routes specifically for super admin monitoring and system management
| These routes are separate from regular school admin routes
|--------------------------------------------------------------------------
*/

// Super Admin Routes - Only accessible to super admin user type
Route::middleware(['auth:sanctum', 'user.type:super_admin'])->prefix('super-admin')->group(function () {

    // Notification System Monitoring
    Route::prefix('notifications')->group(function () {
        // Monitor delivery issues across all schools
        Route::get('delivery-issues', [App\Http\Controllers\NotificationController::class, 'getDeliveryIssues']);

        // Get comprehensive notification statistics with delivery health
        Route::get('stats', [App\Http\Controllers\NotificationController::class, 'getSuperAdminStats']);

        // System health check with detailed Firebase diagnostics
        Route::get('system-health', [App\Http\Controllers\NotificationController::class, 'healthCheck']);
    });

    // School Management (for monitoring multiple schools)
    Route::prefix('schools')->group(function () {
        // Get schools with notification statistics
        Route::get('notification-stats', function () {
            $schools = \App\Models\School::with(['notifications' => function ($query) {
                $query->select('school_id')
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
                    ->groupBy('school_id');
            }])->get();

            return response()->json([
                'status' => 'success',
                'data' => $schools,
                'message' => 'Schools with notification stats retrieved successfully'
            ]);
        });
    });

    // Firebase System Diagnostics
    Route::prefix('firebase')->group(function () {
        // Test Firebase connection for all schools
        Route::post('test-connection', function () {
            $firebase = app(\App\Services\FirebaseService::class);
            $result = $firebase->sendToTopic('super-admin-test', [
                'title' => 'Super Admin Test',
                'body' => 'Testing Firebase connection from super admin panel'
            ]);

            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'data' => $result,
                'message' => $result['success'] ? 'Firebase connection successful' : 'Firebase connection failed'
            ]);
        });

        // Get device token statistics across all schools
        Route::get('token-stats', function () {
            $stats = [
                'total_tokens' => \App\Models\UserDeviceToken::count(),
                'active_tokens' => \App\Models\UserDeviceToken::where('is_active', true)->count(),
                'recent_registrations' => \App\Models\UserDeviceToken::where('created_at', '>', now()->subDays(7))->count(),
                'tokens_by_platform' => \App\Models\UserDeviceToken::where('is_active', true)
                    ->selectRaw('device_type, COUNT(*) as count')
                    ->groupBy('device_type')
                    ->get(),
                'tokens_by_school' => \App\Models\UserDeviceToken::join('users', 'user_device_tokens.user_id', '=', 'users.id')
                    ->leftJoin('teachers', 'users.id', '=', 'teachers.user_id')
                    ->leftJoin('parents', 'users.id', '=', 'parents.user_id')
                    ->leftJoin('parent_student', 'parents.id', '=', 'parent_student.parent_id')
                    ->leftJoin('students', 'parent_student.student_id', '=', 'students.id')
                    ->selectRaw('COALESCE(teachers.school_id, students.school_id) as school_id, COUNT(DISTINCT user_device_tokens.id) as token_count')
                    ->where('user_device_tokens.is_active', true)
                    ->whereNotNull(\Illuminate\Support\Facades\DB::raw('COALESCE(teachers.school_id, students.school_id)'))
                    ->groupBy(\Illuminate\Support\Facades\DB::raw('COALESCE(teachers.school_id, students.school_id)'))
                    ->get()
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats,
                'message' => 'Device token statistics retrieved successfully'
            ]);
        });
    });
});
