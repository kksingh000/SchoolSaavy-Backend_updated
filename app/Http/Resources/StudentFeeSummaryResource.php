<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentFeeSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Get the current class of the student
        $currentClass = $this->student->currentClass->first();
        
        // Calculate payment stats
        $totalFee = $this->calculateTotalFee();
        $totalPaid = $this->calculateTotalPaid();
        $totalDue = $this->calculateTotalDue();
        $totalOverdue = $this->calculateTotalOverdue();
        
        return [
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'class_id' => $currentClass->id ?? null,
            'class' => $currentClass->name ?? 'N/A',
            'academic_year' => $this->feeStructure->academicYear->display_name ?? 'N/A',
            'academic_year_id' => $this->feeStructure->academic_year_id ?? null,
            'total_fee' => $totalFee,
            'total_paid' => $totalPaid,
            'total_due' => $totalDue,
            'total_overdue' => $totalOverdue,
            'is_paid_up_to_date' => $this->isPaidUpToDate(),
        ];
    }
    
    /**
     * Calculate the total fee amount
     */
    protected function calculateTotalFee()
    {
        return $this->installments->sum('amount');
    }
    
    /**
     * Calculate the total paid amount
     */
    protected function calculateTotalPaid()
    {
        return $this->installments->sum('paid_amount');
    }
    
    /**
     * Calculate the total due amount (excluding overdue)
     */
    protected function calculateTotalDue()
    {
        $now = now();
        return $this->installments
            ->where('due_date', '>=', $now)
            ->sum(function ($installment) {
                return max(0, $installment->amount - $installment->paid_amount);
            });
    }
    
    /**
     * Calculate the total overdue amount
     */
    protected function calculateTotalOverdue()
    {
        $now = now();
        return $this->installments
            ->where('due_date', '<', $now)
            ->sum(function ($installment) {
                return max(0, $installment->amount - $installment->paid_amount);
            });
    }
    
    /**
     * Check if payments are up-to-date (all installments due until now are paid)
     */
    protected function isPaidUpToDate()
    {
        $now = now();
        $overdueInstallments = $this->installments
            ->where('due_date', '<', $now)
            ->filter(function ($installment) {
                return $installment->paid_amount < $installment->amount;
            });
            
        return $overdueInstallments->isEmpty();
    }
}
