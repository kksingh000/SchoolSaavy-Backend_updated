<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ConsolidatedStudentDueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Student information
        $student = $this['student'];
        $overdue = $this['overdue'] ?? 0;
        $pending = $this['pending'] ?? 0;
        $installments = $this['installments'];
        
        // Get the latest due date for student's installments
        $nextDueDate = $installments->where('status', 'Pending')
            ->sortBy('due_date')
            ->first()
            ?->due_date;
        
        // Get the oldest overdue date
        $oldestOverdueDate = $installments->where('status', 'Overdue')
            ->sortBy('due_date')
            ->first()
            ?->due_date;
            
        // Count the number of overdue and pending installments
        $overdueInstallmentsCount = $installments->where('status', 'Overdue')->count();
        $pendingInstallmentsCount = $installments->where('status', 'Pending')->count();
        
        // Get components with dues
        $components = [];
        foreach ($installments as $installment) {
            // Get component information
            $componentName = 'Unknown Component';
            
            if (isset($installment->component)) {
                $component = $installment->component;
                
                // Try different ways to get component name
                if (!empty($component->name)) {
                    // Try the accessor method first
                    $componentName = $component->name;
                } elseif (!empty($component->component_name)) {
                    // Then try the direct field
                    $componentName = $component->component_name;
                } elseif (!empty($component->custom_name)) {
                    // Then try custom name
                    $componentName = $component->custom_name;
                } elseif (isset($component->masterComponent) && !empty($component->masterComponent->name)) {
                    // Finally try from master component
                    $componentName = $component->masterComponent->name;
                }
                
                // Log component information
                \Illuminate\Support\Facades\Log::info("Component debug - ID: {$component->id}, Component name: {$component->component_name}, Custom name: {$component->custom_name}, Accessor name: " . ($component->name ?? 'null'));
            } else {
                // Log missing component
                \Illuminate\Support\Facades\Log::warning("Missing component for installment #{$installment->id}, component_id: " . ($installment->component_id ?? 'unknown'));
            }
            
            if (!isset($components[$componentName])) {
                $components[$componentName] = [
                    'name' => $componentName,
                    'overdue' => 0,
                    'pending' => 0
                ];
            }
            
            $remaining = $installment->amount - $installment->paid_amount;
            
            if ($installment->status === 'Overdue') {
                $components[$componentName]['overdue'] += (float)$remaining;
            } elseif ($installment->status === 'Pending') {
                $components[$componentName]['pending'] += (float)$remaining;
            }
        }
        
        // Format numbers to fix floating-point precision issues
        foreach ($components as $key => $component) {
            $components[$key]['overdue'] = round($component['overdue'], 2);
            $components[$key]['pending'] = round($component['pending'], 2);
        }
        
        // Convert to array of values
        $componentsArray = array_values($components);
            
        // Build the response
        return [
            'student' => [
                'id' => $student->id ?? 0,
                'name' => $this->getStudentName($student),
                'class' => $this->getClassName($student),
            ],
            'fee_summary' => [
                'total_overdue_amount' => round($overdue, 2),
                'total_pending_amount' => round($pending, 2),
                'total_due_amount' => round($overdue + $pending, 2),
                'overdue_installments_count' => $overdueInstallmentsCount,
                'pending_installments_count' => $pendingInstallmentsCount,
                'next_due_date' => $nextDueDate ? $nextDueDate->format('Y-m-d') : null,
                'oldest_overdue_date' => $oldestOverdueDate ? $oldestOverdueDate->format('Y-m-d') : null,
                'overdue_days' => $oldestOverdueDate ? now()->diffInDays($oldestOverdueDate) : 0,
            ],
            'components' => $componentsArray
        ];
    }
    
    /**
     * Safely get the student name
     *
     * @param mixed $student
     * @return string
     */
    protected function getStudentName($student): string
    {
        if (!$student) {
            return 'Unknown Student';
        }
        
        $firstName = $student->first_name ?? '';
        $lastName = $student->last_name ?? '';
        
        if (empty($firstName) && empty($lastName)) {
            return 'Unknown Student';
        }
        
        return trim($firstName . ' ' . $lastName);
    }
    
    /**
     * Safely get the class name
     *
     * @param mixed $student
     * @return string
     */
    protected function getClassName($student): string
    {
        if (!$student) {
            return 'N/A';
        }
        
        if (!isset($student->currentClass)) {
            return 'N/A';
        }
        
        $currentClass = $student->currentClass;
        
        // If currentClass is a collection, get the first item
        if ($currentClass instanceof \Illuminate\Support\Collection) {
            $currentClass = $currentClass->first();
        }
        
        if (!$currentClass) {
            return 'N/A';
        }
        
        return $currentClass->name ?? 'N/A';
    }
}