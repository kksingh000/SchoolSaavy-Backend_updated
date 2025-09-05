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

class SendEventNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $event;
    protected $notificationType;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(Event $event, string $notificationType = 'created')
    {
        $this->event = $event;
        $this->notificationType = $notificationType;

        // Set queue based on event priority
        $this->onQueue($this->determineQueue($event));
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            Log::info("Processing event notification job", [
                'event_id' => $this->event->id,
                'notification_type' => $this->notificationType,
                'event_title' => $this->event->title
            ]);

            // Determine notification data based on event type
            $notificationData = $this->buildNotificationData();

            // Send notification
            $result = $notificationService->sendNotification($notificationData);

            if (!$result['success']) {
                throw new \Exception("Failed to send notification: " . $result['message']);
            }

            Log::info("Event notification sent successfully", [
                'event_id' => $this->event->id,
                'notification_id' => $result['notification_id'] ?? null,
                'recipients_count' => $result['total_recipients'] ?? 0
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send event notification", [
                'event_id' => $this->event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Event notification job failed permanently", [
            'event_id' => $this->event->id,
            'notification_type' => $this->notificationType,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // You could send a notification to administrators here
        // or update the event with a failed notification status
    }

    /**
     * Build notification data based on event
     */
    private function buildNotificationData(): array
    {
        $title = $this->getNotificationTitle();
        $message = $this->getNotificationMessage();
        $targetType = $this->determineTargetType();
        $targetData = $this->getTargetData();

        return [
            'school_id' => $this->event->school_id,
            'sender_id' => $this->event->created_by,
            'title' => $title,
            'message' => $message,
            'type' => $this->getNotificationType(),
            'priority' => $this->getNotificationPriority(),
            'target_type' => $targetType,
            'target_ids' => $targetData['target_ids'] ?? null,
            'target_classes' => $targetData['target_classes'] ?? null,
            'data' => [
                'event_id' => $this->event->id,
                'event_type' => $this->event->type,
                'event_date' => $this->event->event_date->format('Y-m-d'),
                'event_time' => $this->event->formatted_time,
                'location' => $this->event->location,
                'requires_acknowledgment' => $this->event->requires_acknowledgment,
                'notification_type' => $this->notificationType
            ]
        ];
    }

    /**
     * Get notification title based on event and type
     */
    private function getNotificationTitle(): string
    {
        $eventType = ucfirst($this->event->type);

        switch ($this->notificationType) {
            case 'created':
                return "New {$eventType}: {$this->event->title}";
            case 'updated':
                return "{$eventType} Updated: {$this->event->title}";
            case 'reminder':
                return "Reminder: {$this->event->title}";
            case 'cancelled':
                return "{$eventType} Cancelled: {$this->event->title}";
            default:
                return $this->event->title;
        }
    }

    /**
     * Get notification message based on event and type
     */
    private function getNotificationMessage(): string
    {
        $date = $this->event->event_date->format('M d, Y');
        $time = $this->event->formatted_time;
        $location = $this->event->location ? " at {$this->event->location}" : '';

        switch ($this->notificationType) {
            case 'created':
                return "A new {$this->event->type} has been scheduled for {$date}" .
                    ($time !== 'All Day' ? " at {$time}" : '') .
                    $location . ". " .
                    ($this->event->requires_acknowledgment ? "Please acknowledge this event." : "");

            case 'updated':
                return "The {$this->event->type} scheduled for {$date}" .
                    ($time !== 'All Day' ? " at {$time}" : '') .
                    $location . " has been updated. Please check the details.";

            case 'reminder':
                $daysUntil = $this->event->days_until_event;
                $reminderText = $daysUntil == 0 ? "today" : ($daysUntil == 1 ? "tomorrow" : "in {$daysUntil} days");
                return "Reminder: {$this->event->title} is {$reminderText}" .
                    ($time !== 'All Day' ? " at {$time}" : '') .
                    $location . ".";

            case 'cancelled':
                return "The {$this->event->type} '{$this->event->title}' scheduled for {$date} has been cancelled.";

            default:
                return $this->event->description ?: "Event: {$this->event->title}";
        }
    }

    /**
     * Determine notification type based on event
     */
    private function getNotificationType(): string
    {
        switch ($this->event->type) {
            case 'emergency':
                return 'emergency';
            case 'exam':
                return 'academic';
            case 'holiday':
                return 'holiday';
            case 'meeting':
                return 'meeting';
            default:
                return 'event';
        }
    }

    /**
     * Get notification priority based on event priority
     */
    private function getNotificationPriority(): string
    {
        // Map event priority to notification priority
        $priorityMap = [
            'low' => 'low',
            'medium' => 'normal',
            'high' => 'high',
            'urgent' => 'urgent'
        ];

        return $priorityMap[$this->event->priority] ?? 'normal';
    }

    /**
     * Determine target type based on event audience
     */
    private function determineTargetType(): string
    {
        $audiences = $this->event->target_audience;

        // If targeting all, send to both parents and teachers
        if (in_array('all', $audiences)) {
            return 'all_school_users';
        }

        // If targeting specific classes
        if (!empty($this->event->affected_classes)) {
            // Determine if we need parents, teachers, or both
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

        // General audience targeting
        if (in_array('parents', $audiences) && in_array('teachers', $audiences)) {
            return 'all_school_users';
        } elseif (in_array('parents', $audiences)) {
            return 'all_parents';
        } elseif (in_array('teachers', $audiences)) {
            return 'all_teachers';
        }

        return 'all_school_users';
    }

    /**
     * Get target data (IDs or classes) based on target type
     */
    private function getTargetData(): array
    {
        $data = [];

        // If targeting specific classes
        if (!empty($this->event->affected_classes)) {
            $data['target_classes'] = $this->event->affected_classes;
        }

        return $data;
    }

    /**
     * Determine queue based on event priority
     */
    private function determineQueue(Event $event): string
    {
        switch ($event->priority) {
            case 'urgent':
                return 'urgent-notifications';
            case 'high':
                return 'high-notifications';
            case 'medium':
                return 'normal-notifications';
            case 'low':
                return 'low-notifications';
            default:
                return 'normal-notifications';
        }
    }
}
