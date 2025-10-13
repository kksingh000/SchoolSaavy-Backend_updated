<?php

namespace App\Listeners\AssignmentManagement;

use App\Events\AssignmentManagement\AssignmentSubmitted;
use App\Jobs\Notifications\SendAssignmentSubmittedToParentJob;
use App\Jobs\Notifications\SendAssignmentSubmittedToTeacherJob;
use App\Models\Teacher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendAssignmentSubmittedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(AssignmentSubmitted $event): void
    {
        Log::info('🎯 Processing assignment submitted notification', [
            'submission_id' => $event->submissionId,
            'assignment_id' => $event->assignmentId,
            'assignment_title' => $event->assignmentTitle,
            'student_id' => $event->studentId,
            'student_name' => $event->studentName,
            'teacher_id' => $event->teacherId,
            'is_late' => $event->isLateSubmission,
        ]);

        // 1. Notify the teacher
        $teacher = Teacher::with('user')->find($event->teacherId);
        
        if ($teacher && $teacher->user) {
            Log::info('👨‍🏫 Notifying teacher', [
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->user->name,
            ]);

            SendAssignmentSubmittedToTeacherJob::dispatch(
                submissionId: $event->submissionId,
                assignmentId: $event->assignmentId,
                studentId: $event->studentId,
                teacherId: $event->teacherId,
                assignmentTitle: $event->assignmentTitle,
                subjectName: $event->subjectName,
                studentName: $event->studentName,
                submittedAt: $event->submittedAt,
                isLateSubmission: $event->isLateSubmission
            );
        } else {
            Log::warning('⚠️ Teacher not found or has no user account', [
                'teacher_id' => $event->teacherId,
            ]);
        }

        // 2. Notify the parents
        $parents = $event->getParents();

        Log::info('👨‍👩‍👧 Found parents for student', [
            'student_id' => $event->studentId,
            'student_name' => $event->studentName,
            'parent_count' => $parents->count(),
        ]);

        if ($parents->isEmpty()) {
            Log::warning('⚠️ No parents found for student', [
                'student_id' => $event->studentId,
            ]);
            return;
        }

        $notificationsDispatched = 0;

        foreach ($parents as $parent) {
            if (!$parent->user) {
                Log::warning('⚠️ Parent has no user account', [
                    'parent_id' => $parent->id,
                ]);
                continue;
            }

            Log::info('📤 Dispatching assignment submitted notification to parent', [
                'parent_id' => $parent->id,
                'parent_name' => $parent->user->name,
                'student_name' => $event->studentName,
            ]);

            SendAssignmentSubmittedToParentJob::dispatch(
                submissionId: $event->submissionId,
                assignmentId: $event->assignmentId,
                studentId: $event->studentId,
                parentId: $parent->id,
                assignmentTitle: $event->assignmentTitle,
                subjectName: $event->subjectName,
                studentName: $event->studentName,
                submittedAt: $event->submittedAt,
                isLateSubmission: $event->isLateSubmission
            );

            $notificationsDispatched++;
        }

        Log::info('✅ Successfully dispatched all assignment submitted notifications', [
            'submission_id' => $event->submissionId,
            'teacher_notified' => $teacher && $teacher->user ? 1 : 0,
            'parents_notified' => $notificationsDispatched,
        ]);
    }
}
