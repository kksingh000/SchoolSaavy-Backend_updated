<?php

namespace App\Listeners\Attendance;

use App\Events\Attendance\StudentLowAttendance;
use App\Jobs\Notifications\SendLowAttendanceNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendLowAttendanceNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(StudentLowAttendance $event): void
    {
        try {
            // Reload student with relationships to avoid serialization issues
            $student = $event->getStudent();
            
            Log::info('🎯 Processing low attendance notification', [
                'student_id' => $student->id,
                'student_name' => $student->first_name . ' ' . $student->last_name,
                'attendance_percentage' => $event->attendancePercentage,
                'period' => $event->periodStart . ' to ' . $event->periodEnd,
                'total_days' => $event->totalDays,
                'present_days' => $event->presentDays,
                'absent_days' => $event->absentDays,
            ]);

            // Send notification to all parents
            if ($student->parents && $student->parents->isNotEmpty()) {
                Log::info('👨‍👩‍👧 Found parents', [
                    'count' => $student->parents->count(),
                    'parent_ids' => $student->parents->pluck('id')->toArray()
                ]);

                foreach ($student->parents as $parent) {
                    if ($parent->user) {
                        Log::info('📤 Dispatching low attendance notification job', [
                            'parent_id' => $parent->id,
                            'parent_name' => $parent->first_name . ' ' . $parent->last_name,
                            'user_id' => $parent->user->id
                        ]);

                        SendLowAttendanceNotificationJob::dispatch(
                            $student->id,
                            $parent->id,
                            $event->attendancePercentage,
                            $event->totalDays,
                            $event->presentDays,
                            $event->absentDays,
                            $event->periodStart,
                            $event->periodEnd
                        );
                    } else {
                        Log::warning('⚠️ Parent has no associated user account', [
                            'parent_id' => $parent->id
                        ]);
                    }
                }

                Log::info('✅ Successfully dispatched all low attendance notification jobs', [
                    'student_id' => $student->id,
                    'parents_notified' => $student->parents->count()
                ]);
            } else {
                Log::warning('⚠️ No parents found for student', [
                    'student_id' => $student->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Error processing low attendance notification', [
                'student_id' => $event->studentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
