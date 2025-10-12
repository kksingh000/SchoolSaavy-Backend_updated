<?php

namespace App\Listeners\Attendance;

use App\Events\Attendance\ConsecutiveAbsencesAlert;
use App\Jobs\Notifications\SendConsecutiveAbsenceJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendConsecutiveAbsenceNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(ConsecutiveAbsencesAlert $event): void
    {
        // Get all parents of the student
        $parents = $event->student->parents;

        if ($parents->isEmpty()) {
            return;
        }

        // Dispatch job for each parent
        foreach ($parents as $parent) {
            if ($parent->user) {
                SendConsecutiveAbsenceJob::dispatch(
                    $event->student,
                    $parent->user,
                    $event->consecutiveDays,
                    $event->absentDates
                );
            }
        }
    }
}
