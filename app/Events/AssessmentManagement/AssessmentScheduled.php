<?php

namespace App\Events\AssessmentManagement;

use App\Models\Assessment;
use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;

class AssessmentScheduled implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets;

    public int $assessmentId;
    public int $classId;
    public string $title;
    public string $assessmentDate;
    public ?string $startTime;
    public ?string $endTime;
    public string $subjectName;
    public string $teacherName;
    public ?int $maxMarks;
    public ?string $syllabus;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $assessmentId,
        int $classId,
        string $title,
        string $assessmentDate,
        ?string $startTime,
        ?string $endTime,
        string $subjectName,
        string $teacherName,
        ?int $maxMarks = null,
        ?string $syllabus = null
    ) {
        $this->assessmentId = $assessmentId;
        $this->classId = $classId;
        $this->title = $title;
        $this->assessmentDate = $assessmentDate;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->subjectName = $subjectName;
        $this->teacherName = $teacherName;
        $this->maxMarks = $maxMarks;
        $this->syllabus = $syllabus;
    }

    /**
     * Get the assessment instance with relationships
     */
    public function getAssessment(): Assessment
    {
        return Assessment::with(['teacher.user', 'class', 'subject'])
            ->findOrFail($this->assessmentId);
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
