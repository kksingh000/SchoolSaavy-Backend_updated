<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClassResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'grade' => $this->grade,
            'section' => $this->section,
            'academic_year' => $this->academic_year,
            'class_teacher' => new TeacherResource($this->whenLoaded('classTeacher')),
            'room_number' => $this->room_number,
            'capacity' => $this->capacity,
            'description' => $this->description,
            'students_count' => $this->students_count ?? $this->students()->count(),
            'subjects' => SubjectResource::collection($this->whenLoaded('subjects')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 