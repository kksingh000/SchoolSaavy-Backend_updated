<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Assessment;
use App\Models\AssessmentType;
use Carbon\Carbon;

class AssessmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $unitTestType = AssessmentType::where('name', 'UT')->first();
        $formativeType = AssessmentType::where('name', 'FA')->first();
        $summativeType = AssessmentType::where('name', 'SA')->first();
        $quizType = AssessmentType::where('name', 'QUIZ')->first();

        $assessments = [
            // Unit Tests
            [
                'assessment_type_id' => $unitTestType->id,
                'title' => 'UT-1 Algebra Basics',
                'code' => 'UT1-MAT-2025',
                'description' => 'First unit test covering basic algebraic equations and expressions',
                'subject_id' => 1, // Mathematics
                'class_id' => 1, // Class with students
                'school_id' => 7,
                'teacher_id' => 1, // Teacher
                'assessment_date' => Carbon::now()->addDays(7)->format('Y-m-d'),
                'start_time' => '09:00:00',
                'end_time' => '10:30:00',
                'duration_minutes' => 90,
                'total_marks' => 100,
                'passing_marks' => 40,
                'marking_scheme' => json_encode([
                    'mcq' => 20,
                    'short_answer' => 40,
                    'problem_solving' => 40
                ]),
                'syllabus_covered' => 'Chapter 1-3: Linear Equations, Polynomials, Factorization',
                'topics' => json_encode([
                    'Linear equations in one variable',
                    'Polynomials and degrees',
                    'Factorization methods'
                ]),
                'instructions' => json_encode([
                    'Use blue/black pen only',
                    'Calculators not allowed',
                    'Show all working steps',
                    'Write legibly'
                ]),
                'status' => 'scheduled',
                'academic_year' => '2024-25'
            ],
            [
                'assessment_type_id' => $unitTestType->id,
                'title' => 'UT-1 Grammar & Comprehension',
                'code' => 'UT1-ENG-2025',
                'description' => 'English grammar rules and reading comprehension test',
                'subject_id' => 2, // English
                'class_id' => 1, // Class with students
                'school_id' => 7,
                'teacher_id' => 2, // Teacher
                'assessment_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
                'start_time' => '11:00:00',
                'end_time' => '12:30:00',
                'duration_minutes' => 90,
                'total_marks' => 80,
                'passing_marks' => 32,
                'marking_scheme' => json_encode([
                    'grammar' => 30,
                    'comprehension' => 30,
                    'writing' => 20
                ]),
                'syllabus_covered' => 'Grammar: Tenses, Articles, Comprehension: Prose passages',
                'topics' => json_encode([
                    'Present and past tenses',
                    'Articles usage',
                    'Reading comprehension'
                ]),
                'instructions' => json_encode([
                    'Read all questions carefully',
                    'Write legibly in blue/black pen',
                    'Manage time properly'
                ]),
                'status' => 'scheduled',
                'academic_year' => '2024-25'
            ],
            // Formative Assessments
            [
                'assessment_type_id' => $formativeType->id,
                'title' => 'FA-1 Quick Math Check',
                'code' => 'FA1-MAT-2025',
                'description' => 'Weekly formative assessment on recent math topics',
                'subject_id' => 1, // Mathematics
                'class_id' => 1, // Class with students
                'school_id' => 7,
                'teacher_id' => 1,
                'assessment_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                'start_time' => '14:00:00',
                'end_time' => '14:45:00',
                'duration_minutes' => 45,
                'total_marks' => 50,
                'passing_marks' => 20,
                'marking_scheme' => json_encode([
                    'quick_problems' => 50
                ]),
                'syllabus_covered' => 'Recent class topics',
                'topics' => json_encode([
                    'Basic arithmetic',
                    'Simple equations'
                ]),
                'instructions' => json_encode([
                    'Quick assessment',
                    'No calculators allowed'
                ]),
                'status' => 'scheduled',
                'academic_year' => '2024-25'
            ],
            // Completed Assessment for demo
            [
                'assessment_type_id' => $quizType->id,
                'title' => 'Math Quiz - Completed',
                'code' => 'QUIZ1-MAT-2025',
                'description' => 'Completed math quiz for demonstration',
                'subject_id' => 1, // Mathematics
                'class_id' => 1, // Class with students
                'school_id' => 7,
                'teacher_id' => 1,
                'assessment_date' => Carbon::now()->subDays(5)->format('Y-m-d'),
                'start_time' => '10:00:00',
                'end_time' => '10:30:00',
                'duration_minutes' => 30,
                'total_marks' => 40,
                'passing_marks' => 16,
                'marking_scheme' => json_encode([
                    'mcq' => 20,
                    'short_answer' => 20
                ]),
                'syllabus_covered' => 'Basic math operations',
                'topics' => json_encode([
                    'Addition and subtraction',
                    'Multiplication tables'
                ]),
                'instructions' => json_encode([
                    'Complete all questions',
                    'Time limit: 30 minutes'
                ]),
                'status' => 'results_published',
                'academic_year' => '2024-25'
            ]
        ];

        foreach ($assessments as $assessment) {
            Assessment::create($assessment);
        }
    }
}
