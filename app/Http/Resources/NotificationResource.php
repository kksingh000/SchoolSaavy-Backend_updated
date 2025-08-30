<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'type_display' => $this->getTypes()[$this->type] ?? $this->type,
            'priority' => $this->priority,
            'priority_display' => $this->getPriorities()[$this->priority] ?? $this->priority,
            'target_type' => $this->target_type,
            'target_type_display' => $this->getTargetTypes()[$this->target_type] ?? $this->target_type,
            'target_ids' => $this->target_ids,
            'target_classes' => $this->target_classes,
            'status' => $this->status,
            'total_recipients' => $this->total_recipients,
            'sent_count' => $this->sent_count,
            'delivered_count' => $this->delivered_count,
            'read_count' => $this->read_count,
            'delivery_rate' => $this->getDeliveryRate(),
            'read_rate' => $this->getReadRate(),
            'data' => $this->data,
            'scheduled_at' => $this->scheduled_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            // Conditional fields
            'sender' => $this->whenLoaded('sender', function () {
                return [
                    'id' => $this->sender->id,
                    'name' => $this->sender->first_name . ' ' . $this->sender->last_name,
                    'user_type' => $this->sender->user_type
                ];
            }),

            'deliveries' => $this->whenLoaded('deliveries', function () {
                return NotificationDeliveryResource::collection($this->deliveries);
            }),

            'delivery_summary' => $this->when($this->relationLoaded('deliveries'), function () {
                return [
                    'total' => $this->deliveries->count(),
                    'successful' => $this->deliveries->where('status', '!=', 'failed')->count(),
                    'failed' => $this->deliveries->where('status', 'failed')->count(),
                    'read' => $this->deliveries->whereIn('status', ['read', 'acknowledged'])->count(),
                ];
            }),
        ];
    }
}
