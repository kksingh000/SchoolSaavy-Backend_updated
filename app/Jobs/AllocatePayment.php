<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\FeeInstallment;
use App\Models\PaymentAllocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AllocatePayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The payment instance to allocate.
     *
     * @var int
     */
    protected $paymentId;

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
     * @param int $paymentId
     * @return void
     */
    public function __construct(int $paymentId)
    {
        $this->paymentId = $paymentId;
        $this->onQueue('fee-processing');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("Starting payment allocation for payment ID: {$this->paymentId}");

        $payment = Payment::findOrFail($this->paymentId);
        
        // Only allocate successful payments
        if ($payment->status !== 'Success') {
            Log::info("Payment ID {$this->paymentId} has status '{$payment->status}', skipping allocation");
            return;
        }
        
        $remainingAmount = (float)$payment->amount;
        Log::info("Payment amount to allocate: {$remainingAmount}");
        
        // Get pending installments sorted by due date (oldest first)
        $pendingInstallments = FeeInstallment::whereHas('studentFeePlan', function ($query) use ($payment) {
            $query->where('student_id', $payment->student_id);
        })
        ->where(function ($query) {
            $query->where('status', 'Pending')
                  ->orWhere('status', 'Overdue');
        })
        ->orderBy('due_date', 'asc')
        ->get();
        
        Log::info("Found " . $pendingInstallments->count() . " pending installments for allocation");
        
        foreach ($pendingInstallments as $installment) {
            if ($remainingAmount <= 0) {
                break;
            }
            
            $dueAmount = round((float)$installment->amount - (float)$installment->paid_amount, 2);
            
            if ($dueAmount <= 0) {
                continue;
            }
            
            $allocationAmount = round(min($remainingAmount, $dueAmount), 2);
            
            Log::info("Allocating {$allocationAmount} to installment ID {$installment->id}");
            
            // Create allocation
            PaymentAllocation::create([
                'payment_id' => $this->paymentId,
                'installment_id' => $installment->id,
                'amount' => $allocationAmount,
            ]);
            
            // Update installment paid amount and status
            $newPaidAmount = round((float)$installment->paid_amount + $allocationAmount, 2);
            $newStatus = $newPaidAmount >= (float)$installment->amount ? 'Paid' : $installment->status;
            
            $installment->update([
                'paid_amount' => $newPaidAmount,
                'status' => $newStatus,
            ]);
            
            $remainingAmount = round($remainingAmount - $allocationAmount, 2);
        }
        
        Log::info("Payment allocation completed for payment ID: {$this->paymentId}. Remaining unallocated amount: {$remainingAmount}");
        
        // Clear cache
        $this->clearCache($payment->school_id);
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error("Payment allocation failed for payment ID: {$this->paymentId}", [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
        
        // You could also send a notification to admins here
    }
    
    /**
     * Clear cache related to fee management
     * 
     * @param int $schoolId
     * @return void
     */
    protected function clearCache($schoolId)
    {
        $cachePatterns = [
            "*_fee_structures_{$schoolId}_*",
            "*_fee_structure_{$schoolId}_*",
            "*_student_fee_plans_{$schoolId}_*",
            "*_student_fee_plan_{$schoolId}_*",
            "*_fee_installments_{$schoolId}_*",
            "*_payments_{$schoolId}_*",
            "*_payment_{$schoolId}_*",
            "*_student_fee_details_{$schoolId}_*",
            "*_detailed_student_fee_details_{$schoolId}_*",
            "*_student_fee_summary_{$schoolId}_*",
            "*_due_installments_{$schoolId}_*",
        ];
        
        foreach ($cachePatterns as $pattern) {
            \Illuminate\Support\Facades\Cache::forget($pattern);
        }
    }
}
