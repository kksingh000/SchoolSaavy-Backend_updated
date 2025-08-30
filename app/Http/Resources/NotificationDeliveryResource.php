<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationDeliveryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notification_id' => $this->notification_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'status_display' => $this->getStatusOptions()[$this->status] ?? $this->status,
            'sent_at' => $this->sent_at?->format('Y-m-d H:i:s'),
            'delivered_at' => $this->delivered_at?->format('Y-m-d H:i:s'),
            'read_at' => $this->read_at?->format('Y-m-d H:i:s'),
            'acknowledged_at' => $this->acknowledged_at?->format('Y-m-d H:i:s'),
            'error_message' => $this->error_message,
            'retry_count' => $this->retry_count,
            'last_retry_at' => $this->last_retry_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            // Conditional fields
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->first_name . ' ' . $this->user->last_name,
                    'email' => $this->user->email,
                    'user_type' => $this->user->user_type
                ];
            }),

            'notification' => $this->whenLoaded('notification', function () {
                return [
                    'id' => $this->notification->id,
                    'title' => $this->notification->title,
                    'message' => $this->notification->message,
                    'type' => $this->notification->type,
                    'priority' => $this->notification->priority,
                    'created_at' => $this->notification->created_at->format('Y-m-d H:i:s')
                ];
            }),
        ];
    }
}
