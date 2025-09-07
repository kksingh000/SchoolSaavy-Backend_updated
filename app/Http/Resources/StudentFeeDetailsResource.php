<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentFeeDetailsResource extends JsonResource
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
        $className = $currentClass ? $currentClass->name : 'N/A';
        
        // Calculate payment stats
        $totalFee = $this->calculateTotalFee();
        $totalPaid = $this->calculateTotalPaid();
        $totalDue = $this->calculateTotalDue();
        $totalOverdue = $this->calculateTotalOverdue();
        
        // Get components with their installments
        $components = $this->getComponentsWithInstallments();
        
        return [
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'class' => $className,
            'academic_year' => $this->feeStructure->academicYear->display_name ?? 'N/A',
            'fee_plan' => [
                'total_fee' => $totalFee,
                'total_paid' => $totalPaid,
                'total_due' => $totalDue,
                'total_overdue' => $totalOverdue,
                'is_paid_up_to_date' => $this->isPaidUpToDate(),
                'paid_up_to_month' => $this->getPaidUpToMonth(),
                'components' => $components,
            ],
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
    
    /**
     * Get the month up to which the student has paid
     */
    protected function getPaidUpToMonth()
    {
        $now = now();
        $sortedInstallments = $this->installments
            ->sortBy('due_date');
            
        $lastPaidMonth = null;
        
        foreach ($sortedInstallments as $installment) {
            // If this installment is fully paid
            if ($installment->paid_amount >= $installment->amount) {
                $lastPaidMonth = $installment->due_date->format('F Y');
            } else {
                break;
            }
        }
        
        return $lastPaidMonth;
    }
    
    /**
     * Get components with their installments
     */
    protected function getComponentsWithInstallments()
    {
        $components = [];
        $componentIds = $this->installments->pluck('component_id')->unique();
        
        foreach ($componentIds as $componentId) {
            $componentInstallments = $this->installments->where('component_id', $componentId);
            
            if ($componentInstallments->isEmpty()) {
                continue;
            }
            
            $firstInstallment = $componentInstallments->first();
            $componentName = $firstInstallment->component->component_name ?? 'Unknown';
            $frequency = $firstInstallment->component->frequency ?? 'Unknown';
            
            // Calculate component annual amount
            $annualAmount = $componentInstallments->sum('amount');
            
            $installmentsData = $componentInstallments->map(function ($installment) {
                $status = 'Pending';
                if ($installment->paid_amount >= $installment->amount) {
                    $status = 'Paid';
                } elseif ($installment->due_date < now()) {
                    $status = 'Overdue';
                }
                
                return [
                    'installment_no' => $installment->installment_no,
                    'due_date' => $installment->due_date->format('Y-m-d'),
                    'amount' => $installment->amount,
                    'paid_amount' => $installment->paid_amount,
                    'status' => $status,
                ];
            })->values()->all();
            
            $components[] = [
                'name' => $componentName,
                'annual_amount' => $annualAmount,
                'frequency' => $frequency,
                'installments' => $installmentsData,
            ];
        }
        
        return $components;
    }
}
