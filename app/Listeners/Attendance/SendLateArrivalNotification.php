<?php

namespace App\Listeners\Attendance;

use App\Events\Attendance\StudentMarkedLate;
use App\Jobs\Notifications\SendLateNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendLateArrivalNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(StudentMarkedLate $event): void
    {
        try {
            // Load student with relationships from the event
            $student = $event->getStudent();
            $attendance = $event->getAttendance();

            Log::info('🎯 SendLateArrivalNotification listener started', [
                'student_id' => $student->id,
                'date' => $event->date
            ]);

            // Get all parents of the student
            $parents = $student->parents;

            if ($parents->isEmpty()) {
                Log::warning('⚠️ No parents found for late student', [
                    'student_id' => $student->id
                ]);
                return;
            }

            // Dispatch job for each parent
            foreach ($parents as $parent) {
                if ($parent->user) {
                    SendLateNotificationJob::dispatch(
                        $student,
                        $parent->user,
                        $attendance,
                        $event->date,
                        $event->arrivalTime
                    );

                    Log::info('✅ Late notification job dispatched', [
                        'parent_user_id' => $parent->user->id
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('❌ SendLateArrivalNotification listener failed', [
                'error' => $e->getMessage(),
                'student_id' => $event->studentId ?? null
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(StudentMarkedLate $event, \Throwable $exception): void
    {
        Log::error('💥 SendLateArrivalNotification listener permanently failed', [
            'student_id' => $event->studentId ?? null,
            'error' => $exception->getMessage()
        ]);
    }
}
