<?php

namespace App\Events\FeeManagement;

use App\Models\FeeInstallment;
use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentOverdue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public FeeInstallment $installment;
    public Student $student;
    public float $overdueAmount;
    public string $dueDate;
    public int $daysOverdue;

    /**
     * Create a new event instance.
     */
    public function __construct(FeeInstallment $installment, Student $student, int $daysOverdue)
    {
        $this->installment = $installment;
        $this->student = $student;
        $this->overdueAmount = $installment->amount - ($installment->paid_amount ?? 0);
        $this->dueDate = $installment->due_date;
        $this->daysOverdue = $daysOverdue;
    }
}
