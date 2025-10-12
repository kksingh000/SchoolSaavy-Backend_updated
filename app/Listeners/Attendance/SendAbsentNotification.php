<?php

namespace App\Listeners\Attendance;

use App\Events\Attendance\StudentMarkedAbsent;
use App\Jobs\Notifications\SendAbsentNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendAbsentNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(StudentMarkedAbsent $event): void
    {
        try {
            // Load student with relationships from the event
            $student = $event->getStudent();

            Log::info('🎯 SendAbsentNotification listener started', [
                'student_id' => $student->id,
                'student_name' => $student->first_name . ' ' . $student->last_name,
                'date' => $event->date,
                'attendance_id' => $event->attendanceId
            ]);

            // Get all parents of the student
            $parents = $student->parents;

            Log::info('👨‍👩‍👧 Found parents for student', [
                'student_id' => $student->id,
                'parents_count' => $parents->count()
            ]);

            if ($parents->isEmpty()) {
                Log::warning('⚠️ No parents found for student', [
                    'student_id' => $student->id
                ]);
                return;
            }

            // Dispatch job for each parent
            foreach ($parents as $parent) {
                Log::info('👤 Processing parent', [
                    'parent_id' => $parent->id,
                    'parent_user_id' => $parent->user_id ?? null,
                    'has_user' => isset($parent->user)
                ]);

                if ($parent->user) {
                    // Pass attendance ID instead of model to avoid loading issues
                    SendAbsentNotificationJob::dispatch(
                        $student,
                        $parent->user,
                        $event->attendanceId,  // Pass ID, not model
                        $event->date
                    );

                    Log::info('✅ Job dispatched for parent', [
                        'parent_user_id' => $parent->user->id,
                        'parent_name' => $parent->user->name
                    ]);
                } else {
                    Log::warning('⚠️ Parent has no user account', [
                        'parent_id' => $parent->id
                    ]);
                }
            }

            Log::info('✅ SendAbsentNotification listener completed successfully');

        } catch (\Exception $e) {
            Log::error('❌ SendAbsentNotification listener failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'student_id' => $event->studentId ?? null
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(StudentMarkedAbsent $event, \Throwable $exception): void
    {
        Log::error('💥 SendAbsentNotification listener permanently failed', [
            'student_id' => $event->student->id ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
