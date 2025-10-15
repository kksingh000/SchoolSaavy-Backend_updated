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
        public int $schoolId,
        public int $parentId,
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
                'job' => class_basename($this),
                'submission_id' => $this->submissionId,
                'assignment_id' => $this->assignmentId,
                'parent_id' => $this->parentId,
                'student_name' => $this->studentName,
            ]);

            // Build the notification message
            $message = $this->buildNotificationMessage();

            // Prepare notification data
            $notificationData = [
                'school_id' => $this->schoolId,
                'type' => 'assignment',
                'title' => '🔄 Assignment Resubmission Required',
                'message' => $message,
                'priority' => 'high', // HIGH priority - action required
                'target_type' => 'parent',
                'target_ids' => [$this->parentId],
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
                    'action_url' => $this->getActionUrl(),
                ],
            ];

            // Send notification
            $result = $notificationService->sendNotification($notificationData);

            Log::info("✅ Resubmission notification sent successfully", [
                'notification_id' => $result['notification_id'] ?? null,
                'submission_id' => $this->submissionId,
                'parent_id' => $this->parentId,
                'student_name' => $this->studentName,
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Failed to send resubmission notification", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'submission_id' => $this->submissionId,
                'parent_id' => $this->parentId,
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

    /**
     * Get action URL for deep linking
     */
    private function getActionUrl(): string
    {
        return "/assignments/{$this->assignmentId}";
    }
}