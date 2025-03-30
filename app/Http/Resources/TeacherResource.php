<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'joining_date' => $this->joining_date,
            'gender' => $this->gender,
            'qualification' => $this->qualification,
            'profile_photo' => $this->profile_photo,
            'address' => $this->address,
            'specializations' => $this->specializations,
            'school' => new SchoolResource($this->whenLoaded('school')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 