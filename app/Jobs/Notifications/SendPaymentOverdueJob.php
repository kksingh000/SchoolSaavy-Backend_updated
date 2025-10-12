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

class SendPaymentOverdueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Student $student;
    public User $parent;
    public FeeInstallment $installment;
    public int $daysOverdue;

    /**
     * Create a new job instance.
     */
    public function __construct(Student $student, User $parent, FeeInstallment $installment, int $daysOverdue)
    {
        $this->student = $student;
        $this->parent = $parent;
        $this->installment = $installment;
        $this->daysOverdue = $daysOverdue;
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
                'title' => '🚨 Payment Overdue',
                'message' => "Fee payment of ₹{$amount} for {$studentName} is overdue by {$this->daysOverdue} days (Due: {$dueDate}). Please pay immediately to avoid penalties.",
                'target_type' => 'parent',
                'target_ids' => [$this->parent->id],
                'is_urgent' => true,
                'priority' => 'high',
                'requires_acknowledgment' => true,
                'data' => [
                    'student_id' => $this->student->id,
                    'student_name' => $studentName,
                    'installment_id' => $this->installment->id,
                    'amount' => $this->installment->amount,
                    'due_date' => $this->installment->due_date->format('Y-m-d'),
                    'days_overdue' => $this->daysOverdue,
                    'installment_number' => $this->installment->installment_number,
                    'action_url' => '/fees/installments/' . $this->installment->id,
                ],
            ];

            $notificationService->sendNotification($notificationData);

            Log::info('Payment overdue notification sent', [
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id,
                'installment_id' => $this->installment->id,
                'days_overdue' => $this->daysOverdue
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send payment overdue notification', [
                'error' => $e->getMessage(),
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id,
                'installment_id' => $this->installment->id
            ]);

            throw $e;
        }
    }
}
