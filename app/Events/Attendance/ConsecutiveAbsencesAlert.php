<?php

namespace App\Events\Attendance;

use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConsecutiveAbsencesAlert
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Student $student;
    public int $consecutiveDays;
    public array $absentDates;

    /**
     * Create a new event instance.
     */
    public function __construct(Student $student, int $consecutiveDays, array $absentDates = [])
    {
        $this->student = $student;
        $this->consecutiveDays = $consecutiveDays;
        $this->absentDates = $absentDates;
    }
}
