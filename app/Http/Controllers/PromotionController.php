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
     * Get promotion criteria for academic year
     */
    public function getCriteria($academicYearId)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $criteria = $this->promotionService->getPromotionCriteria($academicYearId);

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
                $request->class_ids
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
     * Get student promotions for academic year
     */
    public function getStudentPromotions($academicYearId)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $promotions = $this->promotionService->getAll([
                'academic_year_id' => $academicYearId
            ], ['student', 'fromClass', 'toClass', 'evaluatedBy']);

            return $this->successResponse($promotions, 'Student promotions retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get promotion batches for academic year
     */
    public function getBatches($academicYearId)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            // This would be implemented in the service layer
            $batches = \App\Models\PromotionBatch::forSchool(auth()->user()->getSchool()->id)
                ->forAcademicYear($academicYearId)
                ->with(['createdBy', 'processedBy'])
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($batches, 'Promotion batches retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
