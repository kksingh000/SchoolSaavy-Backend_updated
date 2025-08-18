<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\GeneratesFileUrls;

class TeacherResource extends JsonResource
{
    use GeneratesFileUrls;

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'employee_id' => $this->employee_id,
            'name' => $this->user?->name ?? 'No User Account',
            'email' => $this->user?->email ?? 'No Email',
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth ? $this->date_of_birth->format('Y-m-d') : null,
            'joining_date' => $this->joining_date ? $this->joining_date->format('Y-m-d') : null,
            'years_of_experience' => $this->years_of_experience,
            'gender' => $this->gender,
            'qualification' => $this->qualification,
            'specializations' => $this->specializations,
            'profile_photo' => $this->profile_photo,
            'profile_photo_url' => $this->buildFileUrl($this->profile_photo),
            'address' => $this->address,
            'is_active' => $this->user?->is_active ?? true,

            // Relationships (when loaded)
            'school' => new SchoolResource($this->whenLoaded('school')),
            'classes' => ClassResource::collection($this->whenLoaded('classes')),
            'classes_count' => $this->when(isset($this->classes_count), $this->classes_count),

            // Statistics (when available)
            'statistics' => $this->when(isset($this->statistics), $this->statistics),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
