<?php

namespace App\Events\FeeManagement;

use App\Models\FeeInstallment;
use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class PaymentDueTomorrow implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets;

    public int $installmentId;
    public int $studentId;
    public float $dueAmount;
    public string $dueDate;

    /**
     * Create a new event instance.
     */
    public function __construct(FeeInstallment $installment, Student $student)
    {
        $this->installmentId = $installment->id;
        $this->studentId = $student->id;
        $this->dueAmount = $installment->amount - ($installment->paid_amount ?? 0);
        $this->dueDate = $installment->due_date;
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
