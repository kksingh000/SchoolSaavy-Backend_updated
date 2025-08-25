<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParentStudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Basic student information
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
            'address' => $this->address,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Class information
            'class_id' => $this->class_id,
            'class_name' => $this->class_name,
            'class_section' => $this->class_section,
            'class_title' => $this->class_id
                ? $this->class_name . ' - ' . $this->class_section
                : 'Not Assigned',

            // School information
            'school_id' => $this->school_id,
            'school_name' => $this->school_name,

            // Computed fields
            'full_name' => $this->full_name,
        ];
    }
}
