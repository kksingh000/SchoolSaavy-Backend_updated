<?php

namespace App\Jobs;

use App\Models\FeeInstallment;
use App\Models\StudentFeePlan;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateStudentFeeInstallments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The StudentFeePlan instance.
     *
     * @var \App\Models\StudentFeePlan
     */
    protected $studentFeePlan;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\StudentFeePlan  $studentFeePlan
     * @return void
     */
    public function __construct(StudentFeePlan $studentFeePlan)
    {
        $this->studentFeePlan = $studentFeePlan;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('Starting installment generation for StudentFeePlan ID: ' . $this->studentFeePlan->id);
        
        try {
            // Load the necessary relationships if not already loaded
            if (!$this->studentFeePlan->relationLoaded('components')) {
                $this->studentFeePlan->load(['components.component', 'feeStructure']);
            }
            
            $schoolId = $this->studentFeePlan->school_id;
            
            // Delete any existing installments for this plan
            FeeInstallment::where('student_fee_plan_id', $this->studentFeePlan->id)->delete();
            
            // Generate installments for each active component
            foreach ($this->studentFeePlan->components as $planComponent) {
                if (!$planComponent->is_active) {
                    Log::info("Skipping inactive component ID: {$planComponent->component_id} for StudentFeePlan ID: {$this->studentFeePlan->id}");
                    continue; // Skip inactive components
                }
                
                $component = $planComponent->component;
                
                // Use custom amount if specified, otherwise use default component amount
                $amount = $planComponent->custom_amount ?? $component->amount;
                
                // Get start date from plan or use current date
                $startDate = $this->studentFeePlan->start_date ?? now();
                
                Log::info("Generating installments for component: {$component->component_name}, frequency: {$component->frequency}, amount: {$amount}");
                
                // Generate installments based on frequency
                switch ($component->frequency) {
                    case 'Monthly':
                        $this->generateMonthlyInstallments($schoolId, $component, $startDate, $amount);
                        break;
                        
                    case 'Quarterly':
                        $this->generateQuarterlyInstallments($schoolId, $component, $startDate, $amount);
                        break;
                        
                    case 'Yearly':
                        $this->generateYearlyInstallment($schoolId, $component, $startDate, $amount);
                        break;
                        
                    case 'One-Time':
                        $this->generateOneTimeInstallment($schoolId, $component, $startDate, $amount);
                        break;
                        
                    default:
                        Log::warning("Unknown frequency '{$component->frequency}' for component ID: {$component->id}");
                        break;
                }
            }
            
            Log::info('Successfully generated installments for StudentFeePlan ID: ' . $this->studentFeePlan->id);
        } catch (\Exception $e) {
            Log::error('Error generating installments: ' . $e->getMessage(), [
                'student_fee_plan_id' => $this->studentFeePlan->id,
                'exception' => $e,
            ]);
            
            throw $e; // Rethrow to trigger job retry
        }
    }
    
    /**
     * Generate monthly installments for a component
     */
    private function generateMonthlyInstallments($schoolId, $component, $startDate, $totalAmount)
    {
        // Generate 12 monthly installments
        $monthlyAmount = $totalAmount / 12;
        
        for ($i = 0; $i < 12; $i++) {
            $dueDate = Carbon::parse($startDate)->addMonths($i);
            $this->createInstallment(
                $schoolId,
                $this->studentFeePlan->id,
                $component->id,
                $i + 1,
                $dueDate,
                $monthlyAmount
            );
        }
    }
    
    /**
     * Generate quarterly installments for a component
     */
    private function generateQuarterlyInstallments($schoolId, $component, $startDate, $totalAmount)
    {
        // Generate 4 quarterly installments
        $quarterlyAmount = $totalAmount / 4;
        
        for ($i = 0; $i < 4; $i++) {
            $dueDate = Carbon::parse($startDate)->addMonths($i * 3);
            $this->createInstallment(
                $schoolId,
                $this->studentFeePlan->id,
                $component->id,
                $i + 1,
                $dueDate,
                $quarterlyAmount
            );
        }
    }
    
    /**
     * Generate a single yearly installment for a component
     */
    private function generateYearlyInstallment($schoolId, $component, $startDate, $amount)
    {
        $this->createInstallment(
            $schoolId,
            $this->studentFeePlan->id,
            $component->id,
            1,
            Carbon::parse($startDate),
            $amount
        );
    }
    
    /**
     * Generate a single one-time installment for a component
     */
    private function generateOneTimeInstallment($schoolId, $component, $startDate, $amount)
    {
        $this->createInstallment(
            $schoolId,
            $this->studentFeePlan->id,
            $component->id,
            1,
            Carbon::parse($startDate),
            $amount
        );
    }
    
    /**
     * Create a single installment record
     */
    private function createInstallment($schoolId, $feePlanId, $componentId, $installmentNo, $dueDate, $amount)
    {
        // If the due date is in the past, mark as overdue
        $status = $dueDate->isPast() ? 'Overdue' : 'Pending';
        
        return FeeInstallment::create([
            'school_id' => $schoolId,
            'student_fee_plan_id' => $feePlanId,
            'component_id' => $componentId,
            'installment_no' => $installmentNo,
            'due_date' => $dueDate,
            'amount' => $amount,
            'status' => $status,
            'paid_amount' => 0,
        ]);
    }
}
