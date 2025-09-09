<?php

namespace App\Jobs;

use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\StudentFeePlan;
use App\Models\StudentFeePlanComponent;
use App\Jobs\GenerateStudentFeeInstallments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateDefaultStudentFeePlans implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The fee structure ID for which to create student plans
     *
     * @var int
     */
    protected $feeStructureId;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     *
     * @param int $feeStructureId
     * @return void
     */
    public function __construct(int $feeStructureId)
    {
        $this->feeStructureId = $feeStructureId;
        $this->onQueue('fee-processing');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("Starting default student fee plan creation for fee structure ID: {$this->feeStructureId}");

        $feeStructure = FeeStructure::with(['components.masterComponent', 'class'])
            ->findOrFail($this->feeStructureId);

        // Get all students in this class for the current academic year
        $students = Student::whereHas('classes', function ($query) use ($feeStructure) {
            $query->where('class_rooms.id', $feeStructure->class_id)
                  ->where('class_student.is_active', true);
        })
        ->where('school_id', $feeStructure->school_id)
        ->where('is_active', true)
        ->get();

        Log::info("Found " . $students->count() . " students in class {$feeStructure->class->name}");

        $createdPlans = 0;
        $skippedPlans = 0;

        foreach ($students as $student) {
            // Check if student already has a fee plan for this fee structure
            $existingPlan = StudentFeePlan::where('school_id', $feeStructure->school_id)
                ->where('student_id', $student->id)
                ->where('fee_structure_id', $this->feeStructureId)
                ->exists();

            if ($existingPlan) {
                Log::info("Student {$student->id} already has a fee plan for this structure, skipping");
                $skippedPlans++;
                continue;
            }

            try {
                // Create student fee plan
                $studentFeePlan = StudentFeePlan::create([
                    'school_id' => $feeStructure->school_id,
                    'student_id' => $student->id,
                    'fee_structure_id' => $this->feeStructureId,
                    'start_date' => now(),
                    'end_date' => null,
                    'is_active' => true,
                ]);

                // Add only required components
                $requiredComponents = $feeStructure->components->filter(function ($component) {
                    return $component->is_required || 
                           ($component->masterComponent && $component->masterComponent->is_required);
                });

                foreach ($requiredComponents as $component) {
                    StudentFeePlanComponent::create([
                        'student_fee_plan_id' => $studentFeePlan->id,
                        'component_id' => $component->id,
                        'is_active' => true,
                    ]);
                }

                // Dispatch job to generate installments for this student
                GenerateStudentFeeInstallments::dispatch($studentFeePlan)->onQueue('fee-processing');

                Log::info("Created fee plan for student {$student->id} ({$student->first_name} {$student->last_name}) with " . $requiredComponents->count() . " required components");
                $createdPlans++;

            } catch (\Exception $e) {
                Log::error("Failed to create fee plan for student {$student->id}: " . $e->getMessage());
            }
        }

        Log::info("Default student fee plan creation completed. Created: {$createdPlans}, Skipped: {$skippedPlans}");
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error("Default student fee plan creation failed for fee structure ID: {$this->feeStructureId}", [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
