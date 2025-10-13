<?php

namespace App\Listeners\AssignmentManagement;

use App\Events\AssignmentManagement\AssignmentGraded;
use App\Jobs\Notifications\SendAssignmentGradedJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener: SendAssignmentGradedNotification
 * 
 * Processes AssignmentGraded event and dispatches notification jobs
 * to inform the student and all parents about the grading
 * 
 * Recipients: Student + All Parents
 * Priority: normal
 */
class SendAssignmentGradedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(AssignmentGraded $event): void
    {
        Log::info("🎯 Processing assignment graded notification", [
            'submission_id' => $event->submissionId,
            'assignment_id' => $event->assignmentId,
            'student_id' => $event->studentId,
            'marks_obtained' => $event->marksObtained,
            'max_marks' => $event->maxMarks,
            'has_numerical_grade' => $event->hasNumericalGrade,
        ]);

        try {
            // Get student with parents
            $student = $event->getStudent();
            
            Log::info("👨‍🎓 Graded assignment for student: {$student->name}", [
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
                    SendAssignmentGradedJob::dispatch(
                        submissionId: $event->submissionId,
                        assignmentId: $event->assignmentId,
                        studentId: $event->studentId,
                        parentUserId: $parent->user->id,
                        assignmentTitle: $event->assignmentTitle,
                        subjectName: $event->subjectName,
                        studentName: $event->studentName,
                        marksObtained: $event->marksObtained,
                        maxMarks: $event->maxMarks,
                        percentage: $event->percentage,
                        gradeLetter: $event->gradeLetter,
                        teacherFeedback: $event->teacherFeedback,
                        gradedAt: $event->gradedAt,
                        hasNumericalGrade: $event->hasNumericalGrade,
                    );
                    
                    $jobsDispatched++;
                    
                    Log::info("📤 Dispatched graded notification for parent: {$parent->user->name}");
                } else {
                    Log::warning("⚠️ Parent has no user account", [
                        'parent_id' => $parent->id,
                        'student_id' => $student->id,
                    ]);
                }
            }

            Log::info("✅ Successfully dispatched {$jobsDispatched} assignment graded notification job(s)", [
                'submission_id' => $event->submissionId,
                'assignment_title' => $event->assignmentTitle,
                'student_name' => $event->studentName,
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Failed to process assignment graded notification", [
                'submission_id' => $event->submissionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e; // Re-throw to mark job as failed for retry
        }
    }
}
