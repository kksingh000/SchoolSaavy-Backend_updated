<?php

namespace App\Events\AssignmentManagement;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;

/**
 * Event: AssignmentResubmissionRequested
 * 
 * Triggered when: Teacher returns assignment for revision/resubmission
 * Recipients: Student + Parents
 * Priority: high (requires action)
 * 
 * Pattern: ID-based serialization (no model serialization)
 * Relationships are loaded via getter methods when needed
 */
class AssignmentResubmissionRequested implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $submissionId,
        public int $assignmentId,
        public int $studentId,
        public string $assignmentTitle,
        public string $subjectName,
        public string $studentName,
        public string $teacherFeedback,
        public ?string $newDueDate,
        public ?string $newDueTime,
        public string $returnedAt,
    ) {}

    /**
     * Get the submission with relationships
     */
    public function getSubmission(): AssignmentSubmission
    {
        return AssignmentSubmission::with([
            'assignment',
            'student',
            'assignment.subject',
            'assignment.teacher.user'
        ])->findOrFail($this->submissionId);
    }

    /**
     * Get the assignment with relationships
     */
    public function getAssignment(): Assignment
    {
        return Assignment::with(['subject', 'teacher.user', 'class'])->findOrFail($this->assignmentId);
    }

    /**
     * Get the student with parents
     */
    public function getStudent(): Student
    {
        return Student::with('parents.user')->findOrFail($this->studentId);
    }

    /**
     * Get all parents for the student
     */
    public function getParents(): Collection
    {
        $student = $this->getStudent();
        return $student->parents()->with('user')->get();
    }
}
