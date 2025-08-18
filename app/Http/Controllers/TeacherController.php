<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Services\TeacherService;
use App\Http\Resources\TeacherResource;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TeacherController extends BaseController
{
    protected $teacherService;

    public function __construct(TeacherService $teacherService)
    {
        $this->teacherService = $teacherService;
    }

    /**
     * Get all teachers with filters and pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Check module access (uncomment if needed)
            if (!$this->checkModuleAccess('teacher-management')) {
                return $this->moduleAccessDenied();
            }

            $filters = $request->only([
                'search',
                'gender',
                'qualification',
                'specialization',
                'joining_date_from',
                'joining_date_to'
            ]);

            // Get pagination parameters
            $perPage = $request->get('per_page', 15);
            $perPage = max(1, min(100, (int)$perPage));

            $teachers = $this->teacherService->getAllTeachers($filters, $perPage);

            return $this->successResponse([
                'data' => TeacherResource::collection($teachers->items()),
                'pagination' => [
                    'current_page' => $teachers->currentPage(),
                    'last_page' => $teachers->lastPage(),
                    'per_page' => $teachers->perPage(),
                    'total' => $teachers->total(),
                    'from' => $teachers->firstItem(),
                    'to' => $teachers->lastItem(),
                    'has_more_pages' => $teachers->hasMorePages(),
                    'prev_page_url' => $teachers->previousPageUrl(),
                    'next_page_url' => $teachers->nextPageUrl(),
                ]
            ], 'Teachers retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Teacher index error: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Create a new teacher
     */
    public function store(StoreTeacherRequest $request): JsonResponse
    {
        try {
            $teacher = $this->teacherService->createTeacher($request->validated());

            return $this->successResponse(
                new TeacherResource($teacher),
                'Teacher created successfully',
                201
            );
        } catch (\Exception $e) {
            Log::error('Teacher creation error: ' . $e->getMessage());
            return $this->errorResponse('Failed to create teacher: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get specific teacher details
     */
    public function show($id): JsonResponse
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);

            return $this->successResponse(
                new TeacherResource($teacher),
                'Teacher details retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Teacher show error: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage(), null, 404);
        }
    }

    /**
     * Update teacher information
     */
    public function update(UpdateTeacherRequest $request, $id): JsonResponse
    {
        try {
            $data = $request->validated();

            if (empty($data)) {
                return $this->errorResponse('No update data provided', null, 422);
            }

            $teacher = $this->teacherService->updateTeacher($id, $data);

            return $this->successResponse(
                new TeacherResource($teacher),
                'Teacher updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Teacher update error: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Delete teacher
     */
    public function destroy($id): JsonResponse
    {
        try {
            $this->teacherService->deleteTeacher($id);

            return $this->successResponse(null, 'Teacher deleted successfully');
        } catch (\Exception $e) {
            Log::error('Teacher deletion error: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage(), null, 500);
        }
    }

    /**
     * Get teacher's classes
     */
    public function getClasses(Request $request, $id): JsonResponse
    {
        try {
            $classes = $this->teacherService->getTeacherClasses($id);

            return $this->successResponse($classes, 'Teacher classes retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Teacher classes error: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage(), null, 404);
        }
    }

    /**
     * Get teacher's assignments
     */
    public function getAssignments(Request $request, $id): JsonResponse
    {
        try {
            $status = $request->get('status');
            $perPage = max(1, min(50, (int)$request->get('per_page', 15)));

            $assignments = $this->teacherService->getTeacherAssignments($id, $status, $perPage);

            return $this->successResponse([
                'data' => $assignments->items(),
                'pagination' => [
                    'current_page' => $assignments->currentPage(),
                    'last_page' => $assignments->lastPage(),
                    'per_page' => $assignments->perPage(),
                    'total' => $assignments->total(),
                ]
            ], 'Teacher assignments retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Teacher assignments error: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage(), null, 404);
        }
    }

    /**
     * Get teacher dashboard statistics
     */
    public function getDashboardStats(Request $request, $id): JsonResponse
    {
        try {
            $stats = $this->teacherService->getTeacherDashboardStats($id);

            return $this->successResponse($stats, 'Teacher statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Teacher dashboard stats error: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage(), null, 404);
        }
    }

    /**
     * Search teachers
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2',
                'per_page' => 'sometimes|integer|min:1|max:50'
            ]);

            $searchTerm = $request->get('q');
            $perPage = $request->get('per_page', 15);

            $teachers = $this->teacherService->searchTeachers($searchTerm, $perPage);

            return $this->successResponse([
                'data' => TeacherResource::collection($teachers->items()),
                'pagination' => [
                    'current_page' => $teachers->currentPage(),
                    'last_page' => $teachers->lastPage(),
                    'per_page' => $teachers->perPage(),
                    'total' => $teachers->total(),
                ]
            ], 'Teacher search results retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Teacher search error: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Generate unique employee ID for the school
     */
    public function generateEmployeeId(Request $request): JsonResponse
    {
        try {
            $schoolId = $request->school_id; // From middleware
            $employeeId = $this->teacherService->generateEmployeeId($schoolId);

            return $this->successResponse([
                'employee_id' => $employeeId
            ], 'Employee ID generated successfully');
        } catch (\Exception $e) {
            Log::error('Employee ID generation error: ' . $e->getMessage());
            return $this->errorResponse('Failed to generate employee ID');
        }
    }
}
