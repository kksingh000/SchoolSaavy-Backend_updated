<?php

namespace App\Events\AssignmentManagement;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Student;
use App\Models\Parents;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class AssignmentSubmitted implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets;

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
     * Create a new event instance.
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
     * Get the submission instance with relationships
     */
    public function getSubmission(): AssignmentSubmission
    {
        return AssignmentSubmission::with(['assignment.subject', 'assignment.teacher.user', 'student'])
            ->findOrFail($this->submissionId);
    }

    /**
     * Get the student's parents
     */
    public function getParents()
    {
        return Parents::with('user')
            ->whereHas('students', function ($query) {
                $query->where('student_id', $this->studentId);
            })
            ->get();
    }
}
