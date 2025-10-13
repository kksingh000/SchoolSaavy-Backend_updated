<?php

namespace App\Listeners\AssessmentManagement;

use App\Events\AssessmentManagement\AssessmentRescheduled;
use App\Jobs\Notifications\SendAssessmentRescheduledJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendAssessmentRescheduledNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(AssessmentRescheduled $event): void
    {
        Log::info('🎯 Processing assessment rescheduled notification', [
            'assessment_id' => $event->assessmentId,
            'assessment_title' => $event->title,
            'class_id' => $event->classId,
            'old_date' => $event->oldDate,
            'new_date' => $event->newDate,
        ]);

        // Get all students in the class with their parents
        $students = $event->getStudents();

        Log::info('👨‍🎓 Found students in class', [
            'count' => $students->count(),
            'class_id' => $event->classId,
        ]);

        if ($students->isEmpty()) {
            Log::warning('⚠️ No students found in class', [
                'class_id' => $event->classId,
            ]);
            return;
        }

        $notificationsDispatched = 0;

        foreach ($students as $student) {
            $parents = $student->parents;

            Log::info('👨‍👩‍👧 Found parents for student', [
                'student_id' => $student->id,
                'student_name' => $student->name,
                'parent_count' => $parents->count(),
            ]);

            if ($parents->isEmpty()) {
                Log::warning('⚠️ No parents found for student', [
                    'student_id' => $student->id,
                ]);
                continue;
            }

            foreach ($parents as $parent) {
                if (!$parent->user) {
                    Log::warning('⚠️ Parent has no user account', [
                        'parent_id' => $parent->id,
                    ]);
                    continue;
                }

                Log::info('📤 Dispatching assessment rescheduled notification job', [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'parent_id' => $parent->id,
                    'parent_name' => $parent->user->name,
                ]);

                SendAssessmentRescheduledJob::dispatch(
                    assessmentId: $event->assessmentId,
                    studentId: $student->id,
                    parentId: $parent->id,
                    assessmentTitle: $event->title,
                    oldDate: $event->oldDate,
                    oldStartTime: $event->oldStartTime,
                    oldEndTime: $event->oldEndTime,
                    newDate: $event->newDate,
                    newStartTime: $event->newStartTime,
                    newEndTime: $event->newEndTime,
                    subjectName: $event->subjectName,
                    teacherName: $event->teacherName,
                    reason: $event->reason,
                    studentName: $student->name
                );

                $notificationsDispatched++;
            }
        }

        Log::info('✅ Successfully dispatched all assessment rescheduled notification jobs', [
            'assessment_id' => $event->assessmentId,
            'notifications_dispatched' => $notificationsDispatched,
            'students_count' => $students->count(),
        ]);
    }
}
