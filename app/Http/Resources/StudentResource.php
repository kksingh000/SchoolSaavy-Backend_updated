<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\GeneratesFileUrls;

class StudentResource extends JsonResource
{
    use GeneratesFileUrls;

    public function toArray($request)
    {
        // Get current class information
        $currentClass = $this->currentClass->first();

        return [
            'id' => $this->id,
            'admission_number' => $this->admission_number,
            'roll_number' => $this->roll_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'admission_date' => $this->admission_date,
            'blood_group' => $this->blood_group,
            'profile_photo' => $this->profile_photo,
            'profile_photo_url' => $this->buildFileUrl($this->profile_photo),
            'address' => $this->address,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'class_id' => $currentClass ? $currentClass->id : null,
            'class_name' => $currentClass ? $currentClass->name . ($currentClass->section ? ' ' . $currentClass->section : '') : null,
            'school' => new SchoolResource($this->whenLoaded('school')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
