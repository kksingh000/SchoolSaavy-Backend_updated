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

class SendConsecutiveAbsenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Student $student;
    public User $parent;
    public int $consecutiveDays;
    public array $absentDates;

    /**
     * Create a new job instance.
     */
    public function __construct(Student $student, User $parent, int $consecutiveDays, array $absentDates)
    {
        $this->student = $student;
        $this->parent = $parent;
        $this->consecutiveDays = $consecutiveDays;
        $this->absentDates = $absentDates;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            $studentName = $this->student->first_name . ' ' . $this->student->last_name;
            $datesText = count($this->absentDates) > 0 ? ' (' . implode(', ', array_slice($this->absentDates, 0, 3)) . ')' : '';

            $notificationData = [
                'school_id' => $this->student->school_id,
                'type' => 'attendance',
                'title' => '🚨 Consecutive Absences Alert',
                'message' => "{$studentName} has been absent for {$this->consecutiveDays} consecutive days{$datesText}. Please contact the school.",
                'target_type' => 'parent',
                'target_ids' => [$this->parent->id],
                'is_urgent' => true,
                'priority' => 'high',
                'requires_acknowledgment' => true,
                'data' => [
                    'student_id' => $this->student->id,
                    'student_name' => $studentName,
                    'consecutive_days' => $this->consecutiveDays,
                    'absent_dates' => $this->absentDates,
                    'action_url' => '/attendance/report/' . $this->student->id,
                ],
            ];

            $notificationService->sendNotification($notificationData);

            Log::info('Consecutive absence alert sent', [
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id,
                'consecutive_days' => $this->consecutiveDays
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send consecutive absence alert', [
                'error' => $e->getMessage(),
                'student_id' => $this->student->id,
                'parent_id' => $this->parent->id
            ]);

            throw $e;
        }
    }
}
