<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeeStructureRequest;
use App\Http\Resources\FeeStructureResource;
use App\Services\FeeStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class FeeStructureController extends BaseController
{
    public function __construct(private FeeStructureService $feeStructureService) {}

    /**
     * Get all fee structures with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            $filters = [
                'school_id' => $this->getSchoolId($request),
                'academic_year_id' => $this->getCurrentAcademicYearId($request),
                'class_id' => $request->get('class_id'),
                'is_active' => $request->get('is_active', true),
                'search' => $request->get('search'),
            ];

            // Remove null filters
            $filters = array_filter($filters, fn($value) => $value !== null);

            $relations = ['school', 'academicYear', 'class', 'studentFees'];
            $feeStructures = $this->feeStructureService->getAll($filters, $relations);

            return $this->successResponse(
                FeeStructureResource::collection($feeStructures),
                'Fee structures retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error retrieving fee structures: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve fee structures', null, 500);
        }
    }

    /**
     * Get a specific fee structure by ID
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            $relations = ['school', 'academicYear', 'class', 'studentFees.student', 'studentFees.payments'];
            $feeStructure = $this->feeStructureService->find($id, $relations);

            // Ensure the fee structure belongs to the current school
            if ($feeStructure->school_id !== $this->getSchoolId($request)) {
                return $this->errorResponse('Fee structure not found', null, 404);
            }

            return $this->successResponse(
                new FeeStructureResource($feeStructure),
                'Fee structure retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Fee structure not found', null, 404);
        } catch (\Exception $e) {
            Log::error('Error retrieving fee structure: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve fee structure', null, 500);
        }
    }

    /**
     * Create a new fee structure
     * Note: school_id and academic_year_id are automatically set from authenticated user context
     */
    public function store(FeeStructureRequest $request): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            $data = $request->validated();
            $data['school_id'] = $this->getSchoolId($request);
            $data['academic_year_id'] = $this->getCurrentAcademicYearId($request);

            $feeStructure = $this->feeStructureService->createFeeStructure($data);

            return $this->successResponse(
                new FeeStructureResource($feeStructure),
                'Fee structure created successfully',
                201
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Error creating fee structure: ' . $e->getMessage());
            return $this->errorResponse('Failed to create fee structure', null, 500);
        }
    }

    /**
     * Update an existing fee structure
     */
    public function update(FeeStructureRequest $request, int $id): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            // Verify the fee structure belongs to the current school
            $existingFeeStructure = $this->feeStructureService->find($id);
            if ($existingFeeStructure->school_id !== $this->getSchoolId($request)) {
                return $this->errorResponse('Fee structure not found', null, 404);
            }

            $data = $request->validated();
            $feeStructure = $this->feeStructureService->updateFeeStructure($id, $data);

            return $this->successResponse(
                new FeeStructureResource($feeStructure),
                'Fee structure updated successfully'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Fee structure not found', null, 404);
        } catch (\Exception $e) {
            Log::error('Error updating fee structure: ' . $e->getMessage());
            return $this->errorResponse('Failed to update fee structure', null, 500);
        }
    }

    /**
     * Delete a fee structure (soft delete)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            // Verify the fee structure belongs to the current school
            $feeStructure = $this->feeStructureService->find($id);
            if ($feeStructure->school_id !== $this->getSchoolId($request)) {
                return $this->errorResponse('Fee structure not found', null, 404);
            }

            $this->feeStructureService->deleteFeeStructure($id);

            return $this->successResponse(null, 'Fee structure deleted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Fee structure not found', null, 404);
        } catch (\Exception $e) {
            Log::error('Error deleting fee structure: ' . $e->getMessage());
            return $this->errorResponse('Failed to delete fee structure', null, 500);
        }
    }

    /**
     * Toggle active status of a fee structure
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            // Verify the fee structure belongs to the current school
            $feeStructure = $this->feeStructureService->find($id);
            if ($feeStructure->school_id !== $this->getSchoolId($request)) {
                return $this->errorResponse('Fee structure not found', null, 404);
            }

            $updatedFeeStructure = $this->feeStructureService->toggleStatus($id);

            return $this->successResponse(
                new FeeStructureResource($updatedFeeStructure),
                'Fee structure status updated successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Fee structure not found', null, 404);
        } catch (\Exception $e) {
            Log::error('Error updating fee structure status: ' . $e->getMessage());
            return $this->errorResponse('Failed to update fee structure status', null, 500);
        }
    }

    /**
     * Generate student fees for a specific fee structure
     */
    public function generateStudentFees(Request $request, int $id): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            // Verify the fee structure belongs to the current school
            $feeStructure = $this->feeStructureService->find($id);
            if ($feeStructure->school_id !== $this->getSchoolId($request)) {
                return $this->errorResponse('Fee structure not found', null, 404);
            }

            $result = $this->feeStructureService->generateStudentFeesForStructure($id);

            return $this->successResponse(
                $result,
                'Student fees generated successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Fee structure not found', null, 404);
        } catch (\Exception $e) {
            Log::error('Error generating student fees: ' . $e->getMessage());
            return $this->errorResponse('Failed to generate student fees', null, 500);
        }
    }

    /**
     * Get fee structure statistics
     */
    public function getStatistics(Request $request, int $id): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            // Verify the fee structure belongs to the current school
            $feeStructure = $this->feeStructureService->find($id);
            if ($feeStructure->school_id !== $this->getSchoolId($request)) {
                return $this->errorResponse('Fee structure not found', null, 404);
            }

            $statistics = $this->feeStructureService->getFeeStructureStatistics($id);

            return $this->successResponse($statistics, 'Fee structure statistics retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Fee structure not found', null, 404);
        } catch (\Exception $e) {
            Log::error('Error retrieving fee structure statistics: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve statistics', null, 500);
        }
    }

    /**
     * Get fee structures for a specific class
     */
    public function getByClass(Request $request, int $classId): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            $filters = [
                'school_id' => $this->getSchoolId($request),
                'academic_year_id' => $this->getCurrentAcademicYearId($request),
                'class_id' => $classId,
                'is_active' => true,
            ];

            $relations = ['academicYear', 'class'];
            $feeStructures = $this->feeStructureService->getAll($filters, $relations);

            return $this->successResponse(
                FeeStructureResource::collection($feeStructures),
                'Class fee structures retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error retrieving class fee structures: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve class fee structures', null, 500);
        }
    }

    /**
     * Clone a fee structure to a new academic year or class
     */
    public function clone(Request $request, int $id): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            $request->validate([
                'target_academic_year_id' => 'sometimes|exists:academic_years,id',
                'target_class_id' => 'sometimes|exists:classes,id',
                'name_suffix' => 'sometimes|string|max:50',
            ]);

            // Verify the fee structure belongs to the current school
            $feeStructure = $this->feeStructureService->find($id);
            if ($feeStructure->school_id !== $this->getSchoolId($request)) {
                return $this->errorResponse('Fee structure not found', null, 404);
            }

            $cloneData = [
                'target_academic_year_id' => $request->get('target_academic_year_id'),
                'target_class_id' => $request->get('target_class_id'),
                'name_suffix' => $request->get('name_suffix', ' (Copy)'),
            ];

            $clonedFeeStructure = $this->feeStructureService->cloneFeeStructure($id, $cloneData);

            return $this->successResponse(
                new FeeStructureResource($clonedFeeStructure),
                'Fee structure cloned successfully',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Fee structure not found', null, 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Error cloning fee structure: ' . $e->getMessage());
            return $this->errorResponse('Failed to clone fee structure', null, 500);
        }
    }
}
