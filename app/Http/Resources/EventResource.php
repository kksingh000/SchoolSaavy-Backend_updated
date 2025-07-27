<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'priority' => $this->priority,
            'event_date' => $this->event_date ? $this->event_date->format('Y-m-d') : null,
            'start_time' => $this->start_time ? $this->start_time->format('H:i') : null,
            'end_time' => $this->end_time ? $this->end_time->format('H:i') : null,
            'formatted_time' => $this->formatted_time,
            'location' => $this->location,
            'target_audience' => $this->target_audience,
            'affected_classes' => $this->affected_classes,
            'affected_classes_details' => $this->whenLoaded('affectedClasses', function () {
                return $this->affectedClasses()->map(function ($class) {
                    return [
                        'id' => $class->id,
                        'name' => $class->name,
                        'section' => $class->section,
                    ];
                });
            }),
            'is_recurring' => $this->is_recurring,
            'recurrence_type' => $this->recurrence_type,
            'recurrence_end_date' => $this->recurrence_end_date ? $this->recurrence_end_date->format('Y-m-d') : null,
            'requires_acknowledgment' => $this->requires_acknowledgment,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at ? $this->published_at->format('Y-m-d H:i:s') : null,
            'attachments' => $this->attachments,

            // Status flags
            'is_upcoming' => $this->is_upcoming,
            'is_today' => $this->is_today,
            'days_until_event' => $this->days_until_event,

            // Acknowledgment info
            'acknowledgment_rate' => $this->when($this->requires_acknowledgment, $this->acknowledgment_rate),
            'is_acknowledged_by_current_user' => $this->when(
                $this->requires_acknowledgment && auth()->check(),
                $this->isAcknowledgedBy(auth()->user())
            ),
            'total_acknowledgments' => $this->when(
                $this->requires_acknowledgment,
                $this->acknowledgments()->count()
            ),

            // Relationships
            'creator' => [
                'id' => $this->creator->id ?? null,
                'name' => $this->creator->name ?? 'Unknown',
                'email' => $this->creator->email ?? null,
            ],
            'school' => $this->when($this->relationLoaded('school'), [
                'id' => $this->school->id ?? null,
                'name' => $this->school->name ?? null,
            ]),

            // Acknowledgments (only when specifically loaded)
            'acknowledgments' => $this->when(
                $this->relationLoaded('acknowledgments'),
                function () {
                    return $this->acknowledgments->map(function ($ack) {
                        return [
                            'id' => $ack->id,
                            'user' => [
                                'id' => $ack->user->id,
                                'name' => $ack->user->name,
                                'email' => $ack->user->email,
                            ],
                            'acknowledged_at' => $ack->acknowledged_at->format('Y-m-d H:i:s'),
                            'comments' => $ack->comments,
                        ];
                    });
                }
            ),

            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
