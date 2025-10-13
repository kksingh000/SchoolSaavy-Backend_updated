<?php

namespace App\Jobs\Notifications;

use App\Models\Assignment;
use App\Models\Student;
use App\Models\Parents;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAssignmentSubmittedToParentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $submissionId;
    public int $assignmentId;
    public int $studentId;
    public int $parentId;
    public string $assignmentTitle;
    public string $subjectName;
    public string $studentName;
    public string $submittedAt;
    public bool $isLateSubmission;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $submissionId,
        int $assignmentId,
        int $studentId,
        int $parentId,
        string $assignmentTitle,
        string $subjectName,
        string $studentName,
        string $submittedAt,
        bool $isLateSubmission = false
    ) {
        $this->submissionId = $submissionId;
        $this->assignmentId = $assignmentId;
        $this->studentId = $studentId;
        $this->parentId = $parentId;
        $this->assignmentTitle = $assignmentTitle;
        $this->subjectName = $subjectName;
        $this->studentName = $studentName;
        $this->submittedAt = $submittedAt;
        $this->isLateSubmission = $isLateSubmission;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        Log::info('📱 Sending assignment submitted notification to parent', [
            'submission_id' => $this->submissionId,
            'assignment_id' => $this->assignmentId,
            'parent_id' => $this->parentId,
            'student_name' => $this->studentName,
        ]);

        // Load models
        $parent = Parents::with('user')->findOrFail($this->parentId);
        $student = Student::findOrFail($this->studentId);
        $assignment = Assignment::findOrFail($this->assignmentId);

        // Format submission time
        $submittedTime = date('d M Y h:i A', strtotime($this->submittedAt));

        // Build message with late indicator
        $lateIndicator = $this->isLateSubmission ? ' ⚠️ This was a late submission.' : '';
        
        $message = "✅ {$this->studentName} successfully submitted \"{$this->assignmentTitle}\" for {$this->subjectName}. Submitted: {$submittedTime}.{$lateIndicator}";

        $notificationData = [
            'school_id' => $student->school_id,
            'type' => 'assignment',
            'title' => '✅ Assignment Submitted',
            'message' => $message,
            'priority' => 'normal',
            'target_type' => 'parent',
            'target_ids' => [$parent->user->id],
            'data' => [
                'submission_id' => $this->submissionId,
                'assignment_id' => $this->assignmentId,
                'student_id' => $this->studentId,
                'student_name' => $this->studentName,
                'assignment_title' => $this->assignmentTitle,
                'subject' => $this->subjectName,
                'submitted_at' => $this->submittedAt,
                'is_late_submission' => $this->isLateSubmission,
                'action_url' => '/assignments/' . $this->assignmentId,
            ],
        ];

        $notification = $notificationService->sendNotification($notificationData);

        $notificationId = is_array($notification) ? ($notification['id'] ?? 'unknown') : $notification->id;

        Log::info('✅ Assignment submitted notification sent to parent', [
            'notification_id' => $notificationId,
            'submission_id' => $this->submissionId,
            'parent_id' => $this->parentId,
            'parent_user_id' => $parent->user->id,
        ]);
    }
}
