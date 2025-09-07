<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Subject;
use App\Models\ClassRoom;
use App\Models\Teacher;
use App\Models\User;
use App\Models\School;
use Carbon\Carbon;

class AssessmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first school or skip
        $school = \App\Models\School::first();
        if (!$school) {
            $this->command->info('No school found, skipping assessment seeding');
            return;
        }
        
        $schoolId = $school->id;
        
        // Get or create subjects if they don't exist
        $mathSubject = \App\Models\Subject::firstOrCreate(
            ['name' => 'Mathematics', 'school_id' => $schoolId],
            ['code' => 'MATH-01', 'description' => 'Mathematics subject', 'is_active' => true]
        );
        
        $englishSubject = \App\Models\Subject::firstOrCreate(
            ['name' => 'English', 'school_id' => $schoolId],
            ['code' => 'ENG-01', 'description' => 'English subject', 'is_active' => true]
        );
        
        // Get or create a class if none exists
        $class = \App\Models\ClassRoom::first();
        if (!$class) {
            $class = \App\Models\ClassRoom::create([
                'school_id' => $schoolId,
                'name' => 'Demo Class',
                'grade_level' => 5,
                'section' => 'A',
                'capacity' => 30,
                'description' => 'Demo class for seeding purposes'
            ]);
        }
        
        // Get or create a teacher if none exists
        $teacher = \App\Models\Teacher::first();
        if (!$teacher) {
            $user = \App\Models\User::create([
                'name' => 'Demo Teacher',
                'email' => 'demo.teacher@example.com',
                'password' => bcrypt('password'),
                'user_type' => 'teacher',
                'is_active' => true
            ]);
            
            $teacher = \App\Models\Teacher::create([
                'school_id' => $schoolId,
                'user_id' => $user->id,
                'employee_id' => 'TEACH-001',
                'phone' => '1234567890',
                'gender' => 'male',
                'qualification' => 'B.Ed',
                'specializations' => ['Mathematics', 'Science'],
                'joining_date' => now()->subYears(2),
                'address' => '123 Demo Street'
            ]);
        }
        
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
                'subject_id' => $mathSubject->id,
                'class_id' => $class->id,
                'school_id' => $schoolId,
                'teacher_id' => $teacher->id,
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
                'subject_id' => $englishSubject->id,
                'class_id' => $class->id,
                'school_id' => $schoolId,
                'teacher_id' => $teacher->id,
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
                'subject_id' => $mathSubject->id,
                'class_id' => $class->id,
                'school_id' => $schoolId,
                'teacher_id' => $teacher->id,
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
                'subject_id' => $mathSubject->id,
                'class_id' => $class->id,
                'school_id' => $schoolId,
                'teacher_id' => $teacher->id,
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
