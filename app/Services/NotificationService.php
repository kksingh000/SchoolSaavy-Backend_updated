<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\UserDeviceToken;
use App\Models\ClassRoom;
use App\Jobs\ProcessNotificationDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class NotificationService extends BaseService
{
    private $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        parent::__construct();
        $this->firebaseService = $firebaseService;
    }

    protected function initializeModel()
    {
        $this->model = Notification::class;
    }

    /**
     * Send notification immediately (queued)
     */
    public function sendNotification(array $data): array
    {
        DB::beginTransaction();

        try {
            // Create notification record
            $notification = $this->createNotification($data);

            // Get target recipients
            $recipients = $this->getRecipients($notification);

            if (empty($recipients)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'No recipients found for this notification'
                ];
            }

            // Update total recipients count
            $notification->update(['total_recipients' => count($recipients)]);

            DB::commit();

            // Dispatch job to process notification delivery in background
            ProcessNotificationDelivery::dispatch($notification, $recipients);

            // Return immediate success response
            $response = [
                'success' => true,
                'notification_id' => $notification->id,
                'total_recipients' => count($recipients),
                'status' => 'queued',
                'message' => 'Notification queued for delivery successfully'
            ];

            Log::info('Notification queued for delivery', [
                'notification_id' => $notification->id,
                'school_id' => $notification->school_id,
                'total_recipients' => count($recipients),
                'priority' => $notification->priority
            ]);

            return $response;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create notification', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create notification: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Schedule notification for later
     */
    public function scheduleNotification(array $data): array
    {
        try {
            $data['status'] = Notification::STATUS_SCHEDULED;
            $notification = $this->createNotification($data);

            // Get target recipients count
            $recipients = $this->getRecipients($notification);
            $notification->update(['total_recipients' => count($recipients)]);

            return [
                'success' => true,
                'notification_id' => $notification->id,
                'scheduled_at' => $notification->scheduled_at,
                'total_recipients' => count($recipients),
                'message' => 'Notification scheduled successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to schedule notification', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'message' => 'Failed to schedule notification: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process scheduled notifications
     */
    public function processScheduledNotifications(): array
    {
        $dueNotifications = Notification::dueScheduled()->get();
        $processed = [];

        foreach ($dueNotifications as $notification) {
            try {
                $recipients = $this->getRecipients($notification);
                
                // Dispatch job to process the scheduled notification
                ProcessNotificationDelivery::dispatch($notification, $recipients);
                
                $processed[] = [
                    'notification_id' => $notification->id,
                    'success' => true,
                    'status' => 'queued',
                    'total_recipients' => count($recipients)
                ];

                Log::info('Scheduled notification queued for delivery', [
                    'notification_id' => $notification->id,
                    'total_recipients' => count($recipients)
                ]);
                
            } catch (\Exception $e) {
                $notification->markAsFailed();
                $processed[] = [
                    'notification_id' => $notification->id,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                
                Log::error('Failed to queue scheduled notification', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $processed;
    }

    /**
     * Create notification record
     */
    private function createNotification(array $data): Notification
    {
        $notificationData = [
            'school_id' => $data['school_id'],
            'title' => $data['title'],
            'message' => $data['message'],
            'type' => $data['type'] ?? Notification::TYPE_GENERAL,
            'priority' => $data['priority'] ?? Notification::PRIORITY_NORMAL,
            'sender_id' => $data['sender_id'] ?? (Auth::check() ? Auth::id() : null),
            'target_type' => $data['target_type'],
            'target_ids' => $data['target_ids'] ?? null,
            'target_classes' => $data['target_classes'] ?? null,
            'status' => $data['status'] ?? Notification::STATUS_SENDING,
            'scheduled_at' => isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null,
            'data' => $data['data'] ?? []
        ];

        return Notification::create($notificationData);
    }

    /**
     * Get recipients based on target type
     */
    private function getRecipients(Notification $notification): array
    {
        $recipients = [];
        $schoolId = $notification->school_id;

        switch ($notification->target_type) {
            case Notification::TARGET_ALL_PARENTS:
            case 'all_parents':
                $recipients = $this->getAllParents($schoolId);
                break;

            case Notification::TARGET_ALL_TEACHERS:
            case 'all_teachers':
                $recipients = $this->getAllTeachers($schoolId);
                break;

            case Notification::TARGET_SPECIFIC_USERS:
                $recipients = $this->getSpecificUsers($notification->target_ids);
                break;

            case Notification::TARGET_CLASS_PARENTS:
            case 'class_parents':
                $recipients = $this->getClassParents($schoolId, $notification->target_classes);
                break;

            case Notification::TARGET_CLASS_TEACHERS:
            case 'class_teachers':
                $recipients = $this->getClassTeachers($schoolId, $notification->target_classes);
                break;

            case 'all_school_users':
                $parents = $this->getAllParents($schoolId);
                $teachers = $this->getAllTeachers($schoolId);
                $recipients = array_merge($parents, $teachers);
                break;

            case 'class_all_users':
                $parents = $this->getClassParents($schoolId, $notification->target_classes);
                $teachers = $this->getClassTeachers($schoolId, $notification->target_classes);
                $recipients = array_merge($parents, $teachers);
                break;
        }

        return $recipients;
    }

    /**
     * Get all parents in school
     */
    private function getAllParents(int $schoolId): array
    {
        return User::where('user_type', 'parent')
            ->where('is_active', true)
            ->whereHas('parent.students', function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->with('activeDeviceTokens')
            ->get()
            ->toArray();
    }

    /**
     * Get all teachers in school
     */
    private function getAllTeachers(int $schoolId): array
    {
        return User::where('user_type', 'teacher')
            ->where('is_active', true)
            ->whereHas('teacher', function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->with('activeDeviceTokens')
            ->get()
            ->toArray();
    }

    /**
     * Get specific users by IDs
     */
    private function getSpecificUsers(array $userIds): array
    {
        return User::whereIn('id', $userIds)
            ->where('is_active', true)
            ->with('activeDeviceTokens')
            ->get()
            ->toArray();
    }

    /**
     * Get parents of students in specific classes
     */
    private function getClassParents(int $schoolId, array $classIds): array
    {
        return User::where('user_type', 'parent')
            ->where('is_active', true)
            ->whereHas('parent.students.classes', function ($query) use ($classIds) {
                $query->whereIn('classes.id', $classIds);
            })
            ->with('activeDeviceTokens')
            ->get()
            ->toArray();
    }

    /**
     * Get teachers assigned to specific classes
     */
    private function getClassTeachers(int $schoolId, array $classIds): array
    {
        return User::where('user_type', 'teacher')
            ->where('is_active', true)
            ->whereHas('teacher', function ($query) use ($schoolId, $classIds) {
                $query->where('school_id', $schoolId)
                    ->whereHas('classes', function ($q) use ($classIds) {
                        $q->whereIn('id', $classIds);
                    });
            })
            ->with('activeDeviceTokens')
            ->get()
            ->toArray();
    }

    /**
     * Send notification to recipients
     * Note: Delivery failures don't affect notification success status
     */
    private function sendToRecipients(Notification $notification, array $recipients): array
    {
        $notification->update(['status' => Notification::STATUS_SENDING]);

        $successCount = 0;
        $failureCount = 0;
        $deliveries = [];
        $deliveryErrors = [];

        foreach ($recipients as $recipient) {
            $user = is_array($recipient) ? (object) $recipient : $recipient;

            // Create delivery record
            $delivery = NotificationDelivery::create([
                'notification_id' => $notification->id,
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
            $firebaseResult = $this->sendFirebaseNotification($deviceTokens, $notification);

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

            $deliveries[] = $delivery;
        }

        // Update notification status based on delivery results
        // But never mark as "failed" - notification creation was successful
        if ($successCount > 0) {
            if ($failureCount === 0) {
                $notification->markAsSent();
            } else {
                // Partial delivery - some succeeded, some failed
                $notification->markAsPartial();
            }
        } else {
            // All deliveries failed, but notification creation was successful
            // Mark as "sent" with delivery issues for super admin monitoring
            $notification->markAsSent();

            // Log all delivery failures for super admin
            Log::error('All notification deliveries failed', [
                'notification_id' => $notification->id,
                'school_id' => $notification->school_id,
                'total_recipients' => count($recipients),
                'delivery_errors' => $deliveryErrors
            ]);
        }

        return [
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'deliveries' => $deliveries,
            'delivery_errors' => $deliveryErrors
        ];
    }

    /**
     * Send Firebase notification
     */
    private function sendFirebaseNotification(array $tokens, Notification $notification): array
    {
        $firebaseNotification = [
            'title' => (string) $notification->title,
            'body' => (string) $notification->message
        ];

        // Prepare data payload with proper string conversion
        $firebaseData = [
            'notification_id' => (string) $notification->id,
            'type' => (string) $notification->type,
            'priority' => (string) $notification->priority,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
        ];

        // Add custom data if present, ensuring all values are strings
        if (!empty($notification->data)) {
            foreach ($notification->data as $key => $value) {
                $firebaseData[$key] = is_array($value) ? json_encode($value) : (string) $value;
            }
        }

        if (count($tokens) === 1) {
            return $this->firebaseService->sendToToken($tokens[0], $firebaseNotification, $firebaseData);
        } else {
            return $this->firebaseService->sendToTokens($tokens, $firebaseNotification, $firebaseData);
        }
    }

    /**
     * Get notification statistics
     */
    public function getNotificationStats(int $schoolId, array $filters = []): array
    {
        $query = Notification::forSchool($schoolId);

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $notifications = $query->get();

        return [
            'total_notifications' => $notifications->count(),
            'sent_notifications' => $notifications->where('status', Notification::STATUS_SENT)->count(),
            'failed_notifications' => $notifications->where('status', Notification::STATUS_FAILED)->count(),
            'scheduled_notifications' => $notifications->where('status', Notification::STATUS_SCHEDULED)->count(),
            'total_recipients' => $notifications->sum('total_recipients'),
            'total_sent' => $notifications->sum('sent_count'),
            'total_delivered' => $notifications->sum('delivered_count'),
            'total_read' => $notifications->sum('read_count'),
            'average_delivery_rate' => $this->calculateAverageDeliveryRate($notifications),
            'average_read_rate' => $this->calculateAverageReadRate($notifications)
        ];
    }

    /**
     * Calculate average delivery rate
     */
    private function calculateAverageDeliveryRate($notifications): float
    {
        $totalRecipients = $notifications->sum('total_recipients');
        $totalSent = $notifications->sum('sent_count');

        return $totalRecipients > 0 ? ($totalSent / $totalRecipients) * 100 : 0;
    }

    /**
     * Calculate average read rate
     */
    private function calculateAverageReadRate($notifications): float
    {
        $totalSent = $notifications->sum('sent_count');
        $totalRead = $notifications->sum('read_count');

        return $totalSent > 0 ? ($totalRead / $totalSent) * 100 : 0;
    }

    /**
     * Mark notification as read for user
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $delivery = NotificationDelivery::where('notification_id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if ($delivery) {
            $delivery->markAsRead();

            // Update notification read count
            $notification = $delivery->notification;
            $notification->updateCounts();

            return true;
        }

        return false;
    }

    /**
     * Mark notification as acknowledged for user
     */
    public function markAsAcknowledged(int $notificationId, int $userId): bool
    {
        $delivery = NotificationDelivery::where('notification_id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if ($delivery) {
            $delivery->markAsAcknowledged();

            // Update notification read count
            $notification = $delivery->notification;
            $notification->updateCounts();

            return true;
        }

        return false;
    }

    /**
     * Get user's notifications
     */
    public function getUserNotifications(int $userId, array $filters = [])
    {
        $query = NotificationDelivery::where('user_id', $userId)
            ->with('notification');

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $query->whereHas('notification', function ($q) use ($filters) {
                $q->where('type', $filters['type']);
            });
        }

        if (isset($filters['unread_only']) && $filters['unread_only']) {
            $query->where('status', '!=', NotificationDelivery::STATUS_READ)
                ->where('status', '!=', NotificationDelivery::STATUS_ACKNOWLEDGED);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get unread count for user
     */
    public function getUnreadCount(int $userId): int
    {
        return NotificationDelivery::where('user_id', $userId)
            ->whereNotIn('status', [
                NotificationDelivery::STATUS_READ,
                NotificationDelivery::STATUS_ACKNOWLEDGED
            ])
            ->count();
    }

    /**
     * Register device token for user
     */
    public function registerDeviceToken(array $data): array
    {
        try {
            $existingToken = UserDeviceToken::where('user_id', $data['user_id'])
                ->where('device_id', $data['device_id'])
                ->first();

            if ($existingToken) {
                $existingToken->update([
                    'firebase_token' => $data['firebase_token'],
                    'device_type' => $data['device_type'] ?? null,
                    'app_version' => $data['app_version'] ?? null,
                    'device_name' => $data['device_name'] ?? null,
                    'is_active' => true,
                    'last_used_at' => now()
                ]);

                $token = $existingToken;
            } else {
                $token = UserDeviceToken::create(array_merge($data, [
                    'is_active' => true,
                    'last_used_at' => now()
                ]));
            }

            return [
                'success' => true,
                'token_id' => $token->id,
                'message' => 'Device token registered successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to register device token', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'message' => 'Failed to register device token: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get notification delivery status with progress
     */
    public function getNotificationStatus(int $notificationId): array
    {
        $notification = Notification::with(['deliveries' => function($query) {
            $query->select('notification_id', 'status', DB::raw('count(*) as count'))
                  ->groupBy('notification_id', 'status');
        }])->find($notificationId);

        if (!$notification) {
            return [
                'success' => false,
                'message' => 'Notification not found'
            ];
        }

        $statusCounts = [];
        foreach ($notification->deliveries as $delivery) {
            $statusCounts[$delivery->status] = $delivery->count;
        }

        $totalRecipients = $notification->total_recipients;
        $processedCount = array_sum($statusCounts);
        $progressPercentage = $totalRecipients > 0 ? ($processedCount / $totalRecipients) * 100 : 0;

        return [
            'success' => true,
            'data' => [
                'notification_id' => $notification->id,
                'status' => $notification->status,
                'priority' => $notification->priority,
                'total_recipients' => $totalRecipients,
                'processed_count' => $processedCount,
                'progress_percentage' => round($progressPercentage, 2),
                'delivery_status' => [
                    'pending' => $statusCounts['pending'] ?? 0,
                    'sent' => $statusCounts['sent'] ?? 0,
                    'delivered' => $statusCounts['delivered'] ?? 0,
                    'read' => $statusCounts['read'] ?? 0,
                    'acknowledged' => $statusCounts['acknowledged'] ?? 0,
                    'failed' => $statusCounts['failed'] ?? 0
                ],
                'created_at' => $notification->created_at,
                'updated_at' => $notification->updated_at
            ]
        ];
    }

    /**
     * Deactivate device token
     */
    public function deactivateDeviceToken(int $userId, string $deviceId): bool
    {
        $token = UserDeviceToken::where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->first();

        if ($token) {
            $token->deactivate();
            return true;
        }

        return false;
    }
}
