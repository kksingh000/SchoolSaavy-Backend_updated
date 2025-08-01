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
                'max_marks' => 'required|integer|min:1|max:1000',
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
                'max_marks' => 'integer|min:1|max:1000',
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
        // 1. Total Classes
        $totalClasses = DB::table('class_teacher')
            ->join('classes', 'class_teacher.class_id', '=', 'classes.id')
            ->where('class_teacher.teacher_id', $teacherId)
            ->where('classes.school_id', $schoolId)
            ->count();

        // 2. Total Students (across all teacher's classes)
        $totalStudents = DB::table('class_student')
            ->join('class_teacher', 'class_student.class_id', '=', 'class_teacher.class_id')
            ->join('students', 'class_student.student_id', '=', 'students.id')
            ->where('class_teacher.teacher_id', $teacherId)
            ->where('students.school_id', $schoolId)
            ->distinct('students.id')
            ->count('students.id');

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
}
