<?php

namespace App\Http\Controllers;

use App\Services\ParentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ParentController extends BaseController
{
    private ParentService $parentService;

    public function __construct(ParentService $parentService)
    {
        $this->parentService = $parentService;
    }

    /**
     * Get authenticated parent or return error response
     */
    private function getAuthenticatedParent(): array
    {
        $user = Auth::user();

        if ($user->user_type !== 'parent') {
            return [
                'success' => false,
                'response' => $this->errorResponse('Access denied. Only parents can access this resource.', null, 403)
            ];
        }

        $parent = $user->parent;
        if (!$parent) {
            return [
                'success' => false,
                'response' => $this->errorResponse('Parent profile not found.', null, 404)
            ];
        }

        return [
            'success' => true,
            'parent' => $parent
        ];
    }

    /**
     * Get parent's children list
     * 
     * @return JsonResponse
     */
    public function getChildren(): JsonResponse
    {
        try {
            $authResult = $this->getAuthenticatedParent();
            if (!$authResult['success']) {
                return $authResult['response'];
            }

            $children = $this->parentService->getParentChildren($authResult['parent']->id);

            return $this->successResponse([
                'students' => $children,
                'total_students' => count($children),
            ], 'Students retrieved successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve students.', $e->getMessage(), 500);
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
            $validated = $request->validate([
                'student_id' => 'required|integer|exists:students,id',
            ]);

            $authResult = $this->getAuthenticatedParent();
            if (!$authResult['success']) {
                return $authResult['response'];
            }

            $statistics = $this->parentService->getStudentStatistics(
                $authResult['parent']->id,
                $validated['student_id']
            );

            return $this->successResponse(
                array_merge($statistics, ['generated_at' => now()->toISOString()]),
                'Student statistics retrieved successfully.'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve student statistics.', $e->getMessage(), 500);
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

            $authResult = $this->getAuthenticatedParent();
            if (!$authResult['success']) {
                return $authResult['response'];
            }

            $attendance = $this->parentService->getStudentAttendance(
                $authResult['parent']->id,
                $validated['student_id'],
                $validated['month'] ?? null,
                $validated['year'] ?? null,
                $validated['limit'] ?? 30
            );

            return $this->successResponse($attendance, 'Student attendance retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve attendance data.', $e->getMessage(), 500);
        }
    }

    /**
     * Get student assignments with pagination
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
                'per_page' => 'nullable|integer|between:5,50',
                'page' => 'nullable|integer|min:1',
            ]);

            $authResult = $this->getAuthenticatedParent();
            if (!$authResult['success']) {
                return $authResult['response'];
            }

            // Set the current page for Laravel pagination
            if (isset($validated['page'])) {
                request()->merge(['page' => $validated['page']]);
            }

            $assignments = $this->parentService->getStudentAssignments(
                $authResult['parent']->id,
                $validated['student_id'],
                $validated['status'] ?? null,
                $validated['per_page'] ?? 15
            );

            return $this->successResponse($assignments, 'Student assignments retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve assignments.', $e->getMessage(), 500);
        }
    }

    /**
     * Get detailed assignment information for a student
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAssignmentDetails(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'student_id' => 'required|integer|exists:students,id',
                'assignment_id' => 'required|integer|exists:assignments,id',
            ]);

            $authResult = $this->getAuthenticatedParent();
            if (!$authResult['success']) {
                return $authResult['response'];
            }

            $assignmentDetails = $this->parentService->getAssignmentDetails(
                $authResult['parent']->id,
                $validated['student_id'],
                $validated['assignment_id']
            );

            // Use Resource for consistent data structure and nullable field handling
            return $this->successResponse(
                $assignmentDetails,
                'Assignment details retrieved successfully.'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve assignment details.', $e->getMessage(), 500);
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

            $authResult = $this->getAuthenticatedParent();
            if (!$authResult['success']) {
                return $authResult['response'];
            }

            // Clear cache and get fresh statistics
            $this->parentService->clearStudentStatsCache($authResult['parent']->id, $validated['student_id']);
            $statistics = $this->parentService->getStudentStatistics($authResult['parent']->id, $validated['student_id']);

            return $this->successResponse(
                array_merge($statistics, ['refreshed_at' => now()->toISOString()]),
                'Student statistics refreshed successfully.'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to refresh statistics.', $e->getMessage(), 500);
        }
    }
}
