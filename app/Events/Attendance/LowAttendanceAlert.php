<?php

namespace App\Events\Attendance;

use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowAttendanceAlert
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Student $student;
    public float $attendancePercentage;
    public float $threshold;
    public int $totalDays;
    public int $presentDays;
    public int $absentDays;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Student $student,
        float $attendancePercentage,
        float $threshold = 75.0,
        int $totalDays = 0,
        int $presentDays = 0,
        int $absentDays = 0
    ) {
        $this->student = $student;
        $this->attendancePercentage = $attendancePercentage;
        $this->threshold = $threshold;
        $this->totalDays = $totalDays;
        $this->presentDays = $presentDays;
        $this->absentDays = $absentDays;
    }
}
