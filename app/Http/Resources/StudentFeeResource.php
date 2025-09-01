<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentFeeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'fee_structure_id' => $this->fee_structure_id,
            'component_type' => $this->component_type,
            'component_name' => $this->component_name,
            'amount' => $this->amount,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'is_mandatory' => $this->is_mandatory,
            'student' => new StudentResource($this->whenLoaded('student')),
            'payments' => FeePaymentResource::collection($this->whenLoaded('payments')),
            'total_paid' => $this->whenLoaded('payments', function () {
                return $this->payments->sum('amount');
            }),
            'remaining_amount' => $this->whenLoaded('payments', function () {
                return $this->amount - $this->payments->sum('amount');
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
