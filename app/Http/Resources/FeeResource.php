<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FeeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'student' => new StudentResource($this->whenLoaded('student')),
            'fee_structure' => new FeeStructureResource($this->whenLoaded('feeStructure')),
            'amount' => $this->amount,
            'paid_amount' => $this->paid_amount,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'payment_history' => $this->payment_history,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 