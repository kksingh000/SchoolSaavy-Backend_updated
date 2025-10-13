<?php

namespace App\Events\Attendance;

use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class StudentLowAttendance implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $studentId;
    public float $attendancePercentage;
    public int $totalDays;
    public int $presentDays;
    public int $absentDays;
    public string $periodStart;
    public string $periodEnd;

    /**
     * Create a new event instance.
     * Store only IDs and primitive data to avoid serialization issues.
     */
    public function __construct(
        Student $student,
        float $attendancePercentage,
        int $totalDays,
        int $presentDays,
        int $absentDays,
        string $periodStart,
        string $periodEnd
    ) {
        $this->studentId = $student->id;
        $this->attendancePercentage = $attendancePercentage;
        $this->totalDays = $totalDays;
        $this->presentDays = $presentDays;
        $this->absentDays = $absentDays;
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
    }

    /**
     * Get the student model with relationships loaded.
     */
    public function getStudent(): Student
    {
        return Student::with('parents.user')->findOrFail($this->studentId);
    }
}
