<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeeStructureRequest;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\StudentFeePlanRequest;
use App\Http\Resources\FeeStructureResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\StudentFeePlanResource;
use App\Http\Resources\StudentFeeDetailsResource;
use App\Http\Resources\StudentFeeSummaryResource;
use App\Services\FeeManagementService;
use Illuminate\Http\Request;

class FeeManagementController extends BaseController
{
    protected $feeManagementService;

    public function __construct(FeeManagementService $feeManagementService)
    {
        $this->feeManagementService = $feeManagementService;
    }

    /**
     * Display a listing of fee structures
     */
    public function listFeeStructures(Request $request)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        $filters = [
            'academic_year_id' => $request->query('academic_year_id'),
            'class_id' => $request->query('class_id'),
            'search' => $request->query('search'),
        ];

        $perPage = $request->query('per_page', 15);
        
        $feeStructures = $this->feeManagementService->getAllFeeStructures($filters);

        return $this->successResponse(
            FeeStructureResource::collection($feeStructures),
            'Fee structures retrieved successfully'
        );
    }

    /**
     * Store a new fee structure
     */
    public function storeFeeStructure(FeeStructureRequest $request)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $feeStructure = $this->feeManagementService->createFeeStructure($request->validated());
            
            return $this->successResponse(
                new FeeStructureResource($feeStructure),
                'Fee structure created successfully',
                201
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Fee structure creation error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to create fee structure: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Display the specified fee structure
     */
    public function showFeeStructure($id)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        $feeStructure = $this->feeManagementService->getFeeStructure($id);

        return $this->successResponse(
            new FeeStructureResource($feeStructure),
            'Fee structure retrieved successfully'
        );
    }

    /**
     * Update the specified fee structure
     */
    public function updateFeeStructure(FeeStructureRequest $request, $id)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        $feeStructure = $this->feeManagementService->updateFeeStructure($id, $request->validated());

        return $this->successResponse(
            new FeeStructureResource($feeStructure),
            'Fee structure updated successfully'
        );
    }

    /**
     * Remove the specified fee structure
     */
    public function destroyFeeStructure($id)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        $this->feeManagementService->deleteFeeStructure($id);

        return $this->successResponse(
            null,
            'Fee structure deleted successfully'
        );
    }

    /**
     * Display a listing of student fee plans
     */
    public function listStudentFeePlans(Request $request)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        $filters = [
            'academic_year_id' => $request->query('academic_year_id'),
            'student_id' => $request->query('student_id'),
            'class_id' => $request->query('class_id'),
            'is_active' => $request->query('is_active'),
            'fee_structure_id' => $request->query('fee_structure_id'),
        ];

        $perPage = $request->query('per_page', 15);
        
        $studentFeePlans = $this->feeManagementService->getAllStudentFeePlans($filters);

        return $this->successResponse(
            StudentFeePlanResource::collection($studentFeePlans),
            'Student fee plans retrieved successfully'
        );
    }

    /**
     * Store a new student fee plan
     */
    public function storeStudentFeePlan(StudentFeePlanRequest $request)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        $studentFeePlan = $this->feeManagementService->createStudentFeePlan($request->validated());

        return $this->successResponse(
            new StudentFeePlanResource($studentFeePlan),
            'Student fee plan created successfully',
            201
        );
    }

    /**
     * Display the specified student fee plan
     */
    public function showStudentFeePlan($id)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        $studentFeePlan = $this->feeManagementService->getStudentFeePlan($id);

        return $this->successResponse(
            new StudentFeePlanResource($studentFeePlan),
            'Student fee plan retrieved successfully'
        );
    }

    /**
     * Update the specified student fee plan
     */
    public function updateStudentFeePlan(StudentFeePlanRequest $request, $id)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        $studentFeePlan = $this->feeManagementService->updateStudentFeePlan($id, $request->validated());

        return $this->successResponse(
            new StudentFeePlanResource($studentFeePlan),
            'Student fee plan updated successfully'
        );
    }

    /**
     * Remove the specified student fee plan
     */
    public function destroyStudentFeePlan($id)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        $this->feeManagementService->deleteStudentFeePlan($id);

        return $this->successResponse(
            null,
            'Student fee plan deleted successfully'
        );
    }

    /**
     * Process a new payment
     */
    public function processPayment(PaymentRequest $request)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        $payment = $this->feeManagementService->processPayment($request->validated());

        return $this->successResponse(
            new PaymentResource($payment),
            'Payment processed successfully',
            201
        );
    }

    /**
     * Get student payment history
     */
    public function getStudentPaymentHistory(Request $request, $studentId)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        $filters = [
            'student_id' => $studentId,
            'academic_year_id' => $request->query('academic_year_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'status' => $request->query('status'),
        ];

        $perPage = $request->query('per_page', 15);
        
        $payments = $this->feeManagementService->getAllPayments($filters);

        return $this->successResponse(
            PaymentResource::collection($payments),
            'Student payment history retrieved successfully'
        );
    }

    /**
     * Get student fee summary
     */
    public function getStudentFeeSummary(Request $request, $studentId)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        $filters = [
            'student_id' => $studentId,
            'academic_year_id' => $request->query('academic_year_id'),
        ];
        
        $feeSummary = $this->feeManagementService->getStudentFeeSummary($studentId);

        return $this->successResponse(
            $feeSummary,
            'Student fee summary retrieved successfully'
        );
    }

    /**
     * Get due installments
     */
    public function getDueInstallments(Request $request)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        $classId = $request->query('class_id');
        $academicYearId = $request->query('academic_year_id');
        $studentId = $request->query('student_id');
        $consolidated = $request->query('consolidated', 'true');
        $perPage = $request->query('per_page', 15);
        
        try {
            // If student_id is provided or consolidated=false, use the original approach
            if ($studentId || strtolower($consolidated) === 'false') {
                $filters = [
                    'class_id' => $classId,
                    'student_id' => $studentId,
                    'academic_year_id' => $academicYearId,
                    'due_date' => $request->query('due_date'),
                    'status' => $request->query('status'),
                ];
                
                $dueInstallments = $this->feeManagementService->getAllFeeInstallments($filters);
                
                return $this->successResponse(
                    $dueInstallments,
                    'Due installments retrieved successfully'
                );
            }
            
            // Use the new consolidated approach
            $consolidatedDues = $this->feeManagementService->getConsolidatedStudentDues(
                $classId,
                $academicYearId,
                $perPage
            );
            
            return $this->successResponse(
                \App\Http\Resources\ConsolidatedStudentDueResource::collection($consolidatedDues),
                'Consolidated student dues retrieved successfully'
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Due installments error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
            return $this->errorResponse('Failed to retrieve due installments: ' . $e->getMessage());
        }
    }
    
    /**
     * Get student fee details in a student-centric format
     */
    public function getStudentFeeDetails(Request $request)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        $classId = $request->query('class_id');
        $studentId = $request->query('student_id');
        $academicYearId = $request->query('academic_year_id');
        $perPage = $request->query('per_page', 15);
        
        try {
            $feePlans = $this->feeManagementService->getStudentFeeDetails(
                $classId, 
                $studentId, 
                $academicYearId, 
                $perPage
            );
            
            return $this->paginatedSuccessResponse(
                $feePlans,
                StudentFeeSummaryResource::collection($feePlans),
                'Student fee details retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve student fee details: ' . $e->getMessage());
        }
    }
    
    /**
     * Get detailed fee information for a specific student
     */
    public function getDetailedStudentFeeDetails(Request $request, $studentId)
    {
        if (!$this->checkModuleAccess('fee-management')) {
            return $this->moduleAccessDenied();
        }

        $academicYearId = $request->query('academic_year_id');
        
        try {
            $feePlan = $this->feeManagementService->getDetailedStudentFeeDetails(
                $studentId, 
                $academicYearId
            );
            
            if (!$feePlan) {
                return $this->errorResponse('No fee plan found for this student', null, 404);
            }
            
            return $this->successResponse(
                new StudentFeeDetailsResource($feePlan),
                'Detailed student fee information retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve detailed student fee information: ' . $e->getMessage());
        }
    }

    /**
     * Get all master fee components
     */
    public function getMasterFeeComponents(Request $request)
    {
        try {
            $filters = $request->only(['category', 'is_required', 'search']);
            $masterComponents = $this->feeManagementService->getMasterFeeComponents($filters);
            
            return $this->successResponse($masterComponents, 'Master fee components retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve master fee components: ' . $e->getMessage());
        }
    }

    /**
     * Get a specific master fee component
     */
    public function getMasterFeeComponent($id)
    {
        try {
            $masterComponent = $this->feeManagementService->getMasterFeeComponent($id);
            
            return $this->successResponse($masterComponent, 'Master fee component retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve master fee component: ' . $e->getMessage());
        }
    }
}
