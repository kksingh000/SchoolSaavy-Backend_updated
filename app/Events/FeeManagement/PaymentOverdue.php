<?php

namespace App\Events\FeeManagement;

use App\Models\FeeInstallment;
use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class PaymentOverdue implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets;

    public int $installmentId;
    public int $studentId;
    public float $overdueAmount;
    public string $dueDate;
    public int $daysOverdue;

    /**
     * Create a new event instance.
     */
    public function __construct(FeeInstallment $installment, Student $student, int $daysOverdue)
    {
        $this->installmentId = $installment->id;
        $this->studentId = $student->id;
        $this->overdueAmount = $installment->amount - ($installment->paid_amount ?? 0);
        $this->dueDate = $installment->due_date;
        $this->daysOverdue = $daysOverdue;
    }

    /**
     * Get the installment with relationships
     */
    public function getInstallment(): FeeInstallment
    {
        return FeeInstallment::findOrFail($this->installmentId);
    }

    /**
     * Get the student with parents
     */
    public function getStudent(): Student
    {
        return Student::with('parents.user')->findOrFail($this->studentId);
    }
}
