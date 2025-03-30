<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SchoolAdminResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'phone' => $this->phone,
            'profile_photo' => $this->profile_photo,
            'permissions' => $this->permissions,
            'school' => new SchoolResource($this->whenLoaded('school')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 