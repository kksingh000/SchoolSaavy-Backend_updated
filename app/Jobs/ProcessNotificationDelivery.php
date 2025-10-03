<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\UserDeviceToken;
use App\Services\FirebaseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;

class ProcessNotificationDelivery implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels, Batchable;

    protected $notification;
    protected $recipients;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(Notification $notification, array $recipients)
    {
        $this->notification = $notification;
        $this->recipients = $recipients;

        // Set queue priority based on notification priority
        switch ($notification->priority) {
            case 'urgent':
                $this->onQueue('urgent');
                break;
            case 'high':
                $this->onQueue('high');
                $this->delay(now()->addSeconds(30));
                break;
            case 'normal':
                $this->onQueue('default');
                $this->delay(now()->addMinutes(1));
                break;
            case 'low':
                $this->onQueue('low');
                $this->delay(now()->addMinutes(5));
                break;
            default:
                $this->onQueue('default');
        }
    }

    /**
     * Execute the job.
     */
    public function handle(FirebaseService $firebaseService): void
    {
        try {
            // Check if batch was cancelled (if using batches)
            if ($this->batch()?->cancelled()) {
                return;
            }

            Log::info('Processing notification delivery', [
                'notification_id' => $this->notification->id,
                'recipients_count' => count($this->recipients)
            ]);

            // Update notification status to sending
            $this->notification->update(['status' => Notification::STATUS_SENDING]);

            $successCount = 0;
            $failureCount = 0;
            $deliveryErrors = [];

            foreach ($this->recipients as $recipient) {
                $user = is_array($recipient) ? (object) $recipient : $recipient;

                // Create delivery record
                $delivery = NotificationDelivery::create([
                    'notification_id' => $this->notification->id,
                    'user_id' => $user->id,
                    'status' => NotificationDelivery::STATUS_PENDING
                ]);

                // Get user's active device tokens
                $deviceTokens = UserDeviceToken::where('user_id', $user->id)
                    ->active()
                    ->pluck('firebase_token')
                    ->toArray();

                if (empty($deviceTokens)) {
                    $delivery->markAsFailed('No active device tokens found');
                    $failureCount++;
                    $deliveryErrors[] = [
                        'user_id' => $user->id,
                        'error' => 'No active device tokens found'
                    ];
                    continue;
                }

                // Send to Firebase
                $firebaseResult = $this->sendFirebaseNotification($firebaseService, $deviceTokens, $this->notification);

                if ($firebaseResult['success']) {
                    $delivery->markAsSent($firebaseResult);
                    $successCount++;
                } else {
                    $delivery->markAsFailed($firebaseResult['error']);
                    $failureCount++;
                    $deliveryErrors[] = [
                        'user_id' => $user->id,
                        'error' => $firebaseResult['error']
                    ];
                }
            }

            // Update notification status and counts
            $this->updateNotificationStatus($successCount, $failureCount, $deliveryErrors);

            Log::info('Notification delivery completed', [
                'notification_id' => $this->notification->id,
                'success_count' => $successCount,
                'failure_count' => $failureCount
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process notification delivery', [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark notification as failed if this is the final attempt
            if ($this->attempts() >= $this->tries) {
                $this->notification->markAsFailed();
            }

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Send Firebase notification
     */
    private function sendFirebaseNotification(FirebaseService $firebaseService, array $tokens, Notification $notification): array
    {
        try {
            $firebaseNotification = [
                'title' => (string) $notification->title,
                'body' => (string) $notification->message
            ];

            // Prepare data payload - ensure all values are strings
            $firebaseData = [
                'notification_id' => (string) $notification->id,
                'type' => (string) $notification->type,
                'priority' => (string) $notification->priority
            ];

            // Add custom data if present, ensuring all values are strings
            if (!empty($notification->data) && is_array($notification->data)) {
                foreach ($notification->data as $key => $value) {
                    if ($value !== null) {
                        $firebaseData[$key] = is_array($value) ? json_encode($value) : (string) $value;
                    }
                }
            }

            // Send notification
            if (count($tokens) === 1) {
                return $firebaseService->sendToToken($tokens[0], $firebaseNotification, $firebaseData);
            } else {
                return $firebaseService->sendToTokens($tokens, $firebaseNotification, $firebaseData);
            }
        } catch (\Exception $e) {
            Log::error('Error in sendFirebaseNotification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
                'tokens' => $tokens
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update notification status based on delivery results
     */
    private function updateNotificationStatus(int $successCount, int $failureCount, array $deliveryErrors): void
    {
        if ($successCount > 0) {
            if ($failureCount === 0) {
                $this->notification->markAsSent();
            } else {
                // Partial delivery - some succeeded, some failed
                $this->notification->markAsPartial();
            }
        } else {
            // All deliveries failed
            $this->notification->markAsSent(); // Still mark as sent since notification was processed

            // Log all delivery failures for super admin
            Log::error('All notification deliveries failed', [
                'notification_id' => $this->notification->id,
                'school_id' => $this->notification->school_id,
                'total_recipients' => count($this->recipients),
                'delivery_errors' => $deliveryErrors
            ]);
        }

        // Update counts
        $this->notification->updateCounts();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Notification delivery job failed permanently', [
            'notification_id' => $this->notification->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Mark notification as failed
        $this->notification->markAsFailed();
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags()
    {
        return [
            'notification:' . $this->notification->id,
            'school:' . $this->notification->school_id,
            'priority:' . $this->notification->priority
        ];
    }
}
