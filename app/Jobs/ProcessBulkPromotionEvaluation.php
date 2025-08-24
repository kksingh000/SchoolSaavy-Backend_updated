<?php

namespace App\Jobs;

use App\Models\PromotionBatch;
use App\Models\Student;
use App\Services\PromotionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessBulkPromotionEvaluation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout
    public $tries = 3;
    public $maxExceptions = 5;

    protected $batchId;
    protected $academicYearId;
    protected $classIds;
    protected $userId;
    protected $schoolId;
    protected $targetClassIds;

    /**
     * Create a new job instance.
     */
    public function __construct($batchId, $academicYearId, $classIds = null, $userId = null, $schoolId = null, $targetClassIds = null)
    {
        $this->batchId = $batchId;
        $this->academicYearId = $academicYearId;
        $this->classIds = $classIds;
        $this->userId = $userId;
        $this->schoolId = $schoolId;
        $this->targetClassIds = $targetClassIds;
    }

    /**
     * Execute the job.
     */
    public function handle(PromotionService $promotionService)
    {
        try {
            $batch = PromotionBatch::findOrFail($this->batchId);

            // Mark batch as processing if not already
            if ($batch->status !== 'processing') {
                $batch->update(['status' => 'processing']);
                $batch->markAsStarted($this->userId);
            }

            // Get students to evaluate
            $studentsQuery = Student::where('school_id', $this->schoolId)
                ->whereHas('classes', function ($query) {
                    $query->where('class_student.academic_year_id', $this->academicYearId)
                        ->where('class_student.is_active', true);

                    if ($this->classIds) {
                        $query->whereIn('classes.id', $this->classIds);
                    }
                });

            $students = $studentsQuery->get();
            $totalStudents = $students->count();

            // Update batch with total students count
            $batch->update(['total_students' => $totalStudents]);

            $processed = 0;
            $promoted = 0;
            $failed = 0;
            $pending = 0;

            Log::info("Starting bulk promotion evaluation", [
                'batch_id' => $this->batchId,
                'total_students' => $totalStudents,
                'academic_year_id' => $this->academicYearId
            ]);

            foreach ($students as $student) {
                try {
                    // Process individual student evaluation
                    $promotion = $promotionService->evaluateStudentForBatch(
                        $student->id,
                        $this->academicYearId,
                        $this->userId,
                        $this->targetClassIds
                    );

                    $processed++;

                    // Count promotion results
                    if ($promotion->isPromoted()) {
                        $promoted++;
                    } elseif ($promotion->isFailed()) {
                        $failed++;
                    } else {
                        $pending++;
                    }

                    // Update batch progress every 10 students
                    if ($processed % 10 === 0) {
                        $batch->updateProgress($processed, $promoted, $failed, $pending);
                        $batch->addToProcessingLog("Processed {$processed}/{$totalStudents} students");

                        Log::info("Bulk evaluation progress", [
                            'batch_id' => $this->batchId,
                            'processed' => $processed,
                            'total' => $totalStudents,
                            'promoted' => $promoted,
                            'failed' => $failed
                        ]);
                    }
                } catch (Exception $e) {
                    $pending++;
                    $errorMessage = "Failed to evaluate student {$student->name} (ID: {$student->id}): " . $e->getMessage();
                    $batch->addError($errorMessage);

                    Log::error('Student evaluation failed in batch', [
                        'student_id' => $student->id,
                        'batch_id' => $this->batchId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Final batch update
            $batch->updateProgress($processed, $promoted, $failed, $pending);
            $batch->markAsCompleted();

            Log::info("Bulk promotion evaluation completed", [
                'batch_id' => $this->batchId,
                'processed' => $processed,
                'promoted' => $promoted,
                'failed' => $failed,
                'pending' => $pending
            ]);
        } catch (Exception $e) {
            $batch = PromotionBatch::find($this->batchId);
            if ($batch) {
                $batch->markAsFailed($e->getMessage());
            }

            Log::error('Bulk promotion evaluation job failed', [
                'batch_id' => $this->batchId,
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
        $batch = PromotionBatch::find($this->batchId);
        if ($batch) {
            $batch->markAsFailed('Job failed: ' . $exception->getMessage());
        }

        Log::error('Bulk promotion evaluation job permanently failed', [
            'batch_id' => $this->batchId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
