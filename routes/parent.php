<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ParentController;

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
});
