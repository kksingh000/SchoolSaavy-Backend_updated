<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @see file:copilot-instructions.md
 * 
 * AttendanceResource - Transforms attendance data for API responses
 * 
 * IMPORTANT: marked_by field stores user_id, not teacher_id
 * The markedBy relationship returns a User model, not Teacher model
 */
class AttendanceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'student' => new StudentResource($this->whenLoaded('student')),
            'class' => new ClassResource($this->whenLoaded('class')),
            'date' => $this->date,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'marked_by' => $this->whenLoaded('markedBy', function () {
                return [
                    'id' => $this->markedBy->id,
                    'name' => $this->markedBy->name,
                    'email' => $this->markedBy->email,
                    'user_type' => $this->markedBy->user_type,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
