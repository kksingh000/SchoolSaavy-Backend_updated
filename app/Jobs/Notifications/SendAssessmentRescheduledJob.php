<?php

namespace App\Jobs\Notifications;

use App\Models\Assessment;
use App\Models\Student;
use App\Models\Parents;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAssessmentRescheduledJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $assessmentId;
    public int $studentId;
    public int $parentId;
    public string $assessmentTitle;
    public string $oldDate;
    public ?string $oldStartTime;
    public ?string $oldEndTime;
    public string $newDate;
    public ?string $newStartTime;
    public ?string $newEndTime;
    public string $subjectName;
    public string $teacherName;
    public ?string $reason;
    public string $studentName;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $assessmentId,
        int $studentId,
        int $parentId,
        string $assessmentTitle,
        string $oldDate,
        ?string $oldStartTime,
        ?string $oldEndTime,
        string $newDate,
        ?string $newStartTime,
        ?string $newEndTime,
        string $subjectName,
        string $teacherName,
        ?string $reason,
        string $studentName
    ) {
        $this->assessmentId = $assessmentId;
        $this->studentId = $studentId;
        $this->parentId = $parentId;
        $this->assessmentTitle = $assessmentTitle;
        $this->oldDate = $oldDate;
        $this->oldStartTime = $oldStartTime;
        $this->oldEndTime = $oldEndTime;
        $this->newDate = $newDate;
        $this->newStartTime = $newStartTime;
        $this->newEndTime = $newEndTime;
        $this->subjectName = $subjectName;
        $this->teacherName = $teacherName;
        $this->reason = $reason;
        $this->studentName = $studentName;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        Log::info('📱 Sending assessment rescheduled notification', [
            'assessment_id' => $this->assessmentId,
            'student_id' => $this->studentId,
            'parent_id' => $this->parentId,
        ]);

        // Load models
        $student = Student::findOrFail($this->studentId);
        $parent = Parents::with('user')->findOrFail($this->parentId);
        $assessment = Assessment::findOrFail($this->assessmentId);

        // Format old and new date/time
        $oldDateTime = date('d M Y', strtotime($this->oldDate));
        if ($this->oldStartTime && $this->oldEndTime) {
            $oldDateTime .= ' at ' . date('h:i A', strtotime($this->oldStartTime)) . ' - ' . date('h:i A', strtotime($this->oldEndTime));
        } elseif ($this->oldStartTime) {
            $oldDateTime .= ' at ' . date('h:i A', strtotime($this->oldStartTime));
        }

        $newDateTime = date('d M Y', strtotime($this->newDate));
        if ($this->newStartTime && $this->newEndTime) {
            $newDateTime .= ' at ' . date('h:i A', strtotime($this->newStartTime)) . ' - ' . date('h:i A', strtotime($this->newEndTime));
        } elseif ($this->newStartTime) {
            $newDateTime .= ' at ' . date('h:i A', strtotime($this->newStartTime));
        }

        // Build message
        $reasonInfo = $this->reason ? " Reason: {$this->reason}." : '';
        
        $message = "🔄 IMPORTANT: Assessment rescheduled for {$this->studentName}. \"{$this->assessmentTitle}\" in {$this->subjectName} has been moved from {$oldDateTime} to {$newDateTime}.{$reasonInfo} Please note the new schedule.";

        $notificationData = [
            'school_id' => $student->school_id,
            'type' => 'assessment',
            'title' => '🔄 Assessment Rescheduled',
            'message' => $message,
            'priority' => 'high', // High priority for reschedule
            'target_type' => 'parent',
            'target_ids' => [$parent->user->id],
            'data' => [
                'assessment_id' => $this->assessmentId,
                'student_id' => $this->studentId,
                'student_name' => $this->studentName,
                'assessment_title' => $this->assessmentTitle,
                'subject' => $this->subjectName,
                'old_date' => $this->oldDate,
                'old_start_time' => $this->oldStartTime,
                'old_end_time' => $this->oldEndTime,
                'new_date' => $this->newDate,
                'new_start_time' => $this->newStartTime,
                'new_end_time' => $this->newEndTime,
                'reason' => $this->reason,
                'teacher_name' => $this->teacherName,
                'action_url' => '/assessments/' . $this->assessmentId,
            ],
        ];

        $notification = $notificationService->sendNotification($notificationData);

        $notificationId = is_array($notification) ? ($notification['id'] ?? 'unknown') : $notification->id;

        Log::info('✅ Assessment rescheduled notification sent successfully', [
            'notification_id' => $notificationId,
            'assessment_id' => $this->assessmentId,
            'student_id' => $this->studentId,
            'parent_id' => $this->parentId,
            'parent_user_id' => $parent->user->id,
        ]);
    }
}
