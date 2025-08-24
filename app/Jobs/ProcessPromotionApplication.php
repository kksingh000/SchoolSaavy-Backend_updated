<?php

namespace App\Jobs;

use App\Models\StudentPromotion;
use App\Models\PromotionBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessPromotionApplication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes timeout
    public $tries = 3;

    protected $academicYearId;
    protected $promotionIds;
    protected $schoolId;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($academicYearId, $promotionIds = null, $schoolId = null, $userId = null)
    {
        $this->academicYearId = $academicYearId;
        $this->promotionIds = $promotionIds;
        $this->schoolId = $schoolId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        DB::beginTransaction();
        try {
            $query = StudentPromotion::where('school_id', $this->schoolId)
                ->where('academic_year_id', $this->academicYearId)
                ->whereIn('promotion_status', ['promoted', 'conditionally_promoted']);

            if ($this->promotionIds) {
                $query->whereIn('id', $this->promotionIds);
            }

            $promotions = $query->get();
            $appliedCount = 0;

            Log::info("Starting promotion application", [
                'academic_year_id' => $this->academicYearId,
                'total_promotions' => $promotions->count(),
                'school_id' => $this->schoolId
            ]);

            foreach ($promotions as $promotion) {
                try {
                    if ($promotion->to_class_id) {
                        // Process the promotion (move student to new class)
                        $promotion->applyPromotionDecision(
                            $promotion->promotion_status,
                            $promotion->promotion_reason,
                            $this->userId
                        );
                        $appliedCount++;

                        Log::info("Applied promotion", [
                            'student_id' => $promotion->student_id,
                            'from_class_id' => $promotion->from_class_id,
                            'to_class_id' => $promotion->to_class_id,
                            'promotion_id' => $promotion->id
                        ]);
                    }
                } catch (Exception $e) {
                    Log::error('Failed to apply promotion', [
                        'promotion_id' => $promotion->id,
                        'student_id' => $promotion->student_id,
                        'error' => $e->getMessage()
                    ]);
                    // Continue with other promotions
                }
            }

            DB::commit();

            Log::info("Promotion application completed", [
                'academic_year_id' => $this->academicYearId,
                'applied_count' => $appliedCount,
                'total_promotions' => $promotions->count()
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Promotion application job failed', [
                'academic_year_id' => $this->academicYearId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception)
    {
        Log::error('Promotion application job permanently failed', [
            'academic_year_id' => $this->academicYearId,
            'promotion_ids' => $this->promotionIds,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
