<?php

namespace App\Jobs\Notifications;

use App\Models\Student;
use App\Models\User;
use App\Models\Payment;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPaymentConfirmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Student $student;
    public User $parent;
    public Payment $payment;

    /**
     * Create a new job instance.
     */
    public function __construct(Student $student, User $parent, Payment $payment)
    {
        $this->student = $student;
        $this->parent = $parent;
        $this->payment = $payment;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            $studentName = $this->student->first_name . ' ' . $this->student->last_name;
            $amount = number_format($this->payment->amount_paid, 2);
            $paymentDate = $this->payment->payment_date->format('d M Y');

            $notificationData = [
                'school_id' => $this->student->school_id,
                'type' => 'fee',
                'title' => '✅ Payment Received',
                'message' => "Payment of ₹{$amount} for {$studentName} received successfully on {$paymentDate}. Thank you!",
                'target_type' => 'parent',
                'target_ids' => [$this->parent->id],
                'priority' => 'medium',
                'data' => [
                    'student_id' => $this->student->id,
                    'student_name' => $studentName,
                    'payment_id' => $this->payment->id,
                    'amount_paid' => $this->payment->amount_paid,
                    'payment_date' => $this->payment->payment_date->format('Y-m-d'),
                    'payment_method' => $this->payment->payment_method,
                    'receipt_number' => $this->payment->receipt_number,
                    'action_url' => '/fees/receipt/' . $this->payment->id,
                ],
            ];

            $notificationService->sendNotification($notificationData);

            Log::info('Payment confirmation notification sent', [
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id,
                'payment_id' => $this->payment->id,
                'amount' => $this->payment->amount_paid
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation notification', [
                'error' => $e->getMessage(),
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id,
                'payment_id' => $this->payment->id
            ]);

            throw $e;
        }
    }
}
