<?php

namespace App\Events\FeeManagement;

use App\Models\Payment;
use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Payment $payment;
    public Student $student;
    public float $amount;
    public string $paymentDate;
    public string $method;
    public ?string $transactionId;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment, Student $student)
    {
        $this->payment = $payment;
        $this->student = $student;
        $this->amount = $payment->amount;
        $this->paymentDate = $payment->date;
        $this->method = $payment->method;
        $this->transactionId = $payment->transaction_id;
    }
}
