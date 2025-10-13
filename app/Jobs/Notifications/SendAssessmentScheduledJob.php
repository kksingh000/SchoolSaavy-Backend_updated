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

class SendAssessmentScheduledJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $assessmentId;
    public int $studentId;
    public int $parentId;
    public string $assessmentTitle;
    public string $assessmentDate;
    public ?string $startTime;
    public ?string $endTime;
    public string $subjectName;
    public string $teacherName;
    public ?int $maxMarks;
    public ?string $syllabus;
    public string $studentName;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $assessmentId,
        int $studentId,
        int $parentId,
        string $assessmentTitle,
        string $assessmentDate,
        ?string $startTime,
        ?string $endTime,
        string $subjectName,
        string $teacherName,
        ?int $maxMarks,
        ?string $syllabus,
        string $studentName
    ) {
        $this->assessmentId = $assessmentId;
        $this->studentId = $studentId;
        $this->parentId = $parentId;
        $this->assessmentTitle = $assessmentTitle;
        $this->assessmentDate = $assessmentDate;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->subjectName = $subjectName;
        $this->teacherName = $teacherName;
        $this->maxMarks = $maxMarks;
        $this->syllabus = $syllabus;
        $this->studentName = $studentName;
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        Log::info('📱 Sending assessment scheduled notification', [
            'assessment_id' => $this->assessmentId,
            'student_id' => $this->studentId,
            'parent_id' => $this->parentId,
            'title' => $this->assessmentTitle,
        ]);

        // Load models
        $student = Student::findOrFail($this->studentId);
        $parent = Parents::with('user')->findOrFail($this->parentId);
        $assessment = Assessment::findOrFail($this->assessmentId);

        // Format assessment date and time
        $assessmentDateTime = date('d M Y', strtotime($this->assessmentDate));
        $timeInfo = '';
        
        if ($this->startTime && $this->endTime) {
            $timeInfo = ' at ' . date('h:i A', strtotime($this->startTime)) . ' - ' . date('h:i A', strtotime($this->endTime));
        } elseif ($this->startTime) {
            $timeInfo = ' at ' . date('h:i A', strtotime($this->startTime));
        }

        // Build message
        $marksInfo = $this->maxMarks ? " (Max Marks: {$this->maxMarks})" : '';
        $syllabusInfo = $this->syllabus ? " Topics: {$this->syllabus}." : '';
        
        $message = "📅 New assessment scheduled for {$this->studentName} in {$this->subjectName}. \"{$this->assessmentTitle}\" on {$assessmentDateTime}{$timeInfo}{$marksInfo}.{$syllabusInfo} Please ensure your child is well-prepared.";

        $notificationData = [
            'school_id' => $student->school_id,
            'type' => 'assessment',
            'title' => '📅 Assessment Scheduled',
            'message' => $message,
            'priority' => 'normal',
            'target_type' => 'parent',
            'target_ids' => [$parent->user->id],
            'data' => [
                'assessment_id' => $this->assessmentId,
                'student_id' => $this->studentId,
                'student_name' => $this->studentName,
                'assessment_title' => $this->assessmentTitle,
                'subject' => $this->subjectName,
                'assessment_date' => $this->assessmentDate,
                'start_time' => $this->startTime,
                'end_time' => $this->endTime,
                'max_marks' => $this->maxMarks,
                'syllabus' => $this->syllabus,
                'teacher_name' => $this->teacherName,
                'action_url' => '/assessments/' . $this->assessmentId,
            ],
        ];

        $notification = $notificationService->sendNotification($notificationData);

        $notificationId = is_array($notification) ? ($notification['id'] ?? 'unknown') : $notification->id;

        Log::info('✅ Assessment scheduled notification sent successfully', [
            'notification_id' => $notificationId,
            'assessment_id' => $this->assessmentId,
            'student_id' => $this->studentId,
            'parent_id' => $this->parentId,
            'parent_user_id' => $parent->user->id,
        ]);
    }
}
