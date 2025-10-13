<?php

namespace App\Jobs\Notifications;

use App\Models\Student;
use App\Models\Parents;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendLowAttendanceNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $studentId;
    public int $parentId;
    public float $attendancePercentage;
    public int $totalDays;
    public int $presentDays;
    public int $absentDays;
    public string $periodStart;
    public string $periodEnd;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $studentId,
        int $parentId,
        float $attendancePercentage,
        int $totalDays,
        int $presentDays,
        int $absentDays,
        string $periodStart,
        string $periodEnd
    ) {
        $this->studentId = $studentId;
        $this->parentId = $parentId;
        $this->attendancePercentage = $attendancePercentage;
        $this->totalDays = $totalDays;
        $this->presentDays = $presentDays;
        $this->absentDays = $absentDays;
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            Log::info('📱 Sending low attendance notification', [
                'student_id' => $this->studentId,
                'parent_id' => $this->parentId,
                'attendance_percentage' => $this->attendancePercentage,
                'period' => $this->periodStart . ' to ' . $this->periodEnd,
            ]);

            // Load the student and parent
            $student = Student::findOrFail($this->studentId);
            $parent = Parents::with('user')->findOrFail($this->parentId);

            if (!$parent->user) {
                Log::warning('⚠️ Parent has no associated user account', [
                    'parent_id' => $this->parentId
                ]);
                return;
            }

            // Create notification data
            $notificationData = [
                'school_id' => $student->school_id, // Add school_id for multi-tenant isolation
                'type' => 'attendance',
                'title' => 'Low Attendance Alert',
                'message' => sprintf(
                    '%s has low attendance of %.1f%% (Present: %d/%d days, Absent: %d days) from %s to %s',
                    $student->first_name . ' ' . $student->last_name,
                    $this->attendancePercentage,
                    $this->presentDays,
                    $this->totalDays,
                    $this->absentDays,
                    date('M d, Y', strtotime($this->periodStart)),
                    date('M d, Y', strtotime($this->periodEnd))
                ),
                'data' => [
                    'student_id' => $this->studentId,
                    'student_name' => $student->first_name . ' ' . $student->last_name,
                    'attendance_percentage' => $this->attendancePercentage,
                    'total_days' => $this->totalDays,
                    'present_days' => $this->presentDays,
                    'absent_days' => $this->absentDays,
                    'period_start' => $this->periodStart,
                    'period_end' => $this->periodEnd,
                    'action_url' => '/attendance/students/' . $this->studentId,
                ],
                'priority' => 'high',
                'target_type' => 'parent',
                'target_ids' => [$parent->user->id], // Must be array for NotificationService
            ];

            // Send the notification
            $result = $notificationService->sendNotification($notificationData);

            if ($result['success']) {
                Log::info('✅ Low attendance notification sent successfully', [
                    'notification_id' => $result['notification_id'] ?? null,
                    'parent_id' => $this->parentId,
                    'student_id' => $this->studentId,
                ]);
            } else {
                Log::error('❌ Failed to send low attendance notification', [
                    'error' => $result['error'] ?? 'Unknown error',
                    'parent_id' => $this->parentId,
                    'student_id' => $this->studentId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Exception in SendLowAttendanceNotificationJob', [
                'student_id' => $this->studentId,
                'parent_id' => $this->parentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
