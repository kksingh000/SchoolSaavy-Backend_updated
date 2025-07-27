<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\School;
use App\Models\Teacher;
use App\Models\ClassRoom;
use App\Models\Subject;
use App\Models\Student;
use Carbon\Carbon;

class AssignmentSeeder extends Seeder
{
    public function run()
    {
        $school = School::first();
        $teacher = Teacher::first();

        if (!$school || !$teacher) {
            $this->command->info('Please ensure you have at least one school and teacher before running this seeder.');
            return;
        }

        // Get classes and subjects
        $classes = ClassRoom::where('school_id', $school->id)->get();
        $subjects = Subject::where('school_id', $school->id)->get();

        if ($classes->isEmpty() || $subjects->isEmpty()) {
            $this->command->info('Please ensure you have classes and subjects before running this seeder.');
            return;
        }

        $assignmentTypes = ['homework', 'project', 'quiz', 'classwork', 'assessment'];
        $assignmentData = [
            [
                'title' => 'Color Recognition Worksheet',
                'description' => 'Identify and color different shapes with their respective colors.',
                'instructions' => 'Complete the worksheet by coloring each shape with the correct color as mentioned.',
                'type' => 'homework',
                'assigned_date' => Carbon::today()->subDays(2),
                'due_date' => Carbon::today()->addDays(1),
                'max_marks' => 20,
            ],
            [
                'title' => 'Number Counting Exercise',
                'description' => 'Practice counting numbers from 1 to 20 using pictures.',
                'instructions' => 'Count the objects in each picture and write the number.',
                'type' => 'classwork',
                'assigned_date' => Carbon::today()->subDays(1),
                'due_date' => Carbon::today()->addDays(2),
                'max_marks' => 25,
            ],
            [
                'title' => 'Art Project - Family Drawing',
                'description' => 'Draw and color your family members.',
                'instructions' => 'Use crayons to draw your family. Include all family members living with you.',
                'type' => 'project',
                'assigned_date' => Carbon::today(),
                'due_date' => Carbon::today()->addDays(5),
                'max_marks' => 30,
                'allow_late_submission' => true,
            ],
            [
                'title' => 'Story Time - Three Little Pigs',
                'description' => 'Listen to the story and answer simple questions.',
                'instructions' => 'After listening to the story, circle the correct pictures.',
                'type' => 'assessment',
                'assigned_date' => Carbon::today()->addDays(1),
                'due_date' => Carbon::today()->addDays(3),
                'max_marks' => 15,
            ],
            [
                'title' => 'Phonics Practice - Letter A',
                'description' => 'Practice writing letter A and identify words starting with A.',
                'instructions' => 'Trace the letter A and circle pictures that start with A.',
                'type' => 'homework',
                'assigned_date' => Carbon::today()->addDays(2),
                'due_date' => Carbon::today()->addDays(4),
                'max_marks' => 20,
            ],
        ];

        foreach ($assignmentData as $index => $data) {
            // Get a random class and subject
            $class = $classes->random();
            $classSubjects = $class->subjects;

            if ($classSubjects->isEmpty()) {
                continue;
            }

            $subject = $classSubjects->random();

            $assignment = Assignment::create([
                'school_id' => $school->id,
                'teacher_id' => $teacher->id,
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'instructions' => $data['instructions'],
                'type' => $data['type'],
                'status' => $index < 3 ? 'published' : 'draft', // First 3 are published
                'assigned_date' => $data['assigned_date'],
                'due_date' => $data['due_date'],
                'max_marks' => $data['max_marks'],
                'allow_late_submission' => $data['allow_late_submission'] ?? false,
                'grading_criteria' => 'Excellent: 90-100%, Good: 70-89%, Satisfactory: 50-69%, Needs Improvement: Below 50%',
                'is_active' => true,
            ]);

            // Create submissions for published assignments
            if ($assignment->status === 'published') {
                $assignment->createSubmissionsForClass();

                // Simulate some submissions
                $submissions = $assignment->submissions()->with('student')->get();

                foreach ($submissions->take(rand(2, $submissions->count())) as $submission) {
                    // Some submissions are submitted
                    if (rand(1, 100) <= 70) { // 70% submission rate
                        $submission->submit(
                            "Sample submission content for {$assignment->title}",
                            null
                        );

                        // Some submitted assignments are graded
                        if (rand(1, 100) <= 60) { // 60% of submitted are graded
                            $marks = rand(10, $assignment->max_marks);
                            $submission->grade(
                                $marks,
                                $this->generateFeedback($marks, $assignment->max_marks),
                                null,
                                $teacher->id
                            );
                        }
                    }
                }
            }

            $this->command->info("Created assignment: {$assignment->title} for {$class->name}");
        }

        $this->command->info('Assignment seeder completed successfully!');
    }

    private function generateFeedback($marks, $maxMarks)
    {
        $percentage = ($marks / $maxMarks) * 100;

        if ($percentage >= 90) {
            return 'Excellent work! Keep it up.';
        } elseif ($percentage >= 70) {
            return 'Good job! Well done.';
        } elseif ($percentage >= 50) {
            return 'Satisfactory work. There is room for improvement.';
        } else {
            return 'Needs more practice. Please see me for additional help.';
        }
    }
}
