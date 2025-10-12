<?php

namespace App\Events\Attendance;

use App\Models\Student;
use App\Models\Attendance;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class StudentMarkedLate implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $studentId;
    public int $attendanceId;
    public string $date;
    public ?string $arrivalTime;

    /**
     * Create a new event instance.
     * Store only IDs to avoid serialization issues with relationships.
     */
    public function __construct(Student $student, Attendance $attendance, string $date, ?string $arrivalTime = null)
    {
        $this->studentId = $student->id;
        $this->attendanceId = $attendance->id;
        $this->date = $date;
        $this->arrivalTime = $arrivalTime;
    }

    /**
     * Get the student model with relationships loaded.
     */
    public function getStudent(): Student
    {
        return Student::with('parents.user')->findOrFail($this->studentId);
    }

    /**
     * Get the attendance model.
     */
    public function getAttendance(): Attendance
    {
        return Attendance::findOrFail($this->attendanceId);
    }
}
