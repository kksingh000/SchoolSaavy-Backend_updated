<?php

namespace App\Jobs\Notifications;

use App\Models\Student;
use App\Models\Parents;
use App\Models\FeeInstallment;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $studentId;
    public int $parentId;
    public int $installmentId;
    public float $dueAmount;
    public string $dueDate;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $studentId,
        int $parentId,
        int $installmentId,
        float $dueAmount,
        string $dueDate
    ) {
        $this->studentId = $studentId;
        $this->parentId = $parentId;
        $this->installmentId = $installmentId;
        $this->dueAmount = $dueAmount;
        $this->dueDate = $dueDate;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            Log::info('📱 Sending payment reminder notification', [
                'student_id' => $this->studentId,
                'parent_id' => $this->parentId,
                'installment_id' => $this->installmentId,
                'due_date' => $this->dueDate,
            ]);

            // Load models
            $student = Student::findOrFail($this->studentId);
            $parent = Parents::with('user')->findOrFail($this->parentId);
            $installment = FeeInstallment::findOrFail($this->installmentId);

            if (!$parent->user) {
                Log::warning('⚠️ Parent has no user account', [
                    'parent_id' => $this->parentId,
                    'student_id' => $this->studentId,
                ]);
                return;
            }

            $studentName = $student->first_name . ' ' . $student->last_name;
            $amount = number_format($this->dueAmount, 2);
            $dueDate = date('d M Y', strtotime($this->dueDate));

            $notificationData = [
                'school_id' => $student->school_id,
                'type' => 'fee',
                'title' => '⏰ Payment Reminder',
                'message' => "Reminder: Fee payment of ₹{$amount} for {$studentName} is due tomorrow ({$dueDate}). Please make the payment on time.",
                'data' => [
                    'student_id' => $this->studentId,
                    'student_name' => $studentName,
                    'installment_id' => $this->installmentId,
                    'amount' => $this->dueAmount,
                    'due_date' => $this->dueDate,
                    'installment_number' => $installment->installment_number,
                    'action_url' => '/fees/installments/' . $this->installmentId,
                ],
                'priority' => 'high',
                'target_type' => 'parent',
                'target_ids' => [$parent->user->id],
            ];

            $result = $notificationService->sendNotification($notificationData);

            if ($result['success']) {
                Log::info('✅ Payment reminder notification sent successfully', [
                    'notification_id' => $result['notification_id'] ?? null,
                    'student_id' => $this->studentId,
                    'parent_id' => $this->parentId,
                ]);
            } else {
                Log::error('❌ Failed to send payment reminder notification', [
                    'error' => $result['message'] ?? 'Unknown error',
                    'student_id' => $this->studentId,
                    'parent_id' => $this->parentId,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('❌ Exception in payment reminder notification job', [
                'error' => $e->getMessage(),
                'student_id' => $this->studentId,
                'parent_id' => $this->parentId,
                'installment_id' => $this->installmentId,
            ]);

            throw $e;
        }
    }
}
