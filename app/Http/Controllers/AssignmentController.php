<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Event;
use App\Models\Student;
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
     * Get specific assignment with optimized submission data
     */
    public function show($id): JsonResponse
    {
        if (!$this->checkModuleAccess('assignment-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $startTime = microtime(true);

            // Use optimized method for better performance
            $assignment = $this->assignmentService->getAssignmentWithOptimizedSubmissions($id);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Prepare response data
            $responseData = [
                'assignment' => [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'instructions' => $assignment->instructions,
                    'type' => $assignment->type,
                    'status' => $assignment->status,
                    'assigned_date' => $assignment->assigned_date->format('Y-m-d'),
                    'due_date' => $assignment->due_date->format('Y-m-d'),
                    'due_time' => $assignment->due_time ? $assignment->due_time->format('H:i') : null,
                    'max_marks' => $assignment->max_marks,
                    'attachments' => $assignment->formatted_attachments,
                    'allow_late_submission' => $assignment->allow_late_submission,
                    'grading_criteria' => $assignment->grading_criteria,
                    'is_active' => $assignment->is_active,
                    'is_overdue' => $assignment->is_overdue,
                    'days_until_due' => $assignment->days_until_due,
                    'can_be_edited' => $assignment->canBeEdited(),
                    'can_be_deleted' => $assignment->canBeDeleted(),
                    'can_accept_submissions' => $assignment->canAcceptSubmissions(),
                    'created_at' => $assignment->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $assignment->updated_at->format('Y-m-d H:i:s'),

                    // Related data
                    'teacher' => [
                        'id' => $assignment->teacher->id,
                        'name' => $assignment->teacher->user->name,
                        'email' => $assignment->teacher->user->email,
                    ],
                    'class' => [
                        'id' => $assignment->class->id,
                        'name' => $assignment->class->name,
                        'section' => $assignment->class->section,
                        'grade_level' => $assignment->class->grade_level,
                    ],
                    'subject' => [
                        'id' => $assignment->subject->id,
                        'name' => $assignment->subject->name,
                        'code' => $assignment->subject->code,
                    ],
                ],

                // Optimized submission statistics
                'submission_statistics' => $assignment->submission_statistics,

                // Lightweight submissions (no content field for performance)
                'submissions' => $assignment->lightweight_submissions,

                // Performance metadata
                'meta' => [
                    'execution_time_ms' => $executionTime,
                    'total_submissions' => $assignment->lightweight_submissions->count(),
                ]
            ];

            return $this->successResponse(
                $responseData,
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

            // Authorization check: Verify the authenticated user has access to this student
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $studentId = $validated['student_id'];
            
            // If user is a parent, verify they have access to this student
            if ($user->user_type === 'parent') {
                $hasAccess = DB::table('parent_student')
                    ->where('parent_id', $user->parent->id)
                    ->where('student_id', $studentId)
                    ->exists();
                    
                if (!$hasAccess) {
                    return $this->errorResponse('You do not have permission to submit assignments for this student.', null, 403);
                }
            }
            
            // If user is a teacher or admin, verify the student belongs to their school
            if (in_array($user->user_type, ['teacher', 'admin', 'school_admin'])) {
                $schoolId = $user->getSchoolId();
                $student = Student::find($studentId);
                
                if (!$student || $student->school_id !== $schoolId) {
                    return $this->errorResponse('You do not have permission to submit assignments for this student.', null, 403);
                }
            }

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
                'marks_obtained' => 'nullable|numeric|min:0',
                'teacher_feedback' => 'nullable|string|max:2000',
                'grading_details' => 'nullable|array',
            ]);

            // Custom validation: Either marks or feedback must be provided
            if (is_null($validated['marks_obtained']) && empty($validated['teacher_feedback'])) {
                return $this->errorResponse('Either marks or teacher feedback must be provided for grading.');
            }

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
     * Return assignment submission for revision
     */
    public function returnSubmissionForRevision(Request $request, $submissionId): JsonResponse
    {
        if (!$this->checkModuleAccess('assignment-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $validated = $request->validate([
                'teacher_feedback' => 'required|string|max:1000',
            ]);

            $submission = $this->assignmentService->returnSubmissionForRevision(
                $submissionId,
                $validated['teacher_feedback']
            );

            return $this->successResponse(
                new AssignmentSubmissionResource($submission),
                'Assignment returned for revision successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get detailed submission by student ID and assignment ID
     * This endpoint fetches full submission content and attachments
     */
    public function getSubmissionDetail($assignmentId, $studentId): JsonResponse
    {
        if (!$this->checkModuleAccess('assignment-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $submission = $this->assignmentService->getStudentSubmission($assignmentId, $studentId);

            if (!$submission) {
                return $this->errorResponse('Submission not found', 404);
            }

            return $this->successResponse(
                new AssignmentSubmissionResource($submission),
                'Submission details retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Download/view submission attachment file
     * This provides secure access to submission files with proper validation
     */
    public function downloadSubmissionAttachment($submissionId, Request $request): JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        if (!$this->checkModuleAccess('assignment-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $filename = $request->query('filename');
            $action = $request->query('action', 'download'); // download or view

            if (!$filename) {
                return $this->errorResponse('Filename parameter is required');
            }

            // Get submission with assignment to validate school access
            $submission = AssignmentSubmission::whereHas('assignment', function ($query) use ($request) {
                $schoolId = $request->school_id ?? (Auth::user()->teacher?->school_id ?? Auth::user()->schoolAdmin?->school_id);
                $query->where('school_id', $schoolId);
            })->findOrFail($submissionId);

            // Validate that the filename exists in submission attachments
            $attachments = $submission->attachments;
            if (!$attachments || !is_array($attachments)) {
                return $this->errorResponse('No attachments found for this submission');
            }

            $attachmentData = null;
            foreach ($attachments as $attachment) {
                if (is_array($attachment) && isset($attachment['filename']) && $attachment['filename'] === $filename) {
                    $attachmentData = $attachment;
                    break;
                } elseif (is_string($attachment) && basename($attachment) === $filename) {
                    $attachmentData = ['path' => $attachment, 'name' => $filename];
                    break;
                }
            }

            if (!$attachmentData) {
                return $this->errorResponse('Attachment file not found');
            }

            $filePath = $attachmentData['path'] ?? $attachmentData;

            // Clean the path and make it relative to storage
            $filePath = ltrim($filePath, '/');
            if (!str_starts_with($filePath, 'storage/')) {
                $filePath = 'storage/' . $filePath;
            }

            $fullPath = public_path($filePath);

            if (!file_exists($fullPath)) {
                return $this->errorResponse('File not found on server');
            }

            $originalName = $attachmentData['name'] ?? $filename;
            $mimeType = $attachmentData['mime_type'] ?? mime_content_type($fullPath);

            if ($action === 'view') {
                // Return file for viewing in browser
                return response()->file($fullPath, [
                    'Content-Type' => $mimeType,
                    'Content-Disposition' => 'inline; filename="' . $originalName . '"'
                ]);
            } else {
                // Return file for download
                return response()->download($fullPath, $originalName, [
                    'Content-Type' => $mimeType,
                ]);
            }
        } catch (\Exception $e) {
            return $this->errorResponse('Error accessing file: ' . $e->getMessage());
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
