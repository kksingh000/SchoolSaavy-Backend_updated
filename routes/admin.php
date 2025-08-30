<?php

use Illuminate\Support\Facades\Route;
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
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\SchoolSettingController;
use App\Http\Controllers\AdmissionNumberController;
use App\Http\Controllers\RollNumberController;
use App\Http\Controllers\FeeStructureController;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
| Routes for school administrators, teachers, and staff members
| All routes require authentication and school data injection
|--------------------------------------------------------------------------
*/

// Protected Routes with school data injection and school status check
Route::middleware(['auth:sanctum', 'school.status', 'inject.school'])->group(function () {

    // Dashboard Routes with caching
    Route::middleware('api.cache:ttl:180,vary_by_school:true,vary_by_user:true')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index']);
    });

    // Dashboard Analytics Routes (with longer cache for expensive queries)
    Route::prefix('dashboard')->group(function () {
        Route::get('attendance-graph', [DashboardController::class, 'getAttendanceGraphData']);
    });

    Route::get("test", function (\Illuminate\Http\Request $request) {
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

    // Student Management Routes with smart caching
    Route::prefix('students')->group(function () {
        // Cached read operations
        Route::middleware('api.cache:ttl:300,vary_by_school:true,vary_by_user:true')->group(function () {
            Route::get('/', [StudentController::class, 'index']);
            Route::get('{id}', [StudentController::class, 'show']);
        });

        // Cached reports (longer TTL)
        Route::middleware('api.cache:ttl:600,vary_by_school:true')->group(function () {
            Route::get('{id}/attendance', [StudentController::class, 'getAttendanceReport']);
            Route::get('{id}/fees', [StudentController::class, 'getFeeStatus']);
        });

        // Write operations (no caching)
        Route::post('/', [StudentController::class, 'store']);
        Route::put('{id}', [StudentController::class, 'update']);
        Route::delete('{id}', [StudentController::class, 'destroy']);

        // Bulk Import Routes (no caching for file operations)
        Route::prefix('import')->group(function () {
            Route::get('template', [StudentController::class, 'downloadTemplate']);
            Route::post('/', [StudentController::class, 'import']);
            Route::get('/', [StudentController::class, 'getImports']);
            Route::get('{id}', [StudentController::class, 'getImport']);
            Route::get('{id}/errors', [StudentController::class, 'getImportErrors']);
            Route::post('{id}/cancel', [StudentController::class, 'cancelImport']);
            Route::delete('{id}', [StudentController::class, 'deleteImport']);
        });
    });

    // Parent-Student Management Routes
    Route::prefix('students/{studentId}/parents')->group(function () {
        Route::get('/', [\App\Http\Controllers\ParentStudentController::class, 'getStudentParents']);
        Route::post('assign', [\App\Http\Controllers\ParentStudentController::class, 'assignParent']);
        Route::post('create', [\App\Http\Controllers\ParentStudentController::class, 'createAndAssignParent']);
        Route::put('{parentId}', [\App\Http\Controllers\ParentStudentController::class, 'updateParentStudentRelationship']);
        Route::delete('{parentId}', [\App\Http\Controllers\ParentStudentController::class, 'removeParentFromStudent']);
    });

    // Parent Management Routes
    Route::prefix('parents')->group(function () {
        Route::get('/', [\App\Http\Controllers\ParentStudentController::class, 'getAllParents']);
        Route::post('/', [\App\Http\Controllers\ParentStudentController::class, 'createParent']);
        Route::get('{parentId}', [\App\Http\Controllers\ParentStudentController::class, 'getParentDetails']);
    });

    // Teacher Management Routes
    Route::prefix('teachers')->group(function () {
        Route::get('/', [TeacherController::class, 'index']);
        Route::post('/', [TeacherController::class, 'store']);
        Route::get('search', [TeacherController::class, 'search']);
        Route::get('generate-employee-id', [TeacherController::class, 'generateEmployeeId']);
        Route::get('{id}', [TeacherController::class, 'show']);
        Route::put('{id}', [TeacherController::class, 'update']);
        Route::delete('{id}', [TeacherController::class, 'destroy']);
        Route::get('{id}/classes', [TeacherController::class, 'getClasses']);
        Route::get('{id}/assignments', [TeacherController::class, 'getAssignments']);
        Route::get('{id}/dashboard-stats', [TeacherController::class, 'getDashboardStats']);
    });

    // School Settings Management Routes
    Route::prefix('school-settings')->group(function () {
        Route::get('/', [SchoolSettingController::class, 'index']);
        Route::post('/', [SchoolSettingController::class, 'store']);
        Route::get('category/{category}', [SchoolSettingController::class, 'getByCategory']);
        Route::get('{key}', [SchoolSettingController::class, 'show']);
        Route::put('{key}', [SchoolSettingController::class, 'update']);
        Route::delete('{key}', [SchoolSettingController::class, 'destroy']);
        Route::post('bulk', [SchoolSettingController::class, 'updateBulk']);
    });

    // Admission Number Management Routes
    Route::prefix('admission-number')->group(function () {
        Route::get('generate', [AdmissionNumberController::class, 'generate']);
        Route::post('generate-batch', [AdmissionNumberController::class, 'generateBatch']);
        Route::get('check-availability', [AdmissionNumberController::class, 'checkAvailability']);
        Route::get('settings', [AdmissionNumberController::class, 'getSettings']);
        Route::put('settings', [AdmissionNumberController::class, 'updateSettings']);
        Route::post('migrate-existing', [AdmissionNumberController::class, 'migrateExistingNumbers']);
    });

    // Roll Number Management Routes
    Route::prefix('roll-number')->group(function () {
        Route::get('next', [RollNumberController::class, 'getNext']);
        Route::get('available', [RollNumberController::class, 'getAvailable']);
        Route::get('check-availability', [RollNumberController::class, 'checkAvailability']);
        Route::get('statistics', [RollNumberController::class, 'getStatistics']);
        Route::post('generate-bulk', [RollNumberController::class, 'generateBulk']);
    });

    // Class Management Routes with intelligent caching
    Route::prefix('classes')->group(function () {
        // Cached read operations (shorter TTL for user-specific data)
        Route::middleware('api.cache:ttl:300,vary_by_school:true,vary_by_user:true')->group(function () {
            Route::get('/', [ClassController::class, 'index']);
            Route::get('teachers-classes', [ClassController::class, 'myClasses']);
            Route::get('my-classes-simple', [ClassController::class, 'getMyClassesSimplified']);
        });

        // Cached general data (longer TTL)
        Route::middleware('api.cache:ttl:600,vary_by_school:true')->group(function () {
            Route::get('simple', [ClassController::class, 'getSimpleClasses']);
            Route::get('{id}', [ClassController::class, 'show']);
            Route::get('{id}/students', [ClassController::class, 'getStudents']);
            Route::get('{id}/subjects', [ClassController::class, 'getSubjects']);
            Route::get('{id}/timetable', [ClassController::class, 'getTimetable']);
            Route::get('teacher/{teacherId}', [ClassController::class, 'getClassesByTeacher']);
            Route::get('promotion-mappings', [ClassController::class, 'getWithPromotionMappings']);
        });

        // Write operations (no caching)
        Route::post('/', [ClassController::class, 'store']);
        Route::put('{id}', [ClassController::class, 'update']);
        Route::delete('{id}', [ClassController::class, 'destroy']);
        Route::post('{id}/assign-students', [ClassController::class, 'assignStudents']);
        Route::post('{id}/assign-subjects', [ClassController::class, 'assignSubjects']);
        Route::put('{id}/promotion-mapping', [ClassController::class, 'setPromotionMapping']);
    });

    // Attendance Management Routes
    Route::prefix('attendance')->group(function () {
        // Cached read operations
        Route::middleware('api.cache:ttl:300,vary_by_school:true')->group(function () {
            Route::get('/', [AttendanceController::class, 'index']);
            Route::get('class/{classId}/report', [AttendanceController::class, 'getClassAttendanceReport']);
            Route::get('class/{classId}/date', [AttendanceController::class, 'getClassAttendanceByDate']);
            Route::get('student/{studentId}/report', [AttendanceController::class, 'getStudentAttendanceReport']);
        });

        // Write operations (no caching)
        Route::post('/', [AttendanceController::class, 'markSingleAttendance']);
        Route::post('bulk', [AttendanceController::class, 'markBulkAttendance']);
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
        Route::get('filter-options', [TimetableController::class, 'getFilterOptions']);

        // Bulk Timetable Operations
        Route::post('bulk/create', [TimetableController::class, 'createBulkTimetable']);
        Route::put('bulk/update', [TimetableController::class, 'updateBulkTimetable']);
        Route::put('bulk/replace', [TimetableController::class, 'replaceTimetable']);
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
    Route::get('subjects/teacher/{teacherId}', [SubjectController::class, 'getByTeacher']);

    // Assignment Management Routes
    Route::prefix('assignments')->group(function () {
        Route::get('/', [AssignmentController::class, 'index']);
        Route::post('/', [AssignmentController::class, 'store']);
        Route::get('statistics', [AssignmentController::class, 'statistics']);
        Route::get('teacher-dashboard', [AssignmentController::class, 'teacherDashboard']);
        Route::get('type/{type}', [AssignmentController::class, 'byType']);
        Route::get('class/{classId}/upcoming', [AssignmentController::class, 'upcomingByClass']);
        Route::get('class/{classId}', [AssignmentController::class, 'getByClassOptimized']);
        Route::get('student/{studentId}', [AssignmentController::class, 'studentAssignments']);
        Route::get('{id}', [AssignmentController::class, 'show']);
        Route::get('{id}/submission-overview', [AssignmentController::class, 'getSubmissionOverview']);
        Route::get('{assignmentId}/student/{studentId}/submission', [AssignmentController::class, 'getSubmissionDetail']);
        Route::put('{id}', [AssignmentController::class, 'update']);
        Route::delete('{id}', [AssignmentController::class, 'destroy']);
        Route::post('{id}/submit', [AssignmentController::class, 'submit']);
    });

    // Assignment Submission Routes
    Route::prefix('assignment-submissions')->group(function () {
        Route::post('{submissionId}/grade', [AssignmentController::class, 'gradeSubmission']);
        Route::post('{submissionId}/return-for-revision', [AssignmentController::class, 'returnSubmissionForRevision']);
        Route::get('{submissionId}/download', [AssignmentController::class, 'downloadSubmissionAttachment']);
    });

    // File Upload Routes (Generic for all modules)
    Route::prefix('uploads')->group(function () {
        Route::post('single', [FileUploadController::class, 'uploadSingle']);
        Route::post('multiple', [FileUploadController::class, 'uploadMultiple']);
        Route::delete('file', [FileUploadController::class, 'deleteFile']);
        Route::post('file-info', [FileUploadController::class, 'getFileInfo']);
        Route::post('regenerate-thumbnails', [FileUploadController::class, 'regenerateThumbnails']);
    });

    // Student Performance Routes
    Route::prefix('student-performance')->group(function () {
        Route::get('{studentId}/report', [StudentPerformanceController::class, 'getPerformanceReport']);
        Route::get('{studentId}/class-comparison', [StudentPerformanceController::class, 'getClassPerformanceComparison']);
    });

    // Class Performance Routes
    Route::prefix('class-performance')->group(function () {
        Route::get('{classId}/analytics', [StudentPerformanceController::class, 'getClassPerformanceAnalytics']);
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

    Route::prefix('gallery')->group(function () {
        // Get classes and events (smart endpoints - simple list or paginated based on query params)
        Route::get('classes', [GalleryController::class, 'getClasses']);
        Route::get('events', [GalleryController::class, 'getEvents']);

        // Filter options for gallery
        Route::get('filter-options', [GalleryController::class, 'getFilterOptions']);

        // Gallery statistics
        Route::get('stats', [GalleryController::class, 'getStats']);

        // Gallery albums
        Route::get('/', [GalleryController::class, 'index']);
        Route::post('/', [GalleryController::class, 'store']);
        Route::get('{id}', [GalleryController::class, 'show']);
        Route::put('{id}', [GalleryController::class, 'update']);
        Route::delete('{id}', [GalleryController::class, 'destroy']);

        // Media management
        Route::get('{albumId}/media', [GalleryController::class, 'getAlbumMedia']);
        Route::post('{albumId}/media', [GalleryController::class, 'addMedia']);
        Route::delete('{albumId}/media/{mediaId}', [GalleryController::class, 'deleteMedia']);
    });

    // Contact Form Management (Admin only)
    Route::prefix('admin/contact-submissions')->group(function () {
        Route::get('/', [ContactController::class, 'index']);
        Route::patch('{submission}/status', [ContactController::class, 'updateStatus']);
    });

    // Admin Menu Management Routes
    Route::prefix('admin/menus')->group(function () {
        Route::get('/', [\App\Http\Controllers\AdminMenuController::class, 'index']);
        Route::get('flat', [\App\Http\Controllers\AdminMenuController::class, 'getAllFlat']);
        Route::get('type/{type}', [\App\Http\Controllers\AdminMenuController::class, 'getByType']);
        Route::get('root-groups', [\App\Http\Controllers\AdminMenuController::class, 'getRootGroups']);
        Route::get('{menuId}/children', [\App\Http\Controllers\AdminMenuController::class, 'getChildren']);
        Route::get('{menuId}/breadcrumb', [\App\Http\Controllers\AdminMenuController::class, 'getBreadcrumb']);
        Route::get('search', [\App\Http\Controllers\AdminMenuController::class, 'search']);
        Route::post('/', [\App\Http\Controllers\AdminMenuController::class, 'store']);
        Route::put('{id}', [\App\Http\Controllers\AdminMenuController::class, 'update']);
        Route::delete('{id}', [\App\Http\Controllers\AdminMenuController::class, 'destroy']);
        Route::post('reorder', [\App\Http\Controllers\AdminMenuController::class, 'reorder']);
        Route::patch('{id}/toggle-status', [\App\Http\Controllers\AdminMenuController::class, 'toggleStatus']);
        Route::get('{id}', [\App\Http\Controllers\AdminMenuController::class, 'show']);
    });

    // Academic Year & Promotion System Routes
    Route::prefix('academic-years')->group(function () {
        Route::get('get-current', [\App\Http\Controllers\AcademicYearController::class, 'getCurrent']);
        Route::get('/', [\App\Http\Controllers\AcademicYearController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\AcademicYearController::class, 'store']);
        Route::get('{id}', [\App\Http\Controllers\AcademicYearController::class, 'show']);
        Route::put('{id}', [\App\Http\Controllers\AcademicYearController::class, 'update']);
        Route::delete('{id}', [\App\Http\Controllers\AcademicYearController::class, 'destroy']);
        Route::post('{id}/set-current', [\App\Http\Controllers\AcademicYearController::class, 'setCurrent']);
        Route::post('{id}/start-promotion', [\App\Http\Controllers\AcademicYearController::class, 'startPromotionPeriod']);
        Route::post('{id}/complete', [\App\Http\Controllers\AcademicYearController::class, 'complete']);
        Route::get('{id}/generate-next', [\App\Http\Controllers\AcademicYearController::class, 'generateNext']);
        Route::post('{id}/clone-criteria', [\App\Http\Controllers\AcademicYearController::class, 'cloneCriteria']);
    });

    Route::prefix('promotions')->group(function () {
        // Promotion Readiness & Validation
        Route::get('readiness/{academicYearId}', [\App\Http\Controllers\PromotionController::class, 'checkPromotionReadiness']);
        Route::get('consistency/{academicYearId}', [\App\Http\Controllers\PromotionController::class, 'validateDataConsistency']);

        // Promotion Criteria Management - with caching for read operations
        Route::middleware('api.cache:ttl:300,vary_by_school:true')->group(function () {
            Route::get('criteria/{academicYearId}', [\App\Http\Controllers\PromotionController::class, 'getCriteria']); // Supports pagination via ?page=1&per_page=15
        });
        Route::post('criteria', [\App\Http\Controllers\PromotionController::class, 'storeCriteria']);

        // Student Evaluation & Promotion (no caching for write operations)
        Route::post('evaluate-student', [\App\Http\Controllers\PromotionController::class, 'evaluateStudent']);
        Route::post('bulk-evaluate', [\App\Http\Controllers\PromotionController::class, 'bulkEvaluate']);
        Route::post('apply-promotions', [\App\Http\Controllers\PromotionController::class, 'applyPromotions']);

        // Statistics & Reports - with caching and pagination support
        Route::middleware('api.cache:ttl:180,vary_by_school:true')->group(function () {
            Route::get('statistics/{academicYearId}', [\App\Http\Controllers\PromotionController::class, 'getStatistics']);
            Route::get('students/{academicYearId}', [\App\Http\Controllers\PromotionController::class, 'getStudentPromotions']); // Supports pagination via ?page=1&per_page=15
            Route::get('batches/{academicYearId}', [\App\Http\Controllers\PromotionController::class, 'getBatches']); // Supports pagination via ?page=1&per_page=15
        });

        // Real-time batch progress tracking (no caching for live updates)
        Route::get('batches/{batchId}/progress', [\App\Http\Controllers\PromotionController::class, 'getBatchProgress']);

        // Manual Overrides (no caching for write operations)
        Route::post('{promotionId}/override', [\App\Http\Controllers\PromotionController::class, 'overrideDecision']);
    });

    // Fee Structure Management Routes
    Route::prefix('fee-structures')->group(function () {
        // Cached read operations
        Route::middleware('api.cache:ttl:300,vary_by_school:true')->group(function () {
            Route::get('/', [\App\Http\Controllers\FeeStructureController::class, 'index']); // Supports pagination and filtering
            Route::get('{id}', [\App\Http\Controllers\FeeStructureController::class, 'show']);
            Route::get('{id}/statistics', [\App\Http\Controllers\FeeStructureController::class, 'getStatistics']);
            Route::get('class/{classId}', [\App\Http\Controllers\FeeStructureController::class, 'getByClass']);
        });

        // Write operations (no caching)
        Route::post('/', [\App\Http\Controllers\FeeStructureController::class, 'store']);
        Route::put('{id}', [\App\Http\Controllers\FeeStructureController::class, 'update']);
        Route::delete('{id}', [\App\Http\Controllers\FeeStructureController::class, 'destroy']);
        Route::patch('{id}/toggle-status', [\App\Http\Controllers\FeeStructureController::class, 'toggleStatus']);
        Route::post('{id}/generate-student-fees', [\App\Http\Controllers\FeeStructureController::class, 'generateStudentFees']);
        Route::post('{id}/clone', [\App\Http\Controllers\FeeStructureController::class, 'clone']);
    });
});
