<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendNotificationRequest;
use App\Http\Requests\RegisterDeviceTokenRequest;
use App\Services\NotificationService;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\UserDeviceToken;
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
            ->select([
                'id',
                'title',
                'message',
                'type',
                'priority',
                'status',
                'total_recipients',
                'successful_sends',
                'failed_sends',
                'sender_id',
                'scheduled_at',
                'sent_at',
                'is_urgent',
                'requires_acknowledgment',
                'created_at',
                'updated_at'
            ])
            ->with(['sender:id,name'])
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
     * Get single notification details with full delivery information
     * This endpoint includes all delivery details including failed attempts
     */
    public function show(int $id)
    {
        $school = auth()->user()->getSchool();

        $notification = Notification::forSchool($school->id)
            ->with([
                'sender:id,name',
                'deliveries.user:id,name,email',
                'deliveries' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }
            ])
            ->findOrFail($id);

        return $this->successResponse($notification, 'Notification retrieved successfully');
    }

    /**
     * Send notification immediately or schedule for later
     * If scheduled_at is provided, the notification will be scheduled
     * Otherwise, it will be sent immediately
     */
    public function sendNotification(SendNotificationRequest $request)
    {
        $school = auth()->user()->getSchool();

        $data = array_merge($request->validated(), [
            'school_id' => $school->id,
            'sender_id' => auth()->id()
        ]);

        // Determine if this is a scheduled notification
        $isScheduled = !empty($data['scheduled_at']);

        if ($isScheduled) {
            $result = $this->notificationService->scheduleNotification($data);
            $successMessage = 'Notification scheduled successfully';
        } else {
            $result = $this->notificationService->sendNotification($data);
            $successMessage = 'Notification sent successfully';
        }

        if ($result['success']) {
            return $this->successResponse($result, $successMessage);
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

    /**
     * Get all school members (teachers, parents, students) for notification targeting
     * Simplified and optimized using separate queries with Laravel's built-in pagination
     */
    public function getSchoolMembers(Request $request)
    {
        $school = auth()->user()->getSchool();
        $schoolId = $school->id;
        $perPage = $request->per_page ?? 50;
        $search = $request->search;
        $roleFilter = $request->role;

        $members = collect();
        $summary = [];

        // Get members based on role filter - much simpler approach
        if (!$roleFilter || $roleFilter === 'teacher') {
            $teachers = $this->getTeachers($schoolId, $search);
            $members = $members->merge($teachers);
        }

        if (!$roleFilter || $roleFilter === 'parent') {
            $parents = $this->getParents($schoolId, $search);
            $members = $members->merge($parents);
        }

        if (!$roleFilter || $roleFilter === 'student') {
            $students = $this->getStudents($schoolId, $search);
            $members = $members->merge($students);
        }

        // Sort members by name
        $members = $members->sortBy('name')->values();

        // Create paginator manually for collection
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $itemsForCurrentPage = $members->slice($offset, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $itemsForCurrentPage,
            $members->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
                'query' => request()->query()
            ]
        );

        // Get summary counts only if showing all roles
        if (!$roleFilter) {
            $summary = [
                'total_teachers' => $members->where('role', 'teacher')->count(),
                'total_parents' => $members->where('role', 'parent')->count(),
                'total_students' => $members->where('role', 'student')->count(),
                'total_members' => $members->count()
            ];
        }

        $response = [
            'members' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'path' => $paginator->path(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl()
            ]
        ];

        if (!empty($summary)) {
            $response['summary'] = $summary;
        }

        return $this->successResponse($response, 'School members retrieved successfully');
    }

    /**
     * Health check for notification system
     * Validates Firebase config, device tokens, and recent delivery status
     */
    public function healthCheck()
    {
        $checks = [
            'firebase_config' => $this->checkFirebaseConfig(),
            'service_account' => $this->checkServiceAccount(),
            'device_tokens' => $this->checkDeviceTokens(),
            'recent_deliveries' => $this->checkRecentDeliveries()
        ];

        $overallStatus = collect($checks)->every(fn($check) => $check['status'] === 'ok') ? 'healthy' : 'issues_detected';

        return response()->json([
            'status' => $overallStatus,
            'checks' => $checks,
            'timestamp' => now(),
            'recommendations' => $this->getHealthRecommendations($checks)
        ]);
    }

    /**
     * Check Firebase configuration
     */
    private function checkFirebaseConfig(): array
    {
        try {
            $projectId = config('services.firebase.project_id');
            $serviceAccountPath = config('services.firebase.service_account_path');

            return [
                'status' => ($projectId && $serviceAccountPath) ? 'ok' : 'error',
                'details' => [
                    'project_id_set' => !empty($projectId),
                    'service_account_path_set' => !empty($serviceAccountPath),
                    'project_id' => $projectId ? 'configured' : 'missing'
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Check Firebase service account file
     */
    private function checkServiceAccount(): array
    {
        try {
            $path = config('services.firebase.service_account_path');
            $exists = $path && file_exists($path);

            return [
                'status' => $exists ? 'ok' : 'error',
                'details' => [
                    'file_exists' => $exists,
                    'path' => $path,
                    'readable' => $exists && is_readable($path),
                    'size' => $exists ? filesize($path) : 0
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Check device tokens status
     */
    private function checkDeviceTokens(): array
    {
        try {
            $total = \App\Models\UserDeviceToken::count();
            $active = \App\Models\UserDeviceToken::where('is_active', true)->count();
            $recent = \App\Models\UserDeviceToken::where('last_used_at', '>', now()->subDays(7))->count();

            return [
                'status' => $active > 0 ? 'ok' : 'warning',
                'details' => [
                    'total_tokens' => $total,
                    'active_tokens' => $active,
                    'recently_used' => $recent,
                    'inactive_tokens' => $total - $active
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Check recent delivery performance
     */
    private function checkRecentDeliveries(): array
    {
        try {
            $recent = NotificationDelivery::where('created_at', '>', now()->subHour())->count();
            $failed = NotificationDelivery::where('created_at', '>', now()->subHour())
                ->where('status', 'failed')->count();
            $sent = NotificationDelivery::where('created_at', '>', now()->subHour())
                ->where('status', 'sent')->count();

            $failureRate = $recent > 0 ? ($failed / $recent) * 100 : 0;
            $successRate = $recent > 0 ? ($sent / $recent) * 100 : 0;

            return [
                'status' => $failureRate < 50 ? 'ok' : 'warning',
                'details' => [
                    'recent_deliveries' => $recent,
                    'failed_deliveries' => $failed,
                    'sent_deliveries' => $sent,
                    'failure_rate_percent' => round($failureRate, 2),
                    'success_rate_percent' => round($successRate, 2)
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get health recommendations based on check results
     */
    private function getHealthRecommendations(array $checks): array
    {
        $recommendations = [];

        if ($checks['firebase_config']['status'] === 'error') {
            $recommendations[] = 'Configure Firebase settings in .env file (FIREBASE_PROJECT_ID, FIREBASE_SERVICE_ACCOUNT_PATH)';
        }

        if ($checks['service_account']['status'] === 'error') {
            $recommendations[] = 'Upload Firebase service account JSON file to storage/app/firebase-service-account.json';
        }

        if ($checks['device_tokens']['status'] === 'warning') {
            $recommendations[] = 'Users need to register device tokens for push notifications';
        }

        if ($checks['recent_deliveries']['status'] === 'warning') {
            $recommendations[] = 'High failure rate detected - check Firebase configuration and device token validity';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Notification system is healthy - no issues detected';
        }

        return $recommendations;
    }

    /**
     * Get delivery issues for super admin monitoring
     * Regular school admins don't see these Firebase delivery issues
     */
    public function getDeliveryIssues(Request $request)
    {
        // Only super admins can see delivery issues
        if (auth()->user()->user_type !== 'super_admin') {
            return $this->errorResponse('Access denied. This endpoint is for super admin monitoring only.', null, 403);
        }

        $query = NotificationDelivery::with([
            'notification:id,title,school_id,created_at',
            'user:id,name,email'
        ])
            ->where('status', NotificationDelivery::STATUS_FAILED);

        // Apply filters
        if ($request->has('school_id') && $request->school_id) {
            $query->whereHas('notification', function ($q) use ($request) {
                $q->where('school_id', $request->school_id);
            });
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('error_type') && $request->error_type) {
            $query->where('failure_reason', 'like', '%' . $request->error_type . '%');
        }

        $deliveryIssues = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        // Group by error types for summary
        $errorSummary = NotificationDelivery::where('status', NotificationDelivery::STATUS_FAILED)
            ->when($request->school_id, function ($q, $schoolId) {
                $q->whereHas('notification', fn($nq) => $nq->where('school_id', $schoolId));
            })
            ->when($request->date_from, function ($q, $date) {
                $q->whereDate('created_at', '>=', $date);
            })
            ->when($request->date_to, function ($q, $date) {
                $q->whereDate('created_at', '<=', $date);
            })
            ->selectRaw('failure_reason, COUNT(*) as count')
            ->groupBy('failure_reason')
            ->orderBy('count', 'desc')
            ->get();

        $response = [
            'delivery_issues' => $deliveryIssues,
            'error_summary' => $errorSummary,
            'total_failed_deliveries' => $deliveryIssues->total()
        ];

        return $this->successResponse($response, 'Delivery issues retrieved successfully');
    }

    /**
     * Get notification statistics with delivery health for super admin
     */
    public function getSuperAdminStats(Request $request)
    {
        // Only super admins can see detailed delivery stats
        if (auth()->user()->user_type !== 'super_admin') {
            return $this->errorResponse('Access denied. This endpoint is for super admin monitoring only.', null, 403);
        }

        $schoolId = $request->school_id;
        $dateFrom = $request->date_from ?? now()->subDays(7)->toDateString();
        $dateTo = $request->date_to ?? now()->toDateString();

        $query = Notification::query();

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        $notifications = $query->get();

        $totalDeliveries = NotificationDelivery::whereHas('notification', function ($q) use ($schoolId, $dateFrom, $dateTo) {
            if ($schoolId) {
                $q->where('school_id', $schoolId);
            }
            $q->whereBetween('created_at', [$dateFrom, $dateTo]);
        })
            ->count();

        $failedDeliveries = NotificationDelivery::where('status', NotificationDelivery::STATUS_FAILED)
            ->whereHas('notification', function ($q) use ($schoolId, $dateFrom, $dateTo) {
                if ($schoolId) {
                    $q->where('school_id', $schoolId);
                }
                $q->whereBetween('created_at', [$dateFrom, $dateTo]);
            })
            ->count();

        $deliverySuccessRate = $totalDeliveries > 0 ? (($totalDeliveries - $failedDeliveries) / $totalDeliveries) * 100 : 100;

        // Get top failure reasons
        $topFailureReasons = NotificationDelivery::where('status', NotificationDelivery::STATUS_FAILED)
            ->whereHas('notification', function ($q) use ($schoolId, $dateFrom, $dateTo) {
                if ($schoolId) {
                    $q->where('school_id', $schoolId);
                }
                $q->whereBetween('created_at', [$dateFrom, $dateTo]);
            })
            ->selectRaw('failure_reason, COUNT(*) as count')
            ->groupBy('failure_reason')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        $stats = [
            'notification_stats' => [
                'total_notifications' => $notifications->count(),
                'sent_notifications' => $notifications->where('status', Notification::STATUS_SENT)->count(),
                'partial_notifications' => $notifications->where('status', Notification::STATUS_PARTIAL)->count(),
                'scheduled_notifications' => $notifications->where('status', Notification::STATUS_SCHEDULED)->count(),
            ],
            'delivery_health' => [
                'total_deliveries' => $totalDeliveries,
                'failed_deliveries' => $failedDeliveries,
                'delivery_success_rate' => round($deliverySuccessRate, 2),
                'top_failure_reasons' => $topFailureReasons
            ],
            'system_health' => [
                'active_device_tokens' => UserDeviceToken::where('is_active', true)->count(),
                'recent_token_registrations' => UserDeviceToken::where('created_at', '>', now()->subDays(7))->count(),
            ]
        ];

        return $this->successResponse($stats, 'Super admin notification statistics retrieved successfully');
    }

    /**
     * Get teachers for the school
     */
    private function getTeachers($schoolId, $search = null)
    {
        $query = \App\Models\Teacher::where('school_id', $schoolId)
            ->with('user:id,name,email')
            ->whereHas('user');

        if ($search) {
            $searchTerm = '%' . $search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('employee_id', 'like', $searchTerm)
                    ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                        $userQuery->where('name', 'like', $searchTerm)
                            ->orWhere('email', 'like', $searchTerm);
                    });
            });
        }

        return $query->get()->map(function ($teacher) {
            return [
                'id' => $teacher->user->id,
                'name' => $teacher->user->name,
                'email' => $teacher->user->email,
                'role' => 'teacher',
                'profile_type' => 'Teacher',
                'identifier' => $teacher->employee_id,
                'children' => null,
                'children_count' => 0
            ];
        });
    }

    /**
     * Get parents for the school
     */
    private function getParents($schoolId, $search = null)
    {
        $query = \App\Models\Parents::whereHas('students', function ($q) use ($schoolId) {
            $q->where('school_id', $schoolId);
        })
            ->with([
                'user:id,name,email',
                'students:id,first_name,last_name'
            ])
            ->whereHas('user');

        if ($search) {
            $searchTerm = '%' . $search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->whereHas('user', function ($userQuery) use ($searchTerm) {
                    $userQuery->where('name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm);
                })
                    ->orWhereHas('students', function ($studentQuery) use ($searchTerm) {
                        $studentQuery->where('first_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm);
                    });
            });
        }

        return $query->get()->map(function ($parent) {
            $children = $parent->students->map(function ($student) {
                return $student->first_name . ' ' . $student->last_name;
            })->implode(', ');

            return [
                'id' => $parent->user->id,
                'name' => $parent->user->name,
                'email' => $parent->user->email,
                'role' => 'parent',
                'profile_type' => 'Parent',
                'identifier' => null,
                'children' => $children,
                'children_count' => $parent->students->count()
            ];
        });
    }

    /**
     * Get students for the school
     */
    private function getStudents($schoolId, $search = null)
    {
        $query = \App\Models\Student::where('school_id', $schoolId)
            ->where('is_active', true);

        if ($search) {
            $searchTerm = '%' . $search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', $searchTerm)
                    ->orWhere('last_name', 'like', $searchTerm)
                    ->orWhere('admission_number', 'like', $searchTerm);
            });
        }

        return $query->get()->map(function ($student) {
            return [
                'id' => $student->id,
                'name' => $student->first_name . ' ' . $student->last_name,
                'email' => null,
                'role' => 'student',
                'profile_type' => 'Student',
                'identifier' => $student->admission_number,
                'children' => null,
                'children_count' => 0
            ];
        });
    }
}
