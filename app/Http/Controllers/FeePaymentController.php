<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeePaymentRequest;
use App\Http\Resources\FeePaymentResource;
use App\Services\FeePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class FeePaymentController extends BaseController
{
    public function __construct(private FeePaymentService $feePaymentService) {}

    /**
     * Get all fee payments with pagination and filtering
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
                'student_id' => $request->get('student_id'),
                'class_id' => $request->get('class_id'),
                'payment_method' => $request->get('payment_method'),
                'status' => $request->get('status'),
                'date_range' => $request->get('date_range'),
                'search' => $request->get('search'),
            ];

            // Remove null filters
            $filters = array_filter($filters, fn($value) => $value !== null);

            $relations = ['studentFee.student', 'studentFee.feeStructure', 'receivedBy'];
            $feePayments = $this->feePaymentService->getAll($filters, $relations);

            return $this->successResponse(
                FeePaymentResource::collection($feePayments),
                'Fee payments retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error retrieving fee payments: ' . $e);
            return $this->errorResponse('Failed to retrieve fee payments', null, 500);
        }
    }

    /**
     * Get a specific fee payment by ID
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            $relations = ['studentFee.student', 'studentFee.feeStructure', 'receivedBy'];
            $feePayment = $this->feePaymentService->find($id, $relations);

            // Ensure the fee payment belongs to the current school
            if ($feePayment->studentFee->student->school_id !== $this->getSchoolId($request)) {
                return $this->errorResponse('Fee payment not found', null, 404);
            }

            return $this->successResponse(
                new FeePaymentResource($feePayment),
                'Fee payment retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Fee payment not found', null, 404);
        } catch (\Exception $e) {
            Log::error('Error retrieving fee payment: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve fee payment', null, 500);
        }
    }

    /**
     * Record a new fee payment
     */
    public function store(FeePaymentRequest $request): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            $data = $request->validated();
            $data['received_by'] = \Illuminate\Support\Facades\Auth::id();

            $feePayment = $this->feePaymentService->recordPayment($data);

            return $this->successResponse(
                new FeePaymentResource($feePayment),
                'Fee payment recorded successfully',
                201
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Error recording fee payment: ' . $e->getMessage());
            return $this->errorResponse('Failed to record fee payment', null, 500);
        }
    }

    /**
     * Update an existing fee payment
     */
    public function update(FeePaymentRequest $request, int $id): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            // Verify the fee payment belongs to the current school
            $existingPayment = $this->feePaymentService->find($id, ['studentFee.student']);
            if ($existingPayment->studentFee->student->school_id !== $this->getSchoolId($request)) {
                return $this->errorResponse('Fee payment not found', null, 404);
            }

            $data = $request->validated();
            $feePayment = $this->feePaymentService->updatePayment($id, $data);

            return $this->successResponse(
                new FeePaymentResource($feePayment),
                'Fee payment updated successfully'
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Fee payment not found', null, 404);
        } catch (\Exception $e) {
            Log::error('Error updating fee payment: ' . $e->getMessage());
            return $this->errorResponse('Failed to update fee payment', null, 500);
        }
    }

    /**
     * Delete a fee payment (soft delete)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            // Verify the fee payment belongs to the current school
            $feePayment = $this->feePaymentService->find($id, ['studentFee.student']);
            if ($feePayment->studentFee->student->school_id !== $this->getSchoolId($request)) {
                return $this->errorResponse('Fee payment not found', null, 404);
            }

            $this->feePaymentService->deletePayment($id);

            return $this->successResponse(null, 'Fee payment deleted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Fee payment not found', null, 404);
        } catch (\Exception $e) {
            Log::error('Error deleting fee payment: ' . $e->getMessage());
            return $this->errorResponse('Failed to delete fee payment', null, 500);
        }
    }

    /**
     * Generate fee receipt
     */
    public function generateReceipt(Request $request, int $id): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            $request->validate([
                'format' => 'sometimes|in:pdf,html',
                'download' => 'sometimes|boolean',
            ]);

            // Verify the fee payment belongs to the current school
            $feePayment = $this->feePaymentService->find($id, ['studentFee.student']);
            if ($feePayment->studentFee->student->school_id !== $this->getSchoolId($request)) {
                return $this->errorResponse('Fee payment not found', null, 404);
            }

            $format = $request->get('format', 'pdf');
            $download = $request->get('download', true);

            $receipt = $this->feePaymentService->generateReceipt($id, $format, $download);

            if ($format === 'html') {
                return $this->successResponse([
                    'receipt_html' => $receipt['content'],
                    'receipt_number' => $receipt['receipt_number'],
                ], 'Fee receipt generated successfully');
            }

            // For PDF, return download URL or base64 content
            return $this->successResponse([
                'download_url' => $receipt['download_url'],
                'receipt_number' => $receipt['receipt_number'],
                'file_size' => $receipt['file_size'],
            ], 'Fee receipt generated successfully');

        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Fee payment not found', null, 404);
        } catch (\Exception $e) {
            Log::error('Error generating fee receipt: ' . $e->getMessage());
            return $this->errorResponse('Failed to generate fee receipt', null, 500);
        }
    }

    /**
     * Get fee payment statistics
     */
    public function getPaymentStatistics(Request $request): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            $filters = [
                'school_id' => $this->getSchoolId($request),
                'academic_year_id' => $this->getCurrentAcademicYearId($request),
                'date_range' => $request->get('date_range'),
                'class_id' => $request->get('class_id'),
            ];

            $statistics = $this->feePaymentService->getPaymentStatistics($filters);

            return $this->successResponse($statistics, 'Fee payment statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error retrieving fee payment statistics: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve statistics', null, 500);
        }
    }

    /**
     * Bulk mark fees as paid
     */
    public function bulkMarkAsPaid(Request $request): JsonResponse
    {
        try {
            // Check if fee management module is active
            if (!$this->checkModuleAccess('fee-management')) {
                return $this->moduleAccessDenied();
            }

            $request->validate([
                'student_fee_ids' => 'required|array|min:1',
                'student_fee_ids.*' => 'required|integer|exists:student_fees,id',
                'payment_method' => 'required|in:cash,bank_transfer,cheque,card,online,upi',
                'payment_date' => 'required|date',
                'notes' => 'nullable|string|max:1000',
            ]);

            $data = $request->validated();
            $data['received_by'] = \Illuminate\Support\Facades\Auth::id();

            $result = $this->feePaymentService->bulkMarkAsPaid($data);

            return $this->successResponse($result, 'Fees marked as paid successfully');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Error in bulk fee payment: ' . $e->getMessage());
            return $this->errorResponse('Failed to process bulk payment', null, 500);
        }
    }

    /**
     * Get student fees with payment tracking
     */
    public function getStudentFeesWithPayments(Request $request): JsonResponse
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
                'payment_status' => $request->get('payment_status'),
                'fee_structure_id' => $request->get('fee_structure_id'),
            ];

            $filters = array_filter($filters, fn($value) => $value !== null);
            $studentFees = $this->feePaymentService->getStudentFeesWithPayments($filters);

            return $this->successResponse($studentFees, 'Student fees retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error retrieving student fees with payments: ' . $e->getMessage());
            return $this->errorResponse('Failed to retrieve student fees', null, 500);
        }
    }
}
