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

class SendPaymentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Student $student;
    public User $parent;
    public FeeInstallment $installment;

    /**
     * Create a new job instance.
     */
    public function __construct(Student $student, User $parent, FeeInstallment $installment)
    {
        $this->student = $student;
        $this->parent = $parent;
        $this->installment = $installment;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            $studentName = $this->student->first_name . ' ' . $this->student->last_name;
            $amount = number_format($this->installment->amount, 2);
            $dueDate = $this->installment->due_date->format('d M Y');

            $notificationData = [
                'school_id' => $this->student->school_id,
                'type' => 'fee',
                'title' => '⏰ Payment Reminder',
                'message' => "Reminder: Fee payment of ₹{$amount} for {$studentName} is due tomorrow ({$dueDate}). Please make the payment on time.",
                'target_type' => 'parent',
                'target_ids' => [$this->parent->id],
                'priority' => 'high',
                'data' => [
                    'student_id' => $this->student->id,
                    'student_name' => $studentName,
                    'installment_id' => $this->installment->id,
                    'amount' => $this->installment->amount,
                    'due_date' => $this->installment->due_date->format('Y-m-d'),
                    'installment_number' => $this->installment->installment_number,
                    'action_url' => '/fees/installments/' . $this->installment->id,
                ],
            ];

            $notificationService->sendNotification($notificationData);

            Log::info('Payment reminder notification sent', [
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id,
                'installment_id' => $this->installment->id,
                'amount' => $this->installment->amount
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send payment reminder notification', [
                'error' => $e->getMessage(),
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id,
                'installment_id' => $this->installment->id
            ]);

            throw $e;
        }
    }
}
