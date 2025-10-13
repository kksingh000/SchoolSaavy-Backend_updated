<?php

namespace App\Events\AssignmentManagement;

use App\Models\Assignment;
use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;

class AssignmentCreated implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets;

    public int $assignmentId;
    public int $classId;
    public string $title;
    public string $dueDate;
    public ?string $dueTime;
    public string $subjectName;
    public string $teacherName;
    public string $type;
    public ?int $maxMarks;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $assignmentId,
        int $classId,
        string $title,
        string $dueDate,
        ?string $dueTime,
        string $subjectName,
        string $teacherName,
        string $type,
        ?int $maxMarks = null
    ) {
        $this->assignmentId = $assignmentId;
        $this->classId = $classId;
        $this->title = $title;
        $this->dueDate = $dueDate;
        $this->dueTime = $dueTime;
        $this->subjectName = $subjectName;
        $this->teacherName = $teacherName;
        $this->type = $type;
        $this->maxMarks = $maxMarks;
    }

    /**
     * Get the assignment instance with relationships
     */
    public function getAssignment(): Assignment
    {
        return Assignment::with(['teacher.user', 'class', 'subject'])
            ->findOrFail($this->assignmentId);
    }

    /**
     * Get all students in the class with their parents
     */
    public function getStudents(): Collection
    {
        return Student::with(['parents.user'])
            ->whereHas('classes', function ($query) {
                $query->where('class_id', $this->classId)
                    ->where('class_student.is_active', true);
            })
            ->where('students.is_active', true)
            ->get();
    }
}
