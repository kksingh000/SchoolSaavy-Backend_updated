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

class SendLateNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Student $student;
    public User $parent;
    public Attendance $attendance;
    public string $date;
    public ?string $arrivalTime;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Student $student,
        User $parent,
        Attendance $attendance,
        string $date,
        ?string $arrivalTime = null
    ) {
        $this->student = $student;
        $this->parent = $parent;
        $this->attendance = $attendance;
        $this->date = $date;
        $this->arrivalTime = $arrivalTime;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            $studentName = $this->student->first_name . ' ' . $this->student->last_name;
            $formattedDate = \Carbon\Carbon::parse($this->date)->format('M d, Y');
            $timeText = $this->arrivalTime ? " at {$this->arrivalTime}" : '';

            $notificationData = [
                'school_id' => $this->student->school_id,
                'type' => 'attendance',
                'title' => '⏰ Student Arrived Late',
                'message' => "{$studentName} arrived late today{$timeText} ({$formattedDate})",
                'target_type' => 'parent',
                'target_ids' => [$this->parent->id],
                'sender_id' => $this->attendance->marked_by ?? null,
                'is_urgent' => false,
                'priority' => 'medium',
                'data' => [
                    'student_id' => $this->student->id,
                    'student_name' => $studentName,
                    'attendance_id' => $this->attendance->id,
                    'attendance_status' => $this->attendance->status,
                    'arrival_time' => $this->arrivalTime,
                    'date' => $this->date,
                    'action_url' => '/attendance/details/' . $this->student->id,
                ],
            ];

            $notificationService->sendNotification($notificationData);

            Log::info('Late arrival notification sent', [
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id,
                'date' => $this->date,
                'arrival_time' => $this->arrivalTime
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send late notification', [
                'error' => $e->getMessage(),
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id
            ]);

            throw $e;
        }
    }
}
