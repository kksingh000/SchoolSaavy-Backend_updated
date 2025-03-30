<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ParentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'phone' => $this->phone,
            'alternate_phone' => $this->alternate_phone,
            'gender' => $this->gender,
            'occupation' => $this->occupation,
            'profile_photo' => $this->profile_photo,
            'address' => $this->address,
            'relationship' => $this->relationship,
            'students' => StudentResource::collection($this->whenLoaded('students')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 