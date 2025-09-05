<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendEventReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $event;
    protected $reminderType; // '1_day', '3_hours', '1_hour'

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 2;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(Event $event, string $reminderType = '1_day')
    {
        $this->event = $event;
        $this->reminderType = $reminderType;
        $this->onQueue('reminder-notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            // Check if event is still valid and published
            if (!$this->event->is_published || $this->event->trashed()) {
                Log::info("Skipping reminder for unpublished/deleted event", [
                    'event_id' => $this->event->id,
                    'reminder_type' => $this->reminderType
                ]);
                return;
            }

            // Check if event date hasn't passed
            if ($this->event->event_date->isPast()) {
                Log::info("Skipping reminder for past event", [
                    'event_id' => $this->event->id,
                    'event_date' => $this->event->event_date,
                    'reminder_type' => $this->reminderType
                ]);
                return;
            }

            // Build notification data
            $notificationData = $this->buildReminderNotificationData();

            // Send notification
            $result = $notificationService->sendNotification($notificationData);

            if (!$result['success']) {
                throw new \Exception("Failed to send reminder: " . $result['message']);
            }

            Log::info("Event reminder sent successfully", [
                'event_id' => $this->event->id,
                'reminder_type' => $this->reminderType,
                'notification_id' => $result['notification_id'] ?? null,
                'recipients_count' => $result['total_recipients'] ?? 0
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send event reminder", [
                'event_id' => $this->event->id,
                'reminder_type' => $this->reminderType,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Build reminder notification data
     */
    private function buildReminderNotificationData(): array
    {
        return [
            'school_id' => $this->event->school_id,
            'sender_id' => $this->event->created_by,
            'title' => $this->getReminderTitle(),
            'message' => $this->getReminderMessage(),
            'type' => 'reminder',
            'priority' => $this->getReminderPriority(),
            'target_type' => $this->determineTargetType(),
            'target_ids' => null,
            'target_classes' => !empty($this->event->affected_classes) ? $this->event->affected_classes : null,
            'data' => [
                'event_id' => $this->event->id,
                'event_type' => $this->event->type,
                'event_date' => $this->event->event_date->format('Y-m-d'),
                'event_time' => $this->event->formatted_time,
                'location' => $this->event->location,
                'reminder_type' => $this->reminderType,
                'notification_type' => 'reminder'
            ]
        ];
    }

    /**
     * Get reminder title
     */
    private function getReminderTitle(): string
    {
        $timeText = $this->getTimeText();
        return "Reminder: {$this->event->title} {$timeText}";
    }

    /**
     * Get reminder message
     */
    private function getReminderMessage(): string
    {
        $timeText = $this->getTimeText();
        $date = $this->event->event_date->format('M d, Y');
        $time = $this->event->formatted_time;
        $location = $this->event->location ? " at {$this->event->location}" : '';

        return "Don't forget: {$this->event->title} is {$timeText} on {$date}" .
            ($time !== 'All Day' ? " at {$time}" : '') .
            $location . ".";
    }

    /**
     * Get time text based on reminder type
     */
    private function getTimeText(): string
    {
        switch ($this->reminderType) {
            case '1_day':
                return 'tomorrow';
            case '3_hours':
                return 'in 3 hours';
            case '1_hour':
                return 'in 1 hour';
            default:
                return 'soon';
        }
    }

    /**
     * Get reminder priority
     */
    private function getReminderPriority(): string
    {
        // Escalate priority for reminders
        $priorityMap = [
            'low' => 'normal',
            'medium' => 'high',
            'high' => 'urgent',
            'urgent' => 'urgent'
        ];

        return $priorityMap[$this->event->priority] ?? 'normal';
    }

    /**
     * Determine target type for reminders
     */
    private function determineTargetType(): string
    {
        $audiences = $this->event->target_audience;

        if (in_array('all', $audiences)) {
            return 'all_school_users';
        }

        if (!empty($this->event->affected_classes)) {
            $needsParents = array_intersect(['students', 'parents'], $audiences);
            $needsTeachers = in_array('teachers', $audiences);

            if ($needsParents && $needsTeachers) {
                return 'class_all_users';
            } elseif ($needsParents) {
                return 'class_parents';
            } elseif ($needsTeachers) {
                return 'class_teachers';
            }
        }

        if (in_array('parents', $audiences) && in_array('teachers', $audiences)) {
            return 'all_school_users';
        } elseif (in_array('parents', $audiences)) {
            return 'all_parents';
        } elseif (in_array('teachers', $audiences)) {
            return 'all_teachers';
        }

        return 'all_school_users';
    }
}
