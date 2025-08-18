<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @see file:copilot-instructions.md
 * 
 * SubjectResource - Transforms subject data for API responses
 * 
 * Note: Subjects don't have direct teacher relationships in the database.
 * Teachers are assigned to subjects through class-subject relationships.
 */
class SubjectResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'classes' => ClassResource::collection($this->whenLoaded('classes')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 