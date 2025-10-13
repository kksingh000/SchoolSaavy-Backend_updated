<?php

namespace App\Jobs\Notifications;

use App\Models\Assignment;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAssignmentSubmittedToTeacherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $submissionId;
    public int $assignmentId;
    public int $studentId;
    public int $teacherId;
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
        int $teacherId,
        string $assignmentTitle,
        string $subjectName,
        string $studentName,
        string $submittedAt,
        bool $isLateSubmission = false
    ) {
        $this->submissionId = $submissionId;
        $this->assignmentId = $assignmentId;
        $this->studentId = $studentId;
        $this->teacherId = $teacherId;
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
        Log::info('📱 Sending assignment submitted notification to teacher', [
            'submission_id' => $this->submissionId,
            'assignment_id' => $this->assignmentId,
            'teacher_id' => $this->teacherId,
            'student_name' => $this->studentName,
        ]);

        // Load models
        $teacher = Teacher::with('user')->findOrFail($this->teacherId);
        $student = Student::findOrFail($this->studentId);
        $assignment = Assignment::findOrFail($this->assignmentId);

        // Format submission time
        $submittedTime = date('d M Y h:i A', strtotime($this->submittedAt));

        // Build message with late indicator
        $lateIndicator = $this->isLateSubmission ? ' (Late Submission)' : '';
        
        $message = "✅ {$this->studentName} submitted \"{$this->assignmentTitle}\" in {$this->subjectName}. Submitted: {$submittedTime}{$lateIndicator}. Please review and grade.";

        $notificationData = [
            'school_id' => $teacher->school_id,
            'type' => 'assignment',
            'title' => '✅ Assignment Submitted',
            'message' => $message,
            'priority' => $this->isLateSubmission ? 'high' : 'normal',
            'target_type' => 'teacher',
            'target_ids' => [$teacher->user->id],
            'data' => [
                'submission_id' => $this->submissionId,
                'assignment_id' => $this->assignmentId,
                'student_id' => $this->studentId,
                'student_name' => $this->studentName,
                'assignment_title' => $this->assignmentTitle,
                'subject' => $this->subjectName,
                'submitted_at' => $this->submittedAt,
                'is_late_submission' => $this->isLateSubmission,
                'action_url' => '/assignments/' . $this->assignmentId . '/submissions/' . $this->submissionId,
            ],
        ];

        $notification = $notificationService->sendNotification($notificationData);

        $notificationId = is_array($notification) ? ($notification['id'] ?? 'unknown') : $notification->id;

        Log::info('✅ Assignment submitted notification sent to teacher', [
            'notification_id' => $notificationId,
            'submission_id' => $this->submissionId,
            'teacher_id' => $this->teacherId,
            'teacher_user_id' => $teacher->user->id,
        ]);
    }
}
