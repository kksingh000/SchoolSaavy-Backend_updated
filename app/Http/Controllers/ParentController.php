<?php

namespace App\Http\Controllers;

use App\Services\ParentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ParentController extends Controller
{
    private ParentService $parentService;

    public function __construct(ParentService $parentService)
    {
        $this->parentService = $parentService;
    }

    /**
     * Get parent's children list
     * 
     * @return JsonResponse
     */
    public function getChildren(): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($user->user_type !== 'parent') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only parents can access this resource.',
                ], 403);
            }

            $parent = $user->parent;
            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent profile not found.',
                ], 404);
            }

            $children = $this->parentService->getParentChildren($parent->id);

            return response()->json([
                'success' => true,
                'message' => 'Students retrieved successfully.',
                'data' => [
                    'students' => $children,
                    'total_students' => count($children),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve students.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get student statistics for parent dashboard
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStudentStatistics(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'student_id' => 'required|integer|exists:students,id',
            ]);

            $user = Auth::user();

            if ($user->user_type !== 'parent') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only parents can access this resource.',
                ], 403);
            }

            $parent = $user->parent;
            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent profile not found.',
                ], 404);
            }

            $studentId = $validated['student_id'];

            // Get comprehensive statistics
            $statistics = $this->parentService->getStudentStatistics($parent->id, $studentId);

            return response()->json([
                'success' => true,
                'message' => 'Student statistics retrieved successfully.',
                'data' => $statistics,
                'generated_at' => now()->toISOString(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student statistics.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get student attendance details
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStudentAttendance(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'student_id' => 'required|integer|exists:students,id',
                'month' => 'nullable|integer|between:1,12',
                'year' => 'nullable|integer|between:2020,' . (date('Y') + 1),
                'limit' => 'nullable|integer|between:10,100',
            ]);

            $user = Auth::user();

            if ($user->user_type !== 'parent') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only parents can access this resource.',
                ], 403);
            }

            $parent = $user->parent;
            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent profile not found.',
                ], 404);
            }

            // Verify parent-student relationship
            if (!$this->parentService->verifyParentStudentRelationship($parent->id, $validated['student_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student does not belong to this parent.',
                ], 403);
            }

            $month = $validated['month'] ?? now()->month;
            $year = $validated['year'] ?? now()->year;
            $limit = $validated['limit'] ?? 30;

            // Get attendance records
            $attendance = \App\Models\Attendance::where('student_id', $validated['student_id'])
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->orderBy('date', 'desc')
                ->limit($limit)
                ->get(['date', 'status', 'check_in_time', 'check_out_time', 'remarks']);

            return response()->json([
                'success' => true,
                'message' => 'Student attendance retrieved successfully.',
                'data' => [
                    'attendance_records' => $attendance,
                    'month' => $month,
                    'year' => $year,
                    'total_records' => $attendance->count(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get student assignments
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStudentAssignments(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'student_id' => 'required|integer|exists:students,id',
                'status' => 'nullable|string|in:pending,submitted,graded,overdue',
                'limit' => 'nullable|integer|between:10,50',
            ]);

            $user = Auth::user();

            if ($user->user_type !== 'parent') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only parents can access this resource.',
                ], 403);
            }

            $parent = $user->parent;
            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent profile not found.',
                ], 404);
            }

            // Verify parent-student relationship
            if (!$this->parentService->verifyParentStudentRelationship($parent->id, $validated['student_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student does not belong to this parent.',
                ], 403);
            }

            $studentId = $validated['student_id'];
            $status = $validated['status'] ?? null;
            $limit = $validated['limit'] ?? 20;

            // Get student's current class
            $student = \App\Models\Student::with('currentClass')->findOrFail($studentId);
            $currentClass = $student->currentClass()->first();

            if (!$currentClass) {
                return response()->json([
                    'success' => true,
                    'message' => 'Student is not assigned to any class.',
                    'data' => [
                        'assignments' => [],
                        'total_assignments' => 0,
                    ],
                ]);
            }

            // Build assignments query
            $assignmentsQuery = \App\Models\Assignment::where('class_id', $currentClass->id)
                ->where('is_active', true)
                ->with(['subject:id,name', 'teacher.user:id,name'])
                ->orderBy('due_date', 'desc')
                ->limit($limit);

            // Get assignments with submission status
            $assignments = $assignmentsQuery->get()->map(function ($assignment) use ($studentId) {
                $submission = \App\Models\AssignmentSubmission::where('assignment_id', $assignment->id)
                    ->where('student_id', $studentId)
                    ->first();

                $isOverdue = !$submission && $assignment->due_date < now();
                $assignmentStatus = $submission ? $submission->status : ($isOverdue ? 'overdue' : 'pending');

                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'subject' => $assignment->subject->name ?? 'N/A',
                    'teacher' => $assignment->teacher->user->name ?? 'N/A',
                    'assigned_date' => $assignment->assigned_date->format('Y-m-d'),
                    'due_date' => $assignment->due_date->format('Y-m-d'),
                    'max_marks' => $assignment->max_marks,
                    'status' => $assignmentStatus,
                    'submission' => $submission ? [
                        'submitted_at' => $submission->submitted_at?->format('Y-m-d H:i:s'),
                        'marks_obtained' => $submission->marks_obtained,
                        'grade_percentage' => $submission->grade_percentage,
                        'teacher_feedback' => $submission->teacher_feedback,
                        'is_late_submission' => $submission->is_late_submission,
                    ] : null,
                ];
            });

            // Filter by status if provided
            if ($status) {
                $assignments = $assignments->filter(function ($assignment) use ($status) {
                    return $assignment['status'] === $status;
                });
            }

            return response()->json([
                'success' => true,
                'message' => 'Student assignments retrieved successfully.',
                'data' => [
                    'assignments' => $assignments->values()->toArray(),
                    'total_assignments' => $assignments->count(),
                    'filtered_by' => $status ? ['status' => $status] : null,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assignments.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh student statistics cache
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function refreshStatistics(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'student_id' => 'required|integer|exists:students,id',
            ]);

            $user = Auth::user();

            if ($user->user_type !== 'parent') {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only parents can access this resource.',
                ], 403);
            }

            $parent = $user->parent;
            if (!$parent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent profile not found.',
                ], 404);
            }

            $studentId = $validated['student_id'];

            // Verify relationship
            if (!$this->parentService->verifyParentStudentRelationship($parent->id, $studentId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student does not belong to this parent.',
                ], 403);
            }

            // Clear cache and get fresh statistics
            $this->parentService->clearStudentStatsCache($parent->id, $studentId);
            $statistics = $this->parentService->getStudentStatistics($parent->id, $studentId);

            return response()->json([
                'success' => true,
                'message' => 'Student statistics refreshed successfully.',
                'data' => $statistics,
                'refreshed_at' => now()->toISOString(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh statistics.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
