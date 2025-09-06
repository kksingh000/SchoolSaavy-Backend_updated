<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeePaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_fee_id' => $this->student_fee_id,
            'amount' => $this->amount,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'payment_method' => $this->payment_method,
            'payment_method_display' => ucfirst(str_replace('_', ' ', $this->payment_method)),
            'transaction_id' => $this->transaction_id,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'status' => $this->status,
            'status_display' => ucfirst($this->status),
            
            // Student information
            'student' => $this->whenLoaded('studentFee', function () {
                return [
                    'id' => $this->studentFee->student->id,
                    'name' => $this->studentFee->student->first_name . ' ' . $this->studentFee->student->last_name,
                    'admission_number' => $this->studentFee->student->admission_number,
                    'class' => $this->studentFee->student->classes->first()->name ?? 'N/A',
                ];
            }),

            // Fee structure information
            'fee_structure' => $this->whenLoaded('studentFee', function () {
                return [
                    'id' => $this->studentFee->feeStructure->id,
                    'name' => $this->studentFee->feeStructure->name,
                    'component_name' => $this->studentFee->component_name,
                    'component_type' => $this->studentFee->component_type,
                    'total_amount' => $this->studentFee->amount,
                    'due_date' => $this->studentFee->due_date?->format('Y-m-d'),
                    'fee_status' => $this->studentFee->status,
                ];
            }),

            // Payment summary for this student fee
            'payment_summary' => $this->whenLoaded('studentFee', function () {
                $totalPaid = $this->studentFee->payments->sum('amount');
                $remainingAmount = $this->studentFee->amount - $totalPaid;
                
                return [
                    'total_fee_amount' => $this->studentFee->amount,
                    'total_paid' => $totalPaid,
                    'remaining_amount' => $remainingAmount,
                    'payment_percentage' => $this->studentFee->amount > 0 
                        ? round(($totalPaid / $this->studentFee->amount) * 100, 2) 
                        : 0,
                    'is_fully_paid' => $remainingAmount <= 0,
                ];
            }),

            // Received by information
            'received_by' => $this->whenLoaded('receivedBy', function () {
                return [
                    'id' => $this->receivedBy->id,
                    'name' => $this->receivedBy->name,
                    'email' => $this->receivedBy->email,
                    'user_type' => $this->receivedBy->user_type,
                ];
            }),

            // Legacy field for backward compatibility
            'received_by_id' => $this->received_by,

            // Formatted display values
            'formatted_amount' => '₹' . number_format($this->amount, 2),
            'formatted_payment_date' => $this->payment_date?->format('d M Y'),
            'payment_age_days' => $this->payment_date?->diffInDays(now()),
            
            // Receipt information
            'can_generate_receipt' => $this->status === 'completed',
            'receipt_number' => $this->reference_number,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
