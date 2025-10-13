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

/**
 * Job: SendAssignmentGradedJob
 * 
 * Sends notification to ONE parent about their child's graded assignment
 * 
 * Notification Details:
 * - Type: assignment
 * - Priority: normal
 * - Recipients: Parent (user account)
 * - Action URL: Link to view assignment details
 */
class SendAssignmentGradedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $submissionId,
        public int $assignmentId,
        public int $studentId,
        public int $parentUserId,
        public string $assignmentTitle,
        public string $subjectName,
        public string $studentName,
        public ?float $marksObtained,
        public ?float $maxMarks,
        public ?float $percentage,
        public ?string $gradeLetter,
        public ?string $teacherFeedback,
        public string $gradedAt,
        public bool $hasNumericalGrade,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            Log::info("🎓 Sending assignment graded notification to parent", [
                'submission_id' => $this->submissionId,
                'assignment_id' => $this->assignmentId,
                'parent_user_id' => $this->parentUserId,
                'student_name' => $this->studentName,
            ]);

            // Get parent user
            $parentUser = User::find($this->parentUserId);
            if (!$parentUser) {
                Log::warning("⚠️ Parent user not found", [
                    'parent_user_id' => $this->parentUserId,
                ]);
                return;
            }

            // Get student to access school_id
            $student = Student::find($this->studentId);
            if (!$student) {
                Log::warning("⚠️ Student not found", [
                    'student_id' => $this->studentId,
                ]);
                return;
            }

            // Build the notification message based on grading type
            $message = $this->buildNotificationMessage();

            // Get the parent model (not user) to get parent ID
            $parent = $parentUser->parent;
            if (!$parent) {
                Log::warning("⚠️ Parent model not found for user", [
                    'parent_user_id' => $this->parentUserId,
                ]);
                return;
            }

            // Prepare notification data
            $notificationData = [
                'school_id' => $student->school_id,
                'type' => 'assignment',
                'title' => '✅ Assignment Graded',
                'message' => $message,
                'priority' => 'normal',
                'target_type' => 'parent',
                'target_ids' => [$parent->id], // Parent model ID, not user ID
                'sender_id' => null, // System generated
                'data' => [
                    'submission_id' => $this->submissionId,
                    'assignment_id' => $this->assignmentId,
                    'assignment_title' => $this->assignmentTitle,
                    'subject_name' => $this->subjectName,
                    'student_id' => $this->studentId,
                    'student_name' => $this->studentName,
                    'marks_obtained' => $this->marksObtained,
                    'max_marks' => $this->maxMarks,
                    'percentage' => $this->percentage,
                    'grade_letter' => $this->gradeLetter,
                    'teacher_feedback' => $this->teacherFeedback,
                    'graded_at' => $this->gradedAt,
                    'has_numerical_grade' => $this->hasNumericalGrade,
                    'action_url' => "/assignments/{$this->assignmentId}/submissions/{$this->submissionId}",
                ],
            ];

            // Send notification
            $notificationService->sendNotification($notificationData);

            Log::info("✅ Assignment graded notification sent successfully", [
                'submission_id' => $this->submissionId,
                'parent_user_id' => $this->parentUserId,
                'parent_name' => $parentUser->name,
                'student_name' => $this->studentName,
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Failed to send assignment graded notification", [
                'submission_id' => $this->submissionId,
                'parent_user_id' => $this->parentUserId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to mark job as failed for retry
        }
    }

    /**
     * Build the notification message based on grading type
     */
    private function buildNotificationMessage(): string
    {
        $feedbackText = $this->teacherFeedback 
            ? " Feedback: \"{$this->teacherFeedback}\"" 
            : '';

        if ($this->hasNumericalGrade && $this->marksObtained !== null && $this->maxMarks !== null) {
            // Numerical grading with marks
            $gradeInfo = "Score: {$this->marksObtained}/{$this->maxMarks}";
            
            if ($this->percentage !== null) {
                $gradeInfo .= " ({$this->percentage}%)";
            }
            
            if ($this->gradeLetter) {
                $gradeInfo .= " - Grade: {$this->gradeLetter}";
            }

            return "✅ {$this->studentName}'s assignment \"{$this->assignmentTitle}\" for {$this->subjectName} has been graded. {$gradeInfo}.{$feedbackText} View details for more information.";
        } else {
            // Feedback-only grading (no numerical marks)
            return "✅ {$this->studentName}'s assignment \"{$this->assignmentTitle}\" for {$this->subjectName} has been reviewed by the teacher.{$feedbackText} View details for full feedback.";
        }
    }
}
