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
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\StudentPerformanceController;
use App\Http\Controllers\AssessmentTypeController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AssessmentResultController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check endpoint
Route::get('/health', function () {
    $isRedisWorking = false;
    // check redis working or not 
    try {
        \Illuminate\Support\Facades\Redis::ping();
        $isRedisWorking = true;
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Redis connection failed: ' . $e->getMessage(),
            'code' => 'REDIS_CONNECTION_FAILED'
        ], 500);
    }
    return response()->json([
        'status' => 'ok',
        'message' => 'API is running',
        'redis' => $isRedisWorking ? 'connected' : 'not connected',
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
        Route::get('classes/teachers-classes', [ClassController::class, 'myClasses']);
        Route::apiResource('classes', ClassController::class);
        Route::post('classes/{id}/assign-students', [ClassController::class, 'assignStudents']);
        Route::get('classes/{id}/students', [ClassController::class, 'getStudents']);
        Route::get('classes/{id}/subjects', [ClassController::class, 'getSubjects']);
        Route::post('classes/{id}/assign-subjects', [ClassController::class, 'assignSubjects']);
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

        // Subject Management Routes
        Route::apiResource('subjects', SubjectController::class);
        Route::get('subjects/class/{classId}', [SubjectController::class, 'getByClass']);

        // Assignment Management Routes
        Route::prefix('assignments')->group(function () {
            Route::get('/', [AssignmentController::class, 'index']);
            Route::post('/', [AssignmentController::class, 'store']);
            Route::get('statistics', [AssignmentController::class, 'statistics']);
            Route::get('teacher-dashboard', [AssignmentController::class, 'teacherDashboard']);
            Route::get('type/{type}', [AssignmentController::class, 'byType']);
            Route::get('class/{classId}/upcoming', [AssignmentController::class, 'upcomingByClass']);
            Route::get('student/{studentId}', [AssignmentController::class, 'studentAssignments']);
            Route::get('{id}', [AssignmentController::class, 'show']);
            Route::get('{id}/submission-overview', [AssignmentController::class, 'getSubmissionOverview']);
            Route::put('{id}', [AssignmentController::class, 'update']);
            Route::delete('{id}', [AssignmentController::class, 'destroy']);
            Route::post('{id}/submit', [AssignmentController::class, 'submit']);
        });

        // Assignment Submission Routes
        Route::prefix('assignment-submissions')->group(function () {
            Route::post('{submissionId}/grade', [AssignmentController::class, 'gradeSubmission']);
        });

        // File Upload Routes (Generic for all modules)
        Route::prefix('uploads')->group(function () {
            Route::post('single', [FileUploadController::class, 'uploadSingle']);
            Route::post('multiple', [FileUploadController::class, 'uploadMultiple']);
            Route::delete('file', [FileUploadController::class, 'deleteFile']);
            Route::post('file-info', [FileUploadController::class, 'getFileInfo']);
        });

        // Student Performance Routes
        Route::prefix('student-performance')->group(function () {
            Route::get('{studentId}/report', [StudentPerformanceController::class, 'getPerformanceReport']);
            Route::get('{studentId}/class-comparison', [StudentPerformanceController::class, 'getClassPerformanceComparison']);
        });

        // Assessment System Routes
        Route::prefix('assessment-types')->group(function () {
            Route::get('/', [AssessmentTypeController::class, 'index']);
            Route::post('/', [AssessmentTypeController::class, 'store']);
            Route::get('active', [AssessmentTypeController::class, 'getActive']);
            Route::get('gradebook', [AssessmentTypeController::class, 'getGradebookComponents']);
            Route::get('{id}', [AssessmentTypeController::class, 'show']);
            Route::put('{id}', [AssessmentTypeController::class, 'update']);
            Route::delete('{id}', [AssessmentTypeController::class, 'destroy']);
            Route::patch('{id}/toggle-status', [AssessmentTypeController::class, 'toggleStatus']);
        });

        Route::prefix('assessments')->group(function () {
            Route::get('/', [AssessmentController::class, 'index']);
            Route::post('/', [AssessmentController::class, 'store']);
            Route::get('upcoming', [AssessmentController::class, 'upcoming']);
            Route::get('completed', [AssessmentController::class, 'completed']);
            Route::get('statistics', [AssessmentController::class, 'statistics']);
            Route::get('teacher-dashboard', [AssessmentController::class, 'teacherDashboard']);
            Route::get('class/{classId}', [AssessmentController::class, 'getByClass']);
            Route::get('subject/{subjectId}', [AssessmentController::class, 'getBySubject']);
            Route::get('type/{typeId}', [AssessmentController::class, 'getByType']);
            Route::get('{id}', [AssessmentController::class, 'show']);
            Route::put('{id}', [AssessmentController::class, 'update']);
            Route::delete('{id}', [AssessmentController::class, 'destroy']);
            Route::patch('{id}/status', [AssessmentController::class, 'updateStatus']);

            // Assessment Results Management
            Route::get('{id}/results', [AssessmentController::class, 'getResults']);
            Route::post('{id}/results', [AssessmentResultController::class, 'store']);
            Route::post('{id}/results/bulk', [AssessmentResultController::class, 'bulkStore']);
            Route::patch('{id}/publish-results', [AssessmentController::class, 'publishResults']);
            Route::get('{id}/statistics', [AssessmentController::class, 'getAssessmentStatistics']);
        });

        Route::prefix('assessment-results')->group(function () {
            Route::get('{id}', [AssessmentResultController::class, 'show']);
            Route::put('{id}', [AssessmentResultController::class, 'update']);
            Route::delete('{id}', [AssessmentResultController::class, 'destroy']);
            Route::patch('{id}/publish', [AssessmentResultController::class, 'publish']);
            Route::get('student/{studentId}', [AssessmentResultController::class, 'getStudentResults']);
            Route::get('student/{studentId}/subject/{subjectId}', [AssessmentResultController::class, 'getStudentSubjectResults']);
        });
    });
});
