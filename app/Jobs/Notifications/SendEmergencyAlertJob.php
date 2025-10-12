<?php

namespace App\Jobs\Notifications;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEmergencyAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $schoolId;
    public string $title;
    public string $message;
    public User $recipient;
    public string $targetType;
    public ?array $additionalData;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $schoolId,
        string $title,
        string $message,
        User $recipient,
        string $targetType = 'all',
        ?array $additionalData = null
    ) {
        $this->schoolId = $schoolId;
        $this->title = $title;
        $this->message = $message;
        $this->recipient = $recipient;
        $this->targetType = $targetType;
        $this->additionalData = $additionalData;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            $notificationData = [
                'school_id' => $this->schoolId,
                'type' => 'emergency',
                'title' => '🚨 ' . $this->title,
                'message' => $this->message,
                'target_type' => $this->targetType,
                'target_ids' => [$this->recipient->id],
                'is_urgent' => true,
                'priority' => 'high',
                'requires_acknowledgment' => true,
                'data' => array_merge([
                    'alert_type' => 'emergency',
                    'timestamp' => now()->toIso8601String(),
                ], $this->additionalData ?? []),
            ];

            $notificationService->sendNotification($notificationData);

            Log::info('Emergency alert sent', [
                'school_id' => $this->schoolId,
                'recipient_id' => $this->recipient->id,
                'target_type' => $this->targetType,
                'title' => $this->title
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send emergency alert', [
                'error' => $e->getMessage(),
                'school_id' => $this->schoolId,
                'recipient_id' => $this->recipient->id,
                'title' => $this->title
            ]);

            throw $e;
        }
    }
}
