<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AssignmentSubmission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SarahJohnsonAssignmentSubmissionsSeeder extends Seeder
{
    /**
     * Simulate student submissions and grading for Sarah Johnson's assignments
     */
    public function run(): void
    {
        // Get all assignments created by Sarah Johnson (Teacher ID: 52)
        $assignments = DB::table('assignments')
            ->where('teacher_id', 52)
            ->where('status', 'published')
            ->get(['id', 'due_date', 'max_marks']);

        $submissionStatuses = ['pending', 'submitted', 'graded'];
        $gradingComments = [
            'Excellent work! Very creative and well done.',
            'Good effort! Keep practicing to improve.',
            'Well done! Shows good understanding.',
            'Great participation and enthusiasm shown.',
            'Nice work! Shows improvement.',
            'Very good! Creative approach to the task.',
            'Good job! Complete and neat work.',
            'Excellent creativity and effort displayed!'
        ];

        foreach ($assignments as $assignment) {
            echo "Processing assignment ID: {$assignment->id}\n";

            // Get all submissions for this assignment
            $submissions = AssignmentSubmission::where('assignment_id', $assignment->id)->get();

            foreach ($submissions as $submission) {
                // Randomly determine if student submitted (70% chance)
                $hasSubmitted = rand(1, 100) <= 70;

                if ($hasSubmitted) {
                    // Random submission time (within due date or slightly late)
                    $dueDate = Carbon::parse($assignment->due_date);
                    $isLate = rand(1, 100) <= 20; // 20% chance of late submission

                    if ($isLate) {
                        $submittedAt = $dueDate->addDays(rand(1, 3))->addHours(rand(1, 12));
                    } else {
                        $submittedAt = $dueDate->subDays(rand(0, 2))->addHours(rand(8, 18));
                    }

                    // Update submission as submitted
                    $submission->update([
                        'status' => 'submitted',
                        'submitted_at' => $submittedAt,
                        'content' => $this->getRandomSubmissionContent(),
                        'is_late_submission' => $isLate
                    ]);

                    // 80% chance of grading if submitted
                    $isGraded = rand(1, 100) <= 80;

                    if ($isGraded) {
                        // Random marks (70-100% of max marks for good performance)
                        $percentage = rand(70, 100);
                        $marksObtained = round(($assignment->max_marks * $percentage) / 100, 1);

                        // Grade the submission
                        $submission->update([
                            'status' => 'graded',
                            'marks_obtained' => $marksObtained,
                            'teacher_feedback' => $gradingComments[array_rand($gradingComments)],
                            'graded_at' => $submittedAt->addDays(rand(1, 3))->addHours(rand(1, 8)),
                            'graded_by' => 52 // Sarah Johnson's teacher ID
                        ]);
                    }
                }
            }
        }

        echo "\n✅ Successfully updated assignment submissions!\n";

        // Show summary statistics
        $totalSubmissions = DB::table('assignment_submissions')
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->count();

        $submittedCount = DB::table('assignment_submissions')
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->where('status', '!=', 'pending')
            ->count();

        $gradedCount = DB::table('assignment_submissions')
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->where('status', 'graded')
            ->count();

        echo "📊 Submission Summary:\n";
        echo "   - Total Expected Submissions: {$totalSubmissions}\n";
        echo "   - Submitted: {$submittedCount}\n";
        echo "   - Graded: {$gradedCount}\n";
        echo "   - Submission Rate: " . round(($submittedCount / $totalSubmissions) * 100, 1) . "%\n";
        echo "   - Grading Rate: " . round(($gradedCount / $submittedCount) * 100, 1) . "%\n";
    }

    /**
     * Get random submission content based on assignment type
     */
    private function getRandomSubmissionContent()
    {
        $contents = [
            "Completed the assignment as instructed. Had fun doing this activity!",
            "Worked on this with my family. We enjoyed the learning experience.",
            "Finished all parts of the assignment. Looking forward to sharing in class.",
            "Practiced a lot to complete this task. Very excited to show my work.",
            "Had some help from parents but did most of it myself. Happy with the result!",
            "This was a fun assignment! Learned many new things while doing it.",
            "Completed on time. Can't wait to present this to the class.",
            "Enjoyed working on this creative project. Hope you like my work!"
        ];

        return $contents[array_rand($contents)];
    }
}
