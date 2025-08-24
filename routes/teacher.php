<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\TeacherController;

/*
|--------------------------------------------------------------------------
| Teacher Routes
|--------------------------------------------------------------------------
| Routes specifically for teachers
| All routes require teacher authentication and school data injection
|--------------------------------------------------------------------------
*/

// Teacher specific routes - these could be accessed by teachers only or have teacher-specific functionality
Route::middleware(['auth:sanctum', 'school.status', 'inject.school', 'user.type:teacher,admin'])->group(function () {

    // Teacher Dashboard & Stats
    Route::prefix('teacher')->group(function () {
        Route::get('dashboard-stats', [TeacherController::class, 'getDashboardStats']);
        Route::get('my-classes', [ClassController::class, 'myClasses']);
        Route::get('my-classes-simple', [ClassController::class, 'getMyClassesSimplified']);
        Route::get('my-assignments', [AssignmentController::class, 'teacherDashboard']);
    });

    // Teacher-specific class routes
    Route::prefix('teacher/classes')->group(function () {
        Route::get('{id}/students', [ClassController::class, 'getStudents']);
        Route::get('{id}/timetable', [ClassController::class, 'getTimetable']);
        Route::get('{id}/assignments', [AssignmentController::class, 'getByClassOptimized']);
    });

    // Teacher Assignment Management
    Route::prefix('teacher/assignments')->group(function () {
        Route::get('/', [AssignmentController::class, 'index']);
        Route::post('/', [AssignmentController::class, 'store']);
        Route::put('{id}', [AssignmentController::class, 'update']);
        Route::delete('{id}', [AssignmentController::class, 'destroy']);
        Route::get('{id}/submission-overview', [AssignmentController::class, 'getSubmissionOverview']);
        Route::post('{submissionId}/grade', [AssignmentController::class, 'gradeSubmission']);
        Route::post('{submissionId}/return-for-revision', [AssignmentController::class, 'returnSubmissionForRevision']);
    });

    // Teacher Attendance Management
    Route::prefix('teacher/attendance')->group(function () {
        Route::post('/', [AttendanceController::class, 'markSingleAttendance']);
        Route::post('bulk', [AttendanceController::class, 'markBulkAttendance']);
        Route::get('class/{classId}/report', [AttendanceController::class, 'getClassAttendanceReport']);
        Route::get('class/{classId}/date', [AttendanceController::class, 'getClassAttendanceByDate']);
    });

    // Teacher Timetable Management
    Route::prefix('teacher/timetable')->group(function () {
        Route::get('my-schedule', [TimetableController::class, 'getTeacherTimetable']);
        Route::get('weekly-overview', [TimetableController::class, 'getWeeklyOverview']);
    });

    // Teacher Assessment Management
    Route::prefix('teacher/assessments')->group(function () {
        Route::get('/', [AssessmentController::class, 'index']);
        Route::post('/', [AssessmentController::class, 'store']);
        Route::put('{id}', [AssessmentController::class, 'update']);
        Route::delete('{id}', [AssessmentController::class, 'destroy']);
        Route::get('dashboard', [AssessmentController::class, 'teacherDashboard']);
        Route::get('{id}/results', [AssessmentController::class, 'getResults']);
        Route::post('{id}/results/bulk', [\App\Http\Controllers\AssessmentResultController::class, 'bulkStore']);
        Route::patch('{id}/publish-results', [AssessmentController::class, 'publishResults']);
    });
});
