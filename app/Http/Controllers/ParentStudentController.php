<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Parents;
use App\Models\User;
use App\Services\ParentStudentService;
use App\Http\Requests\ParentStudent\AssignParentRequest;
use App\Http\Requests\ParentStudent\CreateParentRequest;
use App\Http\Requests\ParentStudent\UpdateParentStudentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ParentStudentController extends BaseController
{
    protected $parentStudentService;

    public function __construct(ParentStudentService $parentStudentService)
    {
        $this->parentStudentService = $parentStudentService;
    }

    /**
     * Get all parents of a student
     */
    public function getStudentParents($studentId): JsonResponse
    {
        if (!$this->checkModuleAccess('student-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $parents = $this->parentStudentService->getStudentParents($studentId);
            return $this->successResponse($parents, 'Student parents retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve student parents: ' . $e->getMessage());
        }
    }

    /**
     * Assign existing parent to student
     */
    public function assignParent(AssignParentRequest $request, $studentId): JsonResponse
    {
        if (!$this->checkModuleAccess('student-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $validated = $request->validated();

            $result = $this->parentStudentService->assignParentToStudent(
                $studentId,
                $validated['parent_id'],
                $validated['relationship'],
                $validated['is_primary'] ?? false
            );

            return $this->successResponse($result, 'Parent assigned to student successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to assign parent: ' . $e->getMessage());
        }
    }

    /**
     * Create new parent and assign to student
     */
    public function createAndAssignParent(CreateParentRequest $request, $studentId): JsonResponse
    {
        if (!$this->checkModuleAccess('student-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $validated = $request->validated();

            $result = $this->parentStudentService->createAndAssignParent($studentId, $validated);

            return $this->successResponse($result, 'Parent created and assigned successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create and assign parent: ' . $e->getMessage());
        }
    }

    /**
     * Update parent-student relationship
     */
    public function updateParentStudentRelationship(UpdateParentStudentRequest $request, $studentId, $parentId): JsonResponse
    {
        if (!$this->checkModuleAccess('student-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $validated = $request->validated();

            $result = $this->parentStudentService->updateParentStudentRelationship(
                $studentId,
                $parentId,
                $validated
            );

            return $this->successResponse($result, 'Parent-student relationship updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update relationship: ' . $e->getMessage());
        }
    }

    /**
     * Remove parent from student
     */
    public function removeParentFromStudent($studentId, $parentId): JsonResponse
    {
        if (!$this->checkModuleAccess('student-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $this->parentStudentService->removeParentFromStudent($studentId, $parentId);
            return $this->successResponse(null, 'Parent removed from student successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to remove parent: ' . $e->getMessage());
        }
    }

    /**
     * Get all parents (for dropdown/selection)
     */
    public function getAllParents(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('student-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $perPage = $request->input('per_page', 15);
            $perPage = is_numeric($perPage) && $perPage >= 1 && $perPage <= 100 ? (int)$perPage : 15;

            $search = $request->input('search');

            $parents = $this->parentStudentService->getAllParents($search, $perPage);
            return $this->successResponse($parents, 'Parents retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve parents: ' . $e->getMessage());
        }
    }

    /**
     * Get parent details with their children
     */
    public function getParentDetails($parentId): JsonResponse
    {
        if (!$this->checkModuleAccess('student-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $parent = $this->parentStudentService->getParentDetails($parentId);
            return $this->successResponse($parent, 'Parent details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve parent details: ' . $e->getMessage());
        }
    }

    /**
     * Create new parent (standalone, without student assignment)
     */
    public function createParent(\App\Http\Requests\ParentStudent\StoreParentRequest $request): JsonResponse
    {
        if (!$this->checkModuleAccess('student-management')) {
            return $this->moduleAccessDenied();
        }
        try {
            $validated = $request->validated();
            $parent = $this->parentStudentService->createParent($validated);

            return $this->successResponse($parent, 'Parent created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create parent: ' . $e->getMessage());
        }
    }
}
