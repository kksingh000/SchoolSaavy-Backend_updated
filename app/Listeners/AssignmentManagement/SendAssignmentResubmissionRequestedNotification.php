<?php

namespace App\Listeners\AssignmentManagement;

use App\Events\AssignmentManagement\AssignmentResubmissionRequested;
use App\Jobs\Notifications\SendAssignmentResubmissionRequestedJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener: SendAssignmentResubmissionRequestedNotification
 * 
 * Processes AssignmentResubmissionRequested event and dispatches notification jobs
 * to inform the student and all parents about the resubmission request
 * 
 * Recipients: Student + All Parents
 * Priority: high (action required)
 */
class SendAssignmentResubmissionRequestedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(AssignmentResubmissionRequested $event): void
    {
        Log::info("🎯 Processing assignment resubmission requested notification", [
            'submission_id' => $event->submissionId,
            'assignment_id' => $event->assignmentId,
            'student_id' => $event->studentId,
            'new_due_date' => $event->newDueDate,
        ]);

        try {
            // Get student with parents
            $student = $event->getStudent();
            
            Log::info("👨‍🎓 Resubmission requested for student: {$student->name}", [
                'student_id' => $student->id,
            ]);

            // Get all parents for this student
            $parents = $event->getParents();
            
            if ($parents->isEmpty()) {
                Log::warning("⚠️ No parents found for student: {$student->name}", [
                    'student_id' => $student->id,
                ]);
                return;
            }

            Log::info("👨‍👩‍👧 Found {$parents->count()} parent(s) for student: {$student->name}");

            // Dispatch notification job for each parent
            $jobsDispatched = 0;
            
            foreach ($parents as $parent) {
                if ($parent->user) {
                    SendAssignmentResubmissionRequestedJob::dispatch(
                        submissionId: $event->submissionId,
                        assignmentId: $event->assignmentId,
                        studentId: $event->studentId,
                        parentUserId: $parent->user->id,
                        assignmentTitle: $event->assignmentTitle,
                        subjectName: $event->subjectName,
                        studentName: $event->studentName,
                        teacherFeedback: $event->teacherFeedback,
                        newDueDate: $event->newDueDate,
                        newDueTime: $event->newDueTime,
                        returnedAt: $event->returnedAt,
                    );
                    
                    $jobsDispatched++;
                    
                    Log::info("📤 Dispatched resubmission notification for parent: {$parent->user->name}");
                } else {
                    Log::warning("⚠️ Parent has no user account", [
                        'parent_id' => $parent->id,
                        'student_id' => $student->id,
                    ]);
                }
            }

            Log::info("✅ Successfully dispatched {$jobsDispatched} resubmission notification job(s)", [
                'submission_id' => $event->submissionId,
                'assignment_title' => $event->assignmentTitle,
                'student_name' => $event->studentName,
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Failed to process resubmission requested notification", [
                'submission_id' => $event->submissionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e; // Re-throw to mark job as failed for retry
        }
    }
}
