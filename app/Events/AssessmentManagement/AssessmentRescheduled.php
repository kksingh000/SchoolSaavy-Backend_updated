<?php

namespace App\Events\AssessmentManagement;

use App\Models\Assessment;
use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;

class AssessmentRescheduled implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets;

    public int $assessmentId;
    public int $classId;
    public string $title;
    public string $oldDate;
    public ?string $oldStartTime;
    public ?string $oldEndTime;
    public string $newDate;
    public ?string $newStartTime;
    public ?string $newEndTime;
    public string $subjectName;
    public string $teacherName;
    public ?string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $assessmentId,
        int $classId,
        string $title,
        string $oldDate,
        ?string $oldStartTime,
        ?string $oldEndTime,
        string $newDate,
        ?string $newStartTime,
        ?string $newEndTime,
        string $subjectName,
        string $teacherName,
        ?string $reason = null
    ) {
        $this->assessmentId = $assessmentId;
        $this->classId = $classId;
        $this->title = $title;
        $this->oldDate = $oldDate;
        $this->oldStartTime = $oldStartTime;
        $this->oldEndTime = $oldEndTime;
        $this->newDate = $newDate;
        $this->newStartTime = $newStartTime;
        $this->newEndTime = $newEndTime;
        $this->subjectName = $subjectName;
        $this->teacherName = $teacherName;
        $this->reason = $reason;
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
