<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendNotificationRequest;
use App\Http\Requests\ScheduleNotificationRequest;
use App\Http\Requests\RegisterDeviceTokenRequest;
use App\Services\NotificationService;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use Illuminate\Http\Request;

class NotificationController extends BaseController
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get all notifications for school with pagination
     */
    public function index(Request $request)
    {
        $school = auth()->user()->getSchool();

        $query = Notification::forSchool($school->id)
            ->with(['sender:id,first_name,last_name', 'deliveries'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority') && $request->priority) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $notifications = $query->paginate($request->per_page ?? 15);

        return $this->successResponse($notifications, 'Notifications retrieved successfully');
    }

    /**
     * Get single notification details
     */
    public function show(int $id)
    {
        $school = auth()->user()->getSchool();

        $notification = Notification::forSchool($school->id)
            ->with([
                'sender:id,first_name,last_name',
                'deliveries.user:id,first_name,last_name,email',
                'deliveries' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }
            ])
            ->findOrFail($id);

        return $this->successResponse($notification, 'Notification retrieved successfully');
    }

    /**
     * Send notification immediately
     */
    public function sendNotification(SendNotificationRequest $request)
    {
        $school = auth()->user()->getSchool();

        $data = array_merge($request->validated(), [
            'school_id' => $school->id,
            'sender_id' => auth()->id()
        ]);

        $result = $this->notificationService->sendNotification($data);

        if ($result['success']) {
            return $this->successResponse($result, 'Notification sent successfully');
        }

        return $this->errorResponse($result['message'], null, 400);
    }

    /**
     * Schedule notification for later
     */
    public function scheduleNotification(ScheduleNotificationRequest $request)
    {
        $school = auth()->user()->getSchool();

        $data = array_merge($request->validated(), [
            'school_id' => $school->id,
            'sender_id' => auth()->id()
        ]);

        $result = $this->notificationService->scheduleNotification($data);

        if ($result['success']) {
            return $this->successResponse($result, 'Notification scheduled successfully');
        }

        return $this->errorResponse($result['message'], null, 400);
    }

    /**
     * Get notification statistics
     */
    public function getStats(Request $request)
    {
        $school = auth()->user()->getSchool();

        $filters = [
            'type' => $request->type,
            'status' => $request->status,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to
        ];

        $stats = $this->notificationService->getNotificationStats($school->id, array_filter($filters));

        return $this->successResponse($stats, 'Notification statistics retrieved successfully');
    }

    /**
     * Get user's notifications (for mobile app)
     */
    public function getUserNotifications(Request $request)
    {
        $filters = [
            'status' => $request->status,
            'type' => $request->type,
            'unread_only' => $request->boolean('unread_only'),
            'per_page' => $request->per_page ?? 15
        ];

        $notifications = $this->notificationService->getUserNotifications(
            auth()->id(),
            array_filter($filters)
        );

        return $this->successResponse($notifications, 'User notifications retrieved successfully');
    }

    /**
     * Get unread notifications count for user
     */
    public function getUnreadCount()
    {
        $count = $this->notificationService->getUnreadCount(auth()->id());

        return $this->successResponse(['unread_count' => $count], 'Unread count retrieved successfully');
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId)
    {
        $success = $this->notificationService->markAsRead($notificationId, auth()->id());

        if ($success) {
            return $this->successResponse(null, 'Notification marked as read');
        }

        return $this->errorResponse('Notification not found or already read', null, 404);
    }

    /**
     * Mark notification as acknowledged
     */
    public function markAsAcknowledged(int $notificationId)
    {
        $success = $this->notificationService->markAsAcknowledged($notificationId, auth()->id());

        if ($success) {
            return $this->successResponse(null, 'Notification acknowledged');
        }

        return $this->errorResponse('Notification not found', null, 404);
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead()
    {
        $deliveries = NotificationDelivery::where('user_id', auth()->id())
            ->whereNotIn('status', [
                NotificationDelivery::STATUS_READ,
                NotificationDelivery::STATUS_ACKNOWLEDGED
            ])
            ->get();

        foreach ($deliveries as $delivery) {
            $delivery->markAsRead();
        }

        return $this->successResponse(null, 'All notifications marked as read');
    }

    /**
     * Register device token for push notifications
     */
    public function registerDeviceToken(RegisterDeviceTokenRequest $request)
    {
        $data = array_merge($request->validated(), [
            'user_id' => auth()->id()
        ]);

        $result = $this->notificationService->registerDeviceToken($data);

        if ($result['success']) {
            return $this->successResponse($result, 'Device token registered successfully');
        }

        return $this->errorResponse($result['message'], null, 400);
    }

    /**
     * Deactivate device token
     */
    public function deactivateDeviceToken(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string'
        ]);

        $success = $this->notificationService->deactivateDeviceToken(
            auth()->id(),
            $request->device_id
        );

        if ($success) {
            return $this->successResponse(null, 'Device token deactivated successfully');
        }

        return $this->errorResponse('Device token not found', null, 404);
    }

    /**
     * Delete notification (admin only)
     */
    public function destroy(int $id)
    {
        $school = auth()->user()->getSchool();

        $notification = Notification::forSchool($school->id)->findOrFail($id);

        // Only allow deletion of draft or failed notifications
        if (!in_array($notification->status, [Notification::STATUS_DRAFT, Notification::STATUS_FAILED])) {
            return $this->errorResponse('Cannot delete sent notifications', null, 400);
        }

        $notification->delete();

        return $this->successResponse(null, 'Notification deleted successfully');
    }

    /**
     * Get notification types
     */
    public function getTypes()
    {
        $types = Notification::getTypes();
        return $this->successResponse($types, 'Notification types retrieved successfully');
    }

    /**
     * Get target types
     */
    public function getTargetTypes()
    {
        $targetTypes = Notification::getTargetTypes();
        return $this->successResponse($targetTypes, 'Target types retrieved successfully');
    }

    /**
     * Get priorities
     */
    public function getPriorities()
    {
        $priorities = Notification::getPriorities();
        return $this->successResponse($priorities, 'Priorities retrieved successfully');
    }

    /**
     * Retry failed notification
     */
    public function retryFailedNotification(int $id)
    {
        $school = auth()->user()->getSchool();

        $notification = Notification::forSchool($school->id)->findOrFail($id);

        if ($notification->status !== Notification::STATUS_FAILED) {
            return $this->errorResponse('Only failed notifications can be retried', null, 400);
        }

        // Get failed deliveries
        $failedDeliveries = $notification->failedDeliveries;

        if ($failedDeliveries->isEmpty()) {
            return $this->errorResponse('No failed deliveries to retry', null, 400);
        }

        // Reset notification status
        $notification->update(['status' => Notification::STATUS_SENDING]);

        // Retry failed deliveries
        $successCount = 0;
        $failureCount = 0;

        foreach ($failedDeliveries as $delivery) {
            // Get user's current active tokens
            $deviceTokens = $delivery->user->activeDeviceTokens()
                ->pluck('firebase_token')
                ->toArray();

            if (empty($deviceTokens)) {
                $delivery->markAsFailed('No active device tokens found');
                $failureCount++;
                continue;
            }

            // Try sending again
            $firebaseResult = $this->notificationService->sendFirebaseNotification(
                $deviceTokens,
                $notification
            );

            if ($firebaseResult['success']) {
                $delivery->markAsSent($firebaseResult);
                $successCount++;
            } else {
                $delivery->incrementRetry($firebaseResult['error']);
                $failureCount++;
            }
        }

        // Update notification status
        if ($failureCount === 0) {
            $notification->markAsSent();
        } elseif ($successCount === 0) {
            $notification->markAsFailed();
        } else {
            $notification->markAsPartial();
        }

        return $this->successResponse([
            'retry_success_count' => $successCount,
            'retry_failure_count' => $failureCount,
            'notification_status' => $notification->status
        ], 'Notification retry completed');
    }
}
