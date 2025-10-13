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

class SendAssignmentCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $assignmentId;
    public int $studentId;
    public int $parentId;
    public string $title;
    public string $subjectName;
    public string $teacherName;
    public string $dueDate;
    public ?string $dueTime;
    public string $type;
    public ?int $maxMarks;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $assignmentId,
        int $studentId,
        int $parentId,
        string $title,
        string $subjectName,
        string $teacherName,
        string $dueDate,
        ?string $dueTime,
        string $type,
        ?int $maxMarks = null
    ) {
        $this->assignmentId = $assignmentId;
        $this->studentId = $studentId;
        $this->parentId = $parentId;
        $this->title = $title;
        $this->subjectName = $subjectName;
        $this->teacherName = $teacherName;
        $this->dueDate = $dueDate;
        $this->dueTime = $dueTime;
        $this->type = $type;
        $this->maxMarks = $maxMarks;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        Log::info('📱 Sending assignment created notification', [
            'assignment_id' => $this->assignmentId,
            'student_id' => $this->studentId,
            'parent_id' => $this->parentId,
            'title' => $this->title,
        ]);

        // Load models with relationships
        $student = Student::findOrFail($this->studentId);
        $parent = Parents::with('user')->findOrFail($this->parentId);
        $assignment = Assignment::findOrFail($this->assignmentId);

        $studentName = $student->name; // Uses the accessor from Student model
        $parentUserName = $parent->user->name;

        // Format due date and time
        $dueDateTime = date('d M Y', strtotime($this->dueDate));
        if ($this->dueTime) {
            $dueDateTime .= ' at ' . date('h:i A', strtotime($this->dueTime));
        }

        // Build assignment type label
        $typeLabel = ucfirst($this->type);
        
        // Build marks info
        $marksInfo = $this->maxMarks ? " (Max Marks: {$this->maxMarks})" : '';

        // Create notification message
        $message = "📚 New {$typeLabel} assigned in {$this->subjectName} for {$studentName}. \"{$this->title}\" - Due: {$dueDateTime}{$marksInfo}. Teacher: {$this->teacherName}.";

        $notificationData = [
            'school_id' => $student->school_id,
            'type' => 'assignment',
            'title' => '📚 New Assignment',
            'message' => $message,
            'priority' => 'normal',
            'target_type' => 'parent',
            'target_ids' => [$parent->user->id],
            'data' => [
                'assignment_id' => $this->assignmentId,
                'student_id' => $this->studentId,
                'student_name' => $studentName,
                'assignment_title' => $this->title,
                'subject' => $this->subjectName,
                'teacher' => $this->teacherName,
                'due_date' => $this->dueDate,
                'due_time' => $this->dueTime,
                'type' => $this->type,
                'max_marks' => $this->maxMarks,
                'action_url' => '/assignments/' . $this->assignmentId,
            ],
        ];

        $notification = $notificationService->sendNotification($notificationData);

        $notificationId = is_array($notification) ? ($notification['id'] ?? 'unknown') : $notification->id;

        Log::info('✅ Assignment created notification sent successfully', [
            'notification_id' => $notificationId,
            'assignment_id' => $this->assignmentId,
            'student_id' => $this->studentId,
            'parent_id' => $this->parentId,
            'parent_user_id' => $parent->user->id,
        ]);
    }
}
