<?php

namespace App\Jobs\Notifications;

use App\Models\Student;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: StandardParentNotificationJob
 * 
 * Template for sending notifications to parents
 * 
 * Notification Details:
 * - Type: [notification_type]
 * - Priority: [priority_level]
 * - Recipients: Parent users
 * - Action URL: Link to view related content
 */
class StandardParentNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $schoolId,
        public int $parentId,
        public int $studentId,
        public string $studentName,
        public int $primaryEntityId,
        public string $title,
        public string $notificationType,
        public string $priority = 'normal',
        public ?array $additionalData = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            Log::info("Starting parent notification process", [
                'job' => class_basename($this),
                'student_id' => $this->studentId,
                'parent_id' => $this->parentId,
                'type' => $this->notificationType
            ]);

            // Build the notification message
            $message = $this->buildNotificationMessage();

            // Prepare data payload
            $data = [
                'student_id' => $this->studentId,
                'student_name' => $this->studentName,
                'primary_id' => $this->primaryEntityId,
                'action_url' => $this->getActionUrl(),
            ];

            // Merge additional data if provided
            if ($this->additionalData) {
                $data = array_merge($data, $this->additionalData);
            }

            // Prepare notification data
            $notificationData = [
                'school_id' => $this->schoolId,
                'type' => $this->notificationType,
                'title' => $this->title,
                'message' => $message,
                'priority' => $this->priority,
                'target_type' => 'parent',
                'target_ids' => [$this->parentId],
                'data' => $data,
            ];

            // Send notification
            $result = $notificationService->sendNotification($notificationData);

            Log::info("Parent notification sent successfully", [
                'notification_id' => $result['notification_id'] ?? null,
                'parent_id' => $this->parentId,
                'student_name' => $this->studentName,
                'type' => $this->notificationType
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to send parent notification", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'parent_id' => $this->parentId,
                'student_id' => $this->studentId,
                'type' => $this->notificationType
            ]);

            throw $e; // Re-throw for job retry
        }
    }

    /**
     * Build the notification message
     */
    private function buildNotificationMessage(): string
    {
        // Override in child classes
        return "Notification about {$this->studentName}.";
    }

    /**
     * Get action URL for deep linking
     */
    protected function getActionUrl(): string
    {
        // Override in child classes with proper routing
        return "/dashboard";
    }
}