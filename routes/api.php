<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\EventController;

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
        Route::get('classes/teacher/{teacherId}', [ClassController::class, 'getClassesByTeacher']);

        // Attendance Management Routes
        Route::prefix('attendance')->group(function () {
            Route::get('/', [AttendanceController::class, 'index']);
            Route::post('bulk', [AttendanceController::class, 'markBulkAttendance']);
            Route::get('class/{classId}/report', [AttendanceController::class, 'getClassAttendanceReport']);
            Route::get('student/{studentId}/report', [AttendanceController::class, 'getStudentAttendanceReport']);
        });

        // Timetable Management Routes
        Route::prefix('timetable')->group(function () {
            Route::get('/', [TimetableController::class, 'index']);
            Route::post('/', [TimetableController::class, 'store']);
            Route::put('{id}', [TimetableController::class, 'update']);
            Route::delete('{id}', [TimetableController::class, 'destroy']);
            Route::get('class/{classId}', [TimetableController::class, 'getClassTimetable']);
            Route::get('teacher/{teacherId}', [TimetableController::class, 'getTeacherTimetable']);
            Route::get('weekly-overview', [TimetableController::class, 'getWeeklyOverview']);
        });

        // Event Management Routes
        Route::prefix('events')->group(function () {
            Route::get('/', [EventController::class, 'index']);
            Route::post('/', [EventController::class, 'store']);
            Route::get('todays', [EventController::class, 'todaysEvents']);
            Route::get('upcoming', [EventController::class, 'upcomingEvents']);
            Route::get('calendar', [EventController::class, 'calendar']);
            Route::get('unacknowledged', [EventController::class, 'unacknowledged']);
            Route::get('statistics', [EventController::class, 'statistics']);
            Route::get('type/{type}', [EventController::class, 'byType']);
            Route::get('{id}', [EventController::class, 'show']);
            Route::put('{id}', [EventController::class, 'update']);
            Route::delete('{id}', [EventController::class, 'destroy']);
            Route::post('{id}/acknowledge', [EventController::class, 'acknowledge']);
            Route::get('{id}/acknowledgments', [EventController::class, 'acknowledgments']);
            Route::post('{id}/duplicate', [EventController::class, 'duplicate']);
        });
    });
});
