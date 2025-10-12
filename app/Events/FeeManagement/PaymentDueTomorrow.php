<?php

namespace App\Events\FeeManagement;

use App\Models\FeeInstallment;
use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentDueTomorrow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public FeeInstallment $installment;
    public Student $student;
    public float $dueAmount;
    public string $dueDate;

    /**
     * Create a new event instance.
     */
    public function __construct(FeeInstallment $installment, Student $student)
    {
        $this->installment = $installment;
        $this->student = $student;
        $this->dueAmount = $installment->amount - ($installment->paid_amount ?? 0);
        $this->dueDate = $installment->due_date;
    }
}
