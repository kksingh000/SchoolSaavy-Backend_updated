<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Apply JSON response middleware to all API routes
Route::middleware('json.response')->group(function () {
    // Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    // Public Module Information (for pricing page)

    // Route::get('modules/{id}', [ModuleController::class, 'show']);

    // Protected Routes with school data injection
    Route::middleware(['auth:sanctum', 'inject.school'])->group(function () {
        Route::get('school', [ModuleController::class, 'getSchoolModules']);
        // Dashboard Routes
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get("test", function (Request $request) {
            return response()->json(['message' => 'Test route accessed successfully']);
        });
        // Module Management Routes
        Route::prefix('modules')->group(function () {
            Route::get('pricing', [ModuleController::class, 'getModulePricing']);
            Route::get('/', [ModuleController::class, 'index']);
            Route::get('school', [ModuleController::class, 'getSchoolModules']);
            Route::post('activate-all', [ModuleController::class, 'activateAllModules']);
            Route::post('{moduleId}/activate', [ModuleController::class, 'activateModule']);
            Route::post('{moduleId}/deactivate', [ModuleController::class, 'deactivateModule']);
            Route::get('{id}', [ModuleController::class, 'show']);
        });

        // Student Management Routes
        Route::apiResource('students', StudentController::class);
        Route::get('students/{id}/attendance', [StudentController::class, 'getAttendanceReport']);
        Route::get('students/{id}/fees', [StudentController::class, 'getFeeStatus']);

        // Class Management Routes
        Route::apiResource('classes', ClassController::class);
        Route::post('classes/{id}/assign-students', [ClassController::class, 'assignStudents']);
        Route::get('classes/{id}/students', [ClassController::class, 'getStudents']);
        Route::get('classes/{id}/timetable', [ClassController::class, 'getTimetable']);

        // Attendance Management Routes
        Route::prefix('attendance')->group(function () {
            Route::get('/', [AttendanceController::class, 'index']);
            Route::post('bulk', [AttendanceController::class, 'markBulkAttendance']);
            Route::get('class/{classId}/report', [AttendanceController::class, 'getClassAttendanceReport']);
            Route::get('student/{studentId}/report', [AttendanceController::class, 'getStudentAttendanceReport']);
        });
    });
});
