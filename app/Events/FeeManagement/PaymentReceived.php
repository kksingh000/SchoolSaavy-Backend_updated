<?php

namespace App\Events\FeeManagement;

use App\Models\Payment;
use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PaymentReceived implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $paymentId;
    public int $studentId;
    public float $amount;
    public string $paymentDate;
    public string $method;
    public ?string $transactionId;

    /**
     * Create a new event instance.
     * Store only IDs to avoid serialization issues with relationships.
     */
    public function __construct(Payment $payment, Student $student)
    {
        $this->paymentId = $payment->id;
        $this->studentId = $student->id;
        $this->amount = $payment->amount;
        $this->paymentDate = $payment->date;
        $this->method = $payment->method;
        $this->transactionId = $payment->transaction_id;
    }

    /**
     * Get the student model with relationships loaded.
     */
    public function getStudent(): Student
    {
        return Student::with('parents.user')->findOrFail($this->studentId);
    }

    /**
     * Get the payment model.
     */
    public function getPayment(): Payment
    {
        return Payment::findOrFail($this->paymentId);
    }
}
