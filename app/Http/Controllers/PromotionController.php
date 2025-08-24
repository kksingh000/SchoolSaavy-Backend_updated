<?php

namespace App\Http\Controllers;

use App\Services\PromotionService;
use App\Http\Requests\PromotionCriteriaRequest;
use App\Http\Requests\BulkPromotionRequest;
use Illuminate\Http\Request;

class PromotionController extends BaseController
{
    public function __construct(
        private PromotionService $promotionService
    ) {}

    /**
     * Get promotion criteria for academic year with pagination
     */
    public function getCriteria(Request $request, $academicYearId)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            // Use paginated method
            $perPage = $request->get('per_page', 15);
            $filters = [
                'from_class_id' => $request->get('from_class_id'),
                'to_class_id' => $request->get('to_class_id'),
                'status' => $request->get('status', 'all'),
                'search' => $request->get('search')
            ];

            $criteria = $this->promotionService->getCriteriaPaginated($academicYearId, $perPage, $filters);

            return $this->successResponse($criteria, 'Promotion criteria retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Create or update promotion criteria
     */
    public function storeCriteria(PromotionCriteriaRequest $request)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $criteria = $this->promotionService->createPromotionCriteria($request->validated());

            return $this->successResponse($criteria, 'Promotion criteria saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Evaluate single student for promotion
     */
    public function evaluateStudent(Request $request)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $request->validate([
                'student_id' => 'required|exists:students,id',
                'academic_year_id' => 'required|exists:academic_years,id'
            ]);

            $promotion = $this->promotionService->evaluateStudent(
                $request->student_id,
                $request->academic_year_id
            );

            return $this->successResponse($promotion, 'Student evaluated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Bulk evaluate students for promotion
     */
    public function bulkEvaluate(BulkPromotionRequest $request)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $batch = $this->promotionService->bulkEvaluateStudents(
                $request->academic_year_id,
                $request->class_ids,
                null, // userId will be set in service
                $request->target_class_ids
            );

            return $this->successResponse($batch, 'Bulk evaluation started successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Apply promotion decisions
     */
    public function applyPromotions(Request $request)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $request->validate([
                'academic_year_id' => 'required|exists:academic_years,id',
                'promotion_ids' => 'sometimes|array',
                'promotion_ids.*' => 'exists:student_promotions,id'
            ]);

            $appliedCount = $this->promotionService->applyPromotions(
                $request->academic_year_id,
                $request->promotion_ids
            );

            return $this->successResponse(
                ['applied_promotions' => $appliedCount],
                "Successfully applied {$appliedCount} promotions"
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get promotion statistics
     */
    public function getStatistics($academicYearId)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $statistics = $this->promotionService->getPromotionStatistics($academicYearId);

            return $this->successResponse($statistics, 'Promotion statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Override promotion decision
     */
    public function overrideDecision(Request $request, $promotionId)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $request->validate([
                'new_status' => 'required|in:promoted,conditionally_promoted,failed,transferred',
                'reason' => 'required|string|max:500'
            ]);

            $promotion = $this->promotionService->overridePromotionDecision(
                $promotionId,
                $request->new_status,
                $request->reason
            );

            return $this->successResponse($promotion, 'Promotion decision overridden successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get student promotions for academic year with pagination
     */
    public function getStudentPromotions(Request $request, $academicYearId)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            // Check if pagination is requested
            if ($request->has('page') || $request->has('per_page')) {
                // Use paginated method
                $perPage = $request->get('per_page', 15);
                $filters = [
                    'class_id' => $request->get('class_id'),
                    'promotion_status' => $request->get('promotion_status'),
                    'search' => $request->get('search')
                ];

                $promotions = $this->promotionService->getStudentPromotionsPaginated($academicYearId, $perPage, $filters);
            } else {
                // Use original method for backward compatibility
                $promotions = $this->promotionService->getAll([
                    'academic_year_id' => $academicYearId
                ], ['student', 'fromClass', 'toClass', 'evaluatedBy']);
            }

            return $this->successResponse($promotions, 'Student promotions retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get promotion batches for academic year with pagination
     */
    public function getBatches(Request $request, $academicYearId)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            // Check if pagination is requested
            if ($request->has('page') || $request->has('per_page')) {
                // Use paginated method
                $perPage = $request->get('per_page', 15);
                $filters = [
                    'status' => $request->get('status'),
                    'search' => $request->get('search')
                ];

                $batches = $this->promotionService->getBatchesPaginated($academicYearId, $perPage, $filters);
            } else {
                // Use original method for backward compatibility
                $schoolId = $this->getSchoolId($request);
                $batches = \App\Models\PromotionBatch::where('school_id', $schoolId)
                    ->forAcademicYear($academicYearId)
                    ->with(['createdBy', 'processedBy'])
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            return $this->successResponse($batches, 'Promotion batches retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get detailed progress for a specific promotion batch
     */
    public function getBatchProgress(Request $request, $batchId)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $schoolId = $this->getSchoolId($request);

            $batch = \App\Models\PromotionBatch::where('school_id', $schoolId)
                ->with(['createdBy', 'processedBy', 'academicYear'])
                ->findOrFail($batchId);

            // Get recent processing logs (last 20 entries)
            $recentLogs = collect($batch->processing_log ?? [])->take(-20);

            // Calculate additional metrics
            $batchProgress = [
                'id' => $batch->id,
                'batch_name' => $batch->batch_name,
                'description' => $batch->description,
                'status' => $batch->status,
                'status_display' => $batch->getStatusDisplay(),

                // Progress metrics
                'total_students' => $batch->total_students,
                'processed_students' => $batch->processed_students,
                'promoted_students' => $batch->promoted_students,
                'failed_students' => $batch->failed_students,
                'pending_students' => $batch->pending_students,

                // Calculated percentages
                'progress_percentage' => $batch->getProgressPercentage(),
                'promotion_rate' => $batch->getPromotionRate(),
                'failure_rate' => $batch->getFailureRate(),

                // Timing information
                'processing_time' => $batch->getProcessingTime(),
                'processing_started_at' => $batch->processing_started_at,
                'processing_completed_at' => $batch->processing_completed_at,

                // Associated data
                'academic_year' => [
                    'id' => $batch->academicYear->id,
                    'name' => $batch->academicYear->name,
                    'year' => $batch->academicYear->year
                ],
                'created_by' => [
                    'id' => $batch->createdBy->id,
                    'name' => $batch->createdBy->name,
                    'email' => $batch->createdBy->email
                ],
                'processed_by' => $batch->processedBy ? [
                    'id' => $batch->processedBy->id,
                    'name' => $batch->processedBy->name,
                    'email' => $batch->processedBy->email
                ] : null,

                // Class filters applied
                'class_filters' => $batch->class_filters,

                // Recent processing logs
                'recent_logs' => $recentLogs,

                // Error information
                'has_errors' => !empty($batch->error_log),
                'error_summary' => $batch->error_log ? substr($batch->error_log, -500) : null, // Last 500 chars

                // Timestamps
                'created_at' => $batch->created_at,
                'updated_at' => $batch->updated_at
            ];

            return $this->successResponse($batchProgress, 'Batch progress retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Check promotion readiness with comprehensive validation
     */
    public function checkPromotionReadiness(Request $request, $academicYearId)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $readiness = $this->promotionService->getPromotionReadiness($academicYearId);

            return $this->successResponse($readiness, 'Promotion readiness check completed');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Validate data consistency 
     */
    public function validateDataConsistency(Request $request, $academicYearId)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $consistency = $this->promotionService->checkDataConsistency($academicYearId);

            return $this->successResponse($consistency, 'Data consistency check completed');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
