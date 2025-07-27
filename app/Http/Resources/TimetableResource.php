<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TimetableResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'class' => [
                'id' => $this->class->id,
                'name' => $this->class->name,
                'section' => $this->class->section,
            ],
            'subject' => [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
                'code' => $this->subject->code,
            ],
            'teacher' => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->user->name ?? 'Unknown',
                'email' => $this->teacher->user->email ?? null,
            ],
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->start_time ? $this->start_time->format('H:i') : null,
            'end_time' => $this->end_time ? $this->end_time->format('H:i') : null,
            'formatted_time' => $this->formatted_time ?? null,
            'duration_minutes' => $this->duration_in_minutes ?? null,
            'room_number' => $this->room_number,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
