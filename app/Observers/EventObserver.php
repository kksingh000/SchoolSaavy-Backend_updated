<?php

namespace App\Observers;

use App\Models\Event;
use App\Jobs\SendEventNotificationJob;
use Illuminate\Support\Facades\Log;

class EventObserver
{
    /**
     * Handle the Event "created" event.
     */
    public function created(Event $event): void
    {
        // Only send notifications for published events
        if ($event->is_published) {
            $this->dispatchEventNotification($event, 'created');
        }
    }

    /**
     * Handle the Event "updated" event.
     */
    public function updated(Event $event): void
    {
        // Check if the event was just published
        if ($event->wasChanged('is_published') && $event->is_published) {
            $this->dispatchEventNotification($event, 'created');
            return;
        }

        // Send update notification only if published and significant changes occurred
        if ($event->is_published && $this->hasSignificantChanges($event)) {
            $this->dispatchEventNotification($event, 'updated');
        }
    }

    /**
     * Handle the Event "deleted" event.
     */
    public function deleted(Event $event): void
    {
        // Send cancellation notification for published events
        if ($event->is_published) {
            $this->dispatchEventNotification($event, 'cancelled');
        }
    }

    /**
     * Dispatch notification job for event
     */
    private function dispatchEventNotification(Event $event, string $notificationType): void
    {
        try {
            // Add delay based on priority for non-urgent events
            $delay = $this->calculateNotificationDelay($event, $notificationType);

            if ($delay > 0) {
                SendEventNotificationJob::dispatch($event, $notificationType)
                    ->delay(now()->addSeconds($delay));
            } else {
                SendEventNotificationJob::dispatch($event, $notificationType);
            }

            Log::info("Event notification job dispatched", [
                'event_id' => $event->id,
                'notification_type' => $notificationType,
                'delay_seconds' => $delay
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to dispatch event notification job", [
                'event_id' => $event->id,
                'notification_type' => $notificationType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if event has significant changes that warrant a notification
     */
    private function hasSignificantChanges(Event $event): bool
    {
        $significantFields = [
            'title',
            'description',
            'event_date',
            'start_time',
            'end_time',
            'location',
            'priority',
            'target_audience',
            'affected_classes',
            'requires_acknowledgment'
        ];

        foreach ($significantFields as $field) {
            if ($event->wasChanged($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate notification delay based on event priority and type
     */
    private function calculateNotificationDelay(Event $event, string $notificationType): int
    {
        // Urgent events and cancellations are sent immediately
        if ($event->priority === 'urgent' || $notificationType === 'cancelled') {
            return 0;
        }

        // Emergency events are sent immediately
        if ($event->type === 'emergency') {
            return 0;
        }

        // High priority events get minimal delay
        if ($event->priority === 'high') {
            return 30; // 30 seconds
        }

        // Medium priority events get short delay
        if ($event->priority === 'medium') {
            return 120; // 2 minutes
        }

        // Low priority events get longer delay
        return 300; // 5 minutes
    }
}
