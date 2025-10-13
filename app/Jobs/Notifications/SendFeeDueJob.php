<?php

namespace App\Jobs\Notifications;

use App\Models\Student;
use App\Models\User;
use App\Models\FeeInstallment;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFeeDueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $studentId;
    public int $parentUserId;
    public int $installmentId;

    /**
     * Create a new job instance.
     * Uses ID-based pattern to avoid serialization issues
     */
    public function __construct(int $studentId, int $parentUserId, int $installmentId)
    {
        $this->studentId = $studentId;
        $this->parentUserId = $parentUserId;
        $this->installmentId = $installmentId;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            Log::info('💼 SendFeeDueJob started', [
                'student_id' => $this->studentId,
                'parent_user_id' => $this->parentUserId,
                'installment_id' => $this->installmentId,
            ]);

            // Load models fresh
            $student = Student::find($this->studentId);
            $parent = User::find($this->parentUserId);
            $installment = FeeInstallment::with('component')->find($this->installmentId);

            if (!$student || !$parent || !$installment) {
                Log::warning('⚠️ SendFeeDueJob: Model not found', [
                    'student_found' => !!$student,
                    'parent_found' => !!$parent,
                    'installment_found' => !!$installment,
                ]);
                return;
            }

            $studentName = $student->first_name . ' ' . $student->last_name;
            $remainingAmount = $installment->amount - ($installment->paid_amount ?? 0);
            $amount = number_format($remainingAmount, 2);
            $dueDate = $installment->due_date->format('d M Y');
            $componentName = $installment->component->name ?? 'Fee';

            $notificationData = [
                'school_id' => $student->school_id,
                'type' => 'fee',
                'title' => "💸 {$componentName} Due",
                'message' => "Fee installment of ₹{$amount} for {$studentName} is due on {$dueDate}. Please make payment to avoid late charges.",
                'target_type' => 'parent',
                'target_ids' => [$parent->id],
                'priority' => 'high',
                'data' => [
                    'student_id' => $student->id,
                    'student_name' => $studentName,
                    'installment_id' => $installment->id,
                    'component_name' => $componentName,
                    'total_amount' => $installment->amount,
                    'paid_amount' => $installment->paid_amount ?? 0,
                    'remaining_amount' => $remainingAmount,
                    'due_date' => $installment->due_date->format('Y-m-d'),
                    'installment_number' => $installment->installment_no ?? 1,
                    'action_url' => '/fees/installments/' . $installment->id,
                    'notification_type' => 'fee_due',
                ],
            ];

            Log::info('📤 Sending fee due notification', [
                'student_id' => $student->id,
                'parent_id' => $parent->id,
                'amount' => $remainingAmount,
            ]);

            $result = $notificationService->sendNotification($notificationData);

            if ($result['success']) {
                Log::info('✅ SendFeeDueJob DONE - Notification sent', [
                    'notification_id' => $result['notification']->id ?? null,
                    'student_id' => $student->id,
                    'parent_id' => $parent->id,
                    'amount' => $remainingAmount,
                ]);
            } else {
                Log::error('❌ SendFeeDueJob FAILED', [
                    'errors' => $result['errors'] ?? [],
                    'student_id' => $student->id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('💥 SendFeeDueJob exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'student_id' => $this->studentId,
                'parent_user_id' => $this->parentUserId,
                'installment_id' => $this->installmentId,
            ]);

            throw $e;
        }
    }
}
