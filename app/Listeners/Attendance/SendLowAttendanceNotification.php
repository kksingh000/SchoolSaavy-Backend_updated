<?php

namespace App\Listeners\Attendance;

use App\Events\Attendance\LowAttendanceAlert;
use App\Jobs\Notifications\SendAttendanceAlertJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendLowAttendanceNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(LowAttendanceAlert $event): void
    {
        // Get all parents of the student
        $parents = $event->student->parents;

        if ($parents->isEmpty()) {
            return;
        }

        // Dispatch job for each parent
        foreach ($parents as $parent) {
            if ($parent->user) {
                SendAttendanceAlertJob::dispatch(
                    $event->student,
                    $parent->user,
                    $event->attendancePercentage,
                    $event->threshold,
                    $event->totalDays,
                    $event->presentDays,
                    $event->absentDays
                );
            }
        }
    }
}
