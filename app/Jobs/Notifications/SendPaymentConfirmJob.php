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
    public int $paymentId;  // Changed from Payment model to ID

    /**
     * Create a new job instance.
     */
    public function __construct(int $paymentId, Student $student, User $parent)
    {
        $this->paymentId = $paymentId;
        $this->student = $student;
        $this->parent = $parent;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            // Load payment to get details
            $payment = Payment::find($this->paymentId);
            
            if (!$payment) {
                Log::warning('Payment not found for notification', [
                    'payment_id' => $this->paymentId
                ]);
                return;
            }

            $studentName = $this->student->first_name . ' ' . $this->student->last_name;
            $amount = number_format($payment->amount, 2);
            $paymentDate = \Carbon\Carbon::parse($payment->date)->format('d M Y');

            $notificationData = [
                'school_id' => $this->student->school_id,
                'type' => 'fee',
                'title' => '✅ Payment Received',
                'message' => "Payment of ₹{$amount} for {$studentName} received successfully on {$paymentDate}. Thank you!",
                'target_type' => 'parent',
                'target_ids' => [$this->parent->id],
                'priority' => 'normal',  // Changed from 'medium' to 'normal'
                'data' => [
                    'student_id' => $this->student->id,
                    'student_name' => $studentName,
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->date,
                    'payment_method' => $payment->method ?? 'N/A',
                    'transaction_id' => $payment->transaction_id ?? 'N/A',
                    'action_url' => '/fees/payments/' . $payment->id,
                ],
            ];

            $result = $notificationService->sendNotification($notificationData);

            Log::info('Payment confirmation notification sent', [
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'notification_result' => $result,
                'notification_id' => $result['notification_id'] ?? null,
                'success' => $result['success'] ?? false
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation notification', [
                'error' => $e->getMessage(),
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id,
                'payment_id' => $this->paymentId
            ]);

            throw $e;
        }
    }
}
