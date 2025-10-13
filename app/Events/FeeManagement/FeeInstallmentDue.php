<?php

namespace App\Events\FeeManagement;

use App\Models\FeeInstallment;
use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeeInstallmentDue implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $installmentId;
    public int $studentId;
    public float $amount;
    public string $dueDate;
    public string $componentName;

    /**
     * Create a new event instance.
     * Uses ID-based serialization to avoid queue deserialization issues
     */
    public function __construct(FeeInstallment $installment, Student $student)
    {
        $this->installmentId = $installment->id;
        $this->studentId = $student->id;
        $this->amount = $installment->amount - ($installment->paid_amount ?? 0);
        $this->dueDate = $installment->due_date->format('Y-m-d');
        $this->componentName = $installment->component->name ?? 'Fee';
    }

    /**
     * Get fresh installment with relationships
     */
    public function getInstallment(): FeeInstallment
    {
        return FeeInstallment::with('component', 'studentFeePlan')
            ->findOrFail($this->installmentId);
    }

    /**
     * Get fresh student with parent relationships
     */
    public function getStudent(): Student
    {
        return Student::with('parents.user')
            ->findOrFail($this->studentId);
    }
}
