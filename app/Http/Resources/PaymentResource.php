<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'student_id' => $this->student_id,
            'amount' => $this->amount,
            'method' => $this->method,
            'date' => $this->date,
            'status' => $this->status,
            'transaction_id' => $this->transaction_id,
            'notes' => $this->notes,
            'student' => new StudentResource($this->whenLoaded('student')),
            'allocations' => PaymentAllocationResource::collection($this->whenLoaded('allocations')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
