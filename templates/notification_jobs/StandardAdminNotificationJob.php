<?php

namespace App\Jobs\Notifications;

use App\Models\Admin;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: StandardAdminNotificationJob
 * 
 * Template for sending notifications to school administrators
 * 
 * Notification Details:
 * - Type: [notification_type]
 * - Priority: [priority_level]
 * - Recipients: Admin users
 * - Action URL: Link to view related content
 */
class StandardAdminNotificationJob implements ShouldQueue
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
        public int $adminId,
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
            Log::info("Starting admin notification process", [
                'job' => class_basename($this),
                'admin_id' => $this->adminId,
                'type' => $this->notificationType
            ]);

            // Build the notification message
            $message = $this->buildNotificationMessage();

            // Prepare data payload
            $data = [
                'admin_id' => $this->adminId,
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
                'target_type' => 'admin',
                'target_ids' => [$this->adminId],
                'data' => $data,
            ];

            // Send notification
            $result = $notificationService->sendNotification($notificationData);

            Log::info("Admin notification sent successfully", [
                'notification_id' => $result['notification_id'] ?? null,
                'admin_id' => $this->adminId,
                'type' => $this->notificationType
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to send admin notification", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'admin_id' => $this->adminId,
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
        return "Admin notification about {$this->primaryEntityId}.";
    }

    /**
     * Get action URL for deep linking
     */
    protected function getActionUrl(): string
    {
        // Override in child classes with proper routing
        return "/admin/dashboard";
    }
}