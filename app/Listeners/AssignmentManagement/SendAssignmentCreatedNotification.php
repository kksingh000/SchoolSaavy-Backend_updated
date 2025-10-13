<?php

namespace App\Listeners\AssignmentManagement;

use App\Events\AssignmentManagement\AssignmentCreated;
use App\Jobs\Notifications\SendAssignmentCreatedJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendAssignmentCreatedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(AssignmentCreated $event): void
    {
        Log::info('🎯 Processing assignment created notification', [
            'assignment_id' => $event->assignmentId,
            'assignment_title' => $event->title,
            'class_id' => $event->classId,
            'subject' => $event->subjectName,
            'due_date' => $event->dueDate,
        ]);

        // Get all students in the class
        $students = $event->getStudents();

        Log::info('👨‍🎓 Found students in class', [
            'count' => $students->count(),
            'class_id' => $event->classId,
        ]);

        if ($students->isEmpty()) {
            Log::warning('⚠️ No students found for assignment notification', [
                'assignment_id' => $event->assignmentId,
                'class_id' => $event->classId,
            ]);
            return;
        }

        $notificationsDispatched = 0;

        foreach ($students as $student) {
            // Get all parents for this student
            $parents = $student->parents;

            if ($parents->isEmpty()) {
                Log::debug('⏭️ Student has no parents', [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                ]);
                continue;
            }

            Log::info('👨‍👩‍👧 Found parents for student', [
                'student_id' => $student->id,
                'student_name' => $student->name,
                'parent_count' => $parents->count(),
            ]);

            // Send notification to each parent
            foreach ($parents as $parent) {
                if (!$parent->user) {
                    Log::warning('⚠️ Parent has no user account', [
                        'parent_id' => $parent->id,
                    ]);
                    continue;
                }

                Log::info('📤 Dispatching assignment created notification job', [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'parent_id' => $parent->id,
                    'parent_name' => $parent->user->name,
                    'assignment_title' => $event->title,
                ]);

                SendAssignmentCreatedJob::dispatch(
                    assignmentId: $event->assignmentId,
                    studentId: $student->id,
                    parentId: $parent->id,
                    title: $event->title,
                    subjectName: $event->subjectName,
                    teacherName: $event->teacherName,
                    dueDate: $event->dueDate,
                    dueTime: $event->dueTime,
                    type: $event->type,
                    maxMarks: $event->maxMarks
                );

                $notificationsDispatched++;
            }
        }

        Log::info('✅ Successfully dispatched all assignment created notification jobs', [
            'assignment_id' => $event->assignmentId,
            'notifications_dispatched' => $notificationsDispatched,
            'students_count' => $students->count(),
        ]);
    }
}
