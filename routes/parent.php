<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ParentController;
use App\Http\Controllers\ParentCameraController;

/*
|--------------------------------------------------------------------------
| Parent Routes
|--------------------------------------------------------------------------
| Routes specifically for parent mobile application
| All routes require parent authentication and school data injection
|--------------------------------------------------------------------------
*/

// Parent APIs - For Parent Mobile Application
Route::prefix('parent')->middleware(['auth:sanctum', 'school.status', 'inject.school', 'user.type:parent'])->group(function () {
    // Get parent's children
    Route::get('children', [ParentController::class, 'getChildren']);

    // Student Statistics APIs
    Route::post('student/statistics', [ParentController::class, 'getStudentStatistics']);
    Route::post('student/statistics/refresh', [ParentController::class, 'refreshStatistics']);

    // Student Attendance APIs
    Route::post('student/attendance', [ParentController::class, 'getStudentAttendance']);

    // Student Assignment APIs
    Route::post('student/assignments', [ParentController::class, 'getStudentAssignments']);
    Route::post('student/assignment/details', [ParentController::class, 'getAssignmentDetails']);

    // Student Gallery APIs
    Route::post('student/gallery/albums', [ParentController::class, 'getStudentGalleryAlbums']);
    Route::post('student/gallery/album/media', [ParentController::class, 'getStudentGalleryAlbumMedia']);

    // Notification APIs for Parents
    Route::prefix('notifications')->group(function () {
        // Get user's notifications with filters
        Route::get('/', [App\Http\Controllers\NotificationController::class, 'getUserNotifications']);

        // Get unread notifications count
        Route::get('unread-count', [App\Http\Controllers\NotificationController::class, 'getUnreadCount']);

        // Mark notification as read
        Route::patch('{notificationId}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead']);

        // Mark notification as acknowledged
        Route::patch('{notificationId}/acknowledge', [App\Http\Controllers\NotificationController::class, 'markAsAcknowledged']);

        // Mark all notifications as read
        Route::patch('mark-all-read', [App\Http\Controllers\NotificationController::class, 'markAllAsRead']);
    });

    // Device Token Management for Push Notifications
    Route::prefix('device')->group(function () {
        // Register device token for push notifications
        Route::post('register-token', [App\Http\Controllers\NotificationController::class, 'registerDeviceToken']);

        // Deactivate device token
        Route::post('deactivate-token', [App\Http\Controllers\NotificationController::class, 'deactivateDeviceToken']);
    });

    // Camera Monitoring APIs for Parents (Class-based Access)
    Route::prefix('cameras')->group(function () {
        // Get accessible cameras (class-based)
        Route::get('accessible', [ParentCameraController::class, 'getAccessibleCameras']);
        
        // Get classroom access info
        Route::get('classroom-access', [ParentCameraController::class, 'getClassroomAccess']);
        
        // Live streaming
        Route::post('{cameraId}/stream-token', [ParentCameraController::class, 'getStreamToken']);
        Route::post('end-session', [ParentCameraController::class, 'endSession']);
    });
});
