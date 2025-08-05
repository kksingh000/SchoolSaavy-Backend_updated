<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Event;
use App\Services\AssignmentService;
use App\Http\Resources\AssignmentResource;
use App\Http\Resources\AssignmentSubmissionResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssignmentController extends BaseController
{
    protected $assignmentService;

    public function __construct(AssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    /**
     * Get all assignments
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('assignment-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $filters = $request->only([
                'teacher_id',
                'class_id',
                'subject_id',
                'type',
                'status',
                'due_date_from',
                'due_date_to'
            ]);

            $assignments = $this->assignmentService->getAll(
                $filters,
                ['teacher.user', 'class', 'subject']
            );

            return $this->successResponse(
                AssignmentResource::collection($assignments),
                'Assignments retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Create new assignment
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('assignment-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $validated = $request->validate([
                'class_id' => 'required|exists:classes,id',
                'subject_id' => 'required|exists:subjects,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'instructions' => 'nullable|string',
                'type' => 'required|in:homework,project,quiz,classwork,assessment',
                'status' => 'in:draft,published',
                'assigned_date' => 'required|date',
                'due_date' => 'required|date|after_or_equal:assigned_date',
                'due_time' => 'nullable|date_format:H:i',
                'max_marks' => 'nullable|integer|min:1|max:1000',
                'attachments' => 'nullable|array',
                'allow_late_submission' => 'boolean',
                'grading_criteria' => 'nullable|string',
            ]);

            $assignment = $this->assignmentService->createAssignment($validated);

            return $this->successResponse(
                new AssignmentResource($assignment),
                'Assignment created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get specific assignment
     */
    public function show($id): JsonResponse
    {
        if (!$this->checkModuleAccess('assignment-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $assignment = $this->assignmentService->getAssignmentWithSubmissions($id);

            return $this->successResponse(
                new AssignmentResource($assignment),
                'Assignment retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Update assignment
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!$this->checkModuleAccess('assignment-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $validated = $request->validate([
                'class_id' => 'exists:classes,id',
                'subject_id' => 'exists:subjects,id',
                'title' => 'string|max:255',
                'description' => 'nullable|string',
                'instructions' => 'nullable|string',
                'type' => 'in:homework,project,quiz,classwork,assessment',
                'status' => 'in:draft,published,completed,graded',
                'assigned_date' => 'date',
                'due_date' => 'date|after_or_equal:assigned_date',
                'due_time' => 'nullable|date_format:H:i',
                'max_marks' => 'nullable|integer|min:1|max:1000',
                'attachments' => 'nullable|array',
                'allow_late_submission' => 'boolean',
                'grading_criteria' => 'nullable|string',
            ]);

            $assignment = $this->assignmentService->updateAssignment($id, $validated);

            return $this->successResponse(
                new AssignmentResource($assignment),
                'Assignment updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Delete assignment
     */
    public function destroy($id): JsonResponse
    {
        if (!$this->checkModuleAccess('assignment-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $this->assignmentService->deleteAssignment($id);

            return $this->successResponse(
                null,
                'Assignment deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Submit assignment
     */
    public function submit(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'student_id' => 'required|exists:students,id',
                'content' => 'nullable|string',
                'attachments' => 'nullable|array',
            ]);

            $submission = $this->assignmentService->submitAssignment(
                $id,
                $validated['student_id'],
                $validated
            );

            return $this->successResponse(
                new AssignmentSubmissionResource($submission),
                'Assignment submitted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Grade assignment submission
     */
    public function gradeSubmission(Request $request, $submissionId): JsonResponse
    {
        if (!$this->checkModuleAccess('assignment-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $validated = $request->validate([
                'marks_obtained' => 'required|numeric|min:0',
                'teacher_feedback' => 'nullable|string',
                'grading_details' => 'nullable|array',
            ]);

            $submission = $this->assignmentService->gradeSubmission($submissionId, $validated);

            return $this->successResponse(
                new AssignmentSubmissionResource($submission),
                'Assignment graded successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get teacher dashboard
     */
    public function teacherDashboard(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('assignment-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $user = Auth::user();
            $teacher = $user->teacher;

            if (!$teacher) {
                return $this->errorResponse('Teacher profile not found');
            }

            // Get basic dashboard data from service
            $teacherId = $request->input('teacher_id', $teacher->id);
            $dashboard = $this->assignmentService->getTeacherDashboard($teacherId);

            // Add the new metrics
            $additionalMetrics = $this->getTeacherDashboardMetrics($teacherId, $request->school_id);

            // Merge all data
            $dashboard['dashboard_metrics'] = $additionalMetrics;

            return $this->successResponse(
                $dashboard,
                'Teacher dashboard retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get additional teacher dashboard metrics
     */
    private function getTeacherDashboardMetrics($teacherId, $schoolId): array
    {
        // 1. Total Classes (where teacher is class teacher)
        $totalClasses = DB::table('classes')
            ->where('class_teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->count();

        // 2. Total Students (across all teacher's classes) - OPTIMIZED
        $totalStudents = DB::table('class_student')
            ->whereIn('class_id', function ($query) use ($teacherId, $schoolId) {
                $query->select('id')
                    ->from('classes')
                    ->where('class_teacher_id', $teacherId)
                    ->where('school_id', $schoolId)
                    ->where('is_active', true);
            })
            ->where('is_active', true)
            ->distinct('student_id')
            ->count('student_id');

        // 3. Total Assignments (created by this teacher)
        $totalAssignments = Assignment::where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->count();

        // 4. Total Events for Today
        $todaysEvents = Event::where('school_id', $schoolId)
            ->whereDate('event_date', Carbon::today())
            ->where(function ($query) {
                $query->whereJsonContains('target_audience', 'all')
                    ->orWhereJsonContains('target_audience', 'teachers');
            })
            ->count();

        return [
            'total_classes' => $totalClasses,
            'total_students' => $totalStudents,
            'total_assignments' => $totalAssignments,
            'total_events_today' => $todaysEvents,
        ];
    }

    /**
     * Get upcoming assignments for a class
     */
    public function upcomingByClass($classId, Request $request): JsonResponse
    {
        try {
            $days = $request->input('days', 7);
            $assignments = $this->assignmentService->getUpcomingAssignments($classId, $days);

            return $this->successResponse(
                AssignmentResource::collection($assignments),
                'Upcoming assignments retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get assignments for a student
     */
    public function studentAssignments($studentId, Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status']);
            $assignments = $this->assignmentService->getStudentAssignments($studentId, $filters);

            return $this->successResponse(
                AssignmentResource::collection($assignments),
                'Student assignments retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get assignment statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('assignment-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $filters = $request->only(['date_from', 'date_to']);
            $statistics = $this->assignmentService->getAssignmentStatistics($filters);

            return $this->successResponse(
                $statistics,
                'Assignment statistics retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get assignments by type
     */
    public function byType($type): JsonResponse
    {
        if (!$this->checkModuleAccess('assignment-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $assignments = $this->assignmentService->getAll(
                ['type' => $type],
                ['teacher.user', 'class', 'subject']
            );

            return $this->successResponse(
                AssignmentResource::collection($assignments),
                "Assignments of type '{$type}' retrieved successfully"
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get assignment submission overview for all students in class
     */
    public function getSubmissionOverview($assignmentId): JsonResponse
    {
        if (!$this->checkModuleAccess('assignment-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $overview = $this->assignmentService->getAssignmentSubmissionOverview($assignmentId);

            return $this->successResponse(
                $overview,
                'Assignment submission overview retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get assignments by class ID with submission statistics (OPTIMIZED)
     */
    public function getByClassOptimized($classId, Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('assignment-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $startTime = microtime(true);

            // Get filters from request
            $filters = [
                'status' => $request->input('status'), // Removed default 'published' - now shows all by default
                'type' => $request->input('type'),
                'search' => $request->input('search'),
                'per_page' => $request->input('per_page', 15)
            ];

            // Use service method for business logic
            $result = $this->assignmentService->getAssignmentsByClassOptimized(
                $classId,
                $filters,
                $request->school_id
            );

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Add execution time to meta
            $result['meta']['execution_time_ms'] = $executionTime;

            return $this->successResponse(
                $result,
                'Class assignments retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
