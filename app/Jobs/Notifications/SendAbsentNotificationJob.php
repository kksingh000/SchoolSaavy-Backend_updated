<?php

namespace App\Jobs\Notifications;

use App\Models\Student;
use App\Models\User;
use App\Models\Attendance;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAbsentNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Student $student;
    public User $parent;
    public int $attendanceId;  // Changed from Attendance model to ID
    public string $date;

    /**
     * Create a new job instance.
     */
    public function __construct(Student $student, User $parent, int $attendanceId, string $date)
    {
        $this->student = $student;
        $this->parent = $parent;
        $this->attendanceId = $attendanceId;
        $this->date = $date;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            $studentName = $this->student->first_name . ' ' . $this->student->last_name;
            $formattedDate = \Carbon\Carbon::parse($this->date)->format('M d, Y');

            $notificationData = [
                'school_id' => $this->student->school_id,
                'type' => 'attendance',
                'title' => '❌ Student Absent',
                'message' => "{$studentName} was marked absent today ({$formattedDate})",
                'target_type' => 'parent',
                'target_ids' => [$this->parent->id],
                'sender_id' => null,  // System generated, no specific sender
                'is_urgent' => false,
                'priority' => 'high',
                'data' => [
                    'student_id' => $this->student->id,
                    'student_name' => $studentName,
                    'attendance_id' => $this->attendanceId,  // Use ID instead of model property
                    'attendance_status' => 'absent',
                    'date' => $this->date,
                    'action_url' => '/attendance/details/' . $this->student->id,
                ],
            ];

            $result = $notificationService->sendNotification($notificationData);

            Log::info('Absent notification sent', [
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id,
                'date' => $this->date,
                'notification_result' => $result,
                'notification_id' => $result['notification_id'] ?? null,
                'success' => $result['success'] ?? false
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send absent notification', [
                'error' => $e->getMessage(),
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id
            ]);

            throw $e;
        }
    }
}
