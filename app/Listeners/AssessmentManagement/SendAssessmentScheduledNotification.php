<?php

namespace App\Listeners\AssessmentManagement;

use App\Events\AssessmentManagement\AssessmentScheduled;
use App\Jobs\Notifications\SendAssessmentScheduledJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendAssessmentScheduledNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(AssessmentScheduled $event): void
    {
        Log::info('🎯 Processing assessment scheduled notification', [
            'assessment_id' => $event->assessmentId,
            'assessment_title' => $event->title,
            'class_id' => $event->classId,
            'subject' => $event->subjectName,
            'assessment_date' => $event->assessmentDate,
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
                    'student_name' => $student->name,
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

                Log::info('📤 Dispatching assessment scheduled notification job', [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'parent_id' => $parent->id,
                    'parent_name' => $parent->user->name,
                    'assessment_title' => $event->title,
                ]);

                SendAssessmentScheduledJob::dispatch(
                    assessmentId: $event->assessmentId,
                    studentId: $student->id,
                    parentId: $parent->id,
                    assessmentTitle: $event->title,
                    assessmentDate: $event->assessmentDate,
                    startTime: $event->startTime,
                    endTime: $event->endTime,
                    subjectName: $event->subjectName,
                    teacherName: $event->teacherName,
                    maxMarks: $event->maxMarks,
                    syllabus: $event->syllabus,
                    studentName: $student->name
                );

                $notificationsDispatched++;
            }
        }

        Log::info('✅ Successfully dispatched all assessment scheduled notification jobs', [
            'assessment_id' => $event->assessmentId,
            'notifications_dispatched' => $notificationsDispatched,
            'students_count' => $students->count(),
        ]);
    }
}
