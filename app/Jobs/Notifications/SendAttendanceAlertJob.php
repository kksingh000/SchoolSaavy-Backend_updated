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

class SendAttendanceAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Student $student;
    public User $parent;
    public float $attendancePercentage;
    public float $threshold;
    public int $totalDays;
    public int $presentDays;
    public int $absentDays;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Student $student,
        User $parent,
        float $attendancePercentage,
        float $threshold,
        int $totalDays,
        int $presentDays,
        int $absentDays
    ) {
        $this->student = $student;
        $this->parent = $parent;
        $this->attendancePercentage = $attendancePercentage;
        $this->threshold = $threshold;
        $this->totalDays = $totalDays;
        $this->presentDays = $presentDays;
        $this->absentDays = $absentDays;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            $studentName = $this->student->first_name . ' ' . $this->student->last_name;
            $percentage = number_format($this->attendancePercentage, 1);

            $notificationData = [
                'school_id' => $this->student->school_id,
                'type' => 'attendance',
                'title' => '⚠️ Low Attendance Alert',
                'message' => "{$studentName}'s attendance is {$percentage}% (Below {$this->threshold}% threshold). Present: {$this->presentDays}/{$this->totalDays} days",
                'target_type' => 'parent',
                'target_ids' => [$this->parent->id],
                'is_urgent' => true,
                'priority' => 'high',
                'requires_acknowledgment' => true,
                'data' => [
                    'student_id' => $this->student->id,
                    'student_name' => $studentName,
                    'attendance_percentage' => $this->attendancePercentage,
                    'threshold' => $this->threshold,
                    'total_days' => $this->totalDays,
                    'present_days' => $this->presentDays,
                    'absent_days' => $this->absentDays,
                    'action_url' => '/attendance/report/' . $this->student->id,
                ],
            ];

            $notificationService->sendNotification($notificationData);

            Log::info('Low attendance alert sent', [
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id,
                'attendance_percentage' => $this->attendancePercentage
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send low attendance alert', [
                'error' => $e->getMessage(),
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id
            ]);

            throw $e;
        }
    }
}
