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
use Carbon\Carbon;

/**
 * Job: SendAssignmentResubmissionRequestedJob
 * 
 * Sends notification to ONE parent about resubmission request for their child's assignment
 * 
 * Notification Details:
 * - Type: assignment
 * - Priority: high (action required)
 * - Recipients: Parent (user account)
 * - Action URL: Link to view assignment and resubmit
 */
class SendAssignmentResubmissionRequestedJob implements ShouldQueue
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
        public string $teacherFeedback,
        public ?string $newDueDate,
        public ?string $newDueTime,
        public string $returnedAt,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            Log::info("🔄 Sending resubmission requested notification to parent", [
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

            // Build the notification message
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
                'title' => '🔄 Assignment Resubmission Required',
                'message' => $message,
                'priority' => 'high', // HIGH priority - action required
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
                    'teacher_feedback' => $this->teacherFeedback,
                    'new_due_date' => $this->newDueDate,
                    'new_due_time' => $this->newDueTime,
                    'returned_at' => $this->returnedAt,
                    'action_url' => "/assignments/{$this->assignmentId}",
                ],
            ];

            // Send notification
            $notificationService->sendNotification($notificationData);

            Log::info("✅ Resubmission notification sent successfully", [
                'submission_id' => $this->submissionId,
                'parent_user_id' => $this->parentUserId,
                'parent_name' => $parentUser->name,
                'student_name' => $this->studentName,
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Failed to send resubmission notification", [
                'submission_id' => $this->submissionId,
                'parent_user_id' => $this->parentUserId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to mark job as failed for retry
        }
    }

    /**
     * Build the notification message with feedback and new deadline
     */
    private function buildNotificationMessage(): string
    {
        $deadlineText = '';
        
        if ($this->newDueDate) {
            try {
                $dueDate = Carbon::parse($this->newDueDate);
                $formattedDate = $dueDate->format('M d, Y');
                
                if ($this->newDueTime) {
                    $dueTime = Carbon::parse($this->newDueTime);
                    $formattedTime = $dueTime->format('g:i A');
                    $deadlineText = " New deadline: {$formattedDate} at {$formattedTime}.";
                } else {
                    $deadlineText = " New deadline: {$formattedDate}.";
                }
            } catch (\Exception $e) {
                // If date parsing fails, use raw values
                $deadlineText = $this->newDueTime 
                    ? " New deadline: {$this->newDueDate} at {$this->newDueTime}."
                    : " New deadline: {$this->newDueDate}.";
            }
        }

        return "🔄 {$this->studentName}'s assignment \"{$this->assignmentTitle}\" for {$this->subjectName} requires resubmission. Teacher's feedback: \"{$this->teacherFeedback}\".{$deadlineText} Please review and resubmit the assignment.";
    }
}
