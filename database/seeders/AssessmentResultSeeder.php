<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AssessmentResult;
use App\Models\Assessment;
use App\Models\Student;
use Carbon\Carbon;

class AssessmentResultSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the completed assessment for demo results
        $completedAssessment = Assessment::where('status', 'results_published')->first();

        if (!$completedAssessment) {
            return; // No completed assessment to create results for
        }

        // Get students from Grade 1A through pivot table
        $studentIds = \Illuminate\Support\Facades\DB::table('class_student')
            ->where('class_id', 1)
            ->pluck('student_id')
            ->take(10);
        $students = Student::whereIn('id', $studentIds)->get();

        $sampleResults = [
            ['marks' => 36, 'grade' => 'A', 'status' => 'pass', 'remarks' => 'Excellent work!'],
            ['marks' => 32, 'grade' => 'B', 'status' => 'pass', 'remarks' => 'Good performance'],
            ['marks' => 28, 'grade' => 'B', 'status' => 'pass', 'remarks' => 'Well done'],
            ['marks' => 24, 'grade' => 'C', 'status' => 'pass', 'remarks' => 'Satisfactory'],
            ['marks' => 38, 'grade' => 'A', 'status' => 'pass', 'remarks' => 'Outstanding!'],
            ['marks' => 30, 'grade' => 'B', 'status' => 'pass', 'remarks' => 'Good effort'],
            ['marks' => 18, 'grade' => 'D', 'status' => 'pass', 'remarks' => 'Need improvement'],
            ['marks' => 14, 'grade' => 'F', 'status' => 'fail', 'remarks' => 'Requires additional support'],
            ['marks' => 0, 'grade' => 'F', 'status' => 'absent', 'remarks' => 'Was absent'],
            ['marks' => 26, 'grade' => 'C', 'status' => 'pass', 'remarks' => 'Average performance']
        ];

        foreach ($students as $index => $student) {
            if (!isset($sampleResults[$index])) break;

            $result = $sampleResults[$index];
            $percentage = ($result['marks'] / $completedAssessment->total_marks) * 100;

            AssessmentResult::create([
                'assessment_id' => $completedAssessment->id,
                'student_id' => $student->id,
                'marks_obtained' => $result['marks'],
                'percentage' => round($percentage, 2),
                'grade' => $result['grade'],
                'result_status' => $result['status'],
                'remarks' => $result['remarks'],
                'section_wise_marks' => json_encode([
                    'section_a' => rand(8, 15), // MCQ section
                    'section_b' => rand(10, 15), // Short answers
                    'section_c' => rand(5, 10)   // Problem solving
                ]),
                'is_absent' => $result['status'] === 'absent',
                'absence_reason' => $result['status'] === 'absent' ? 'Medical leave' : null,
                'result_published_at' => Carbon::now()->subDays(3),
                'published_by' => 1, // Teacher ID
                'entered_by' => 1,   // Teacher ID
                'created_at' => Carbon::now()->subDays(4),
                'updated_at' => Carbon::now()->subDays(3)
            ]);
        }

        // Create some unpublished results for another assessment
        $upcomingAssessment = Assessment::where('status', 'scheduled')->first();

        if ($upcomingAssessment && $students->count() > 0) {
            // Create a few results but don't publish them (for demo of unpublished results)
            $unpublishedResults = [
                ['marks' => 34, 'grade' => 'B', 'status' => 'pass'],
                ['marks' => 29, 'grade' => 'B', 'status' => 'pass'],
                ['marks' => 41, 'grade' => 'A', 'status' => 'pass']
            ];

            foreach ($unpublishedResults as $index => $result) {
                if (!isset($students[$index])) break;

                $percentage = ($result['marks'] / $upcomingAssessment->total_marks) * 100;

                AssessmentResult::create([
                    'assessment_id' => $upcomingAssessment->id,
                    'student_id' => $students[$index]->id,
                    'marks_obtained' => $result['marks'],
                    'percentage' => round($percentage, 2),
                    'grade' => $result['grade'],
                    'result_status' => $result['status'],
                    'remarks' => 'Results pending publication',
                    'is_absent' => false,
                    'result_published_at' => null, // Not published yet
                    'published_by' => null,
                    'entered_by' => 1,
                    'created_at' => Carbon::now()->subHours(2),
                    'updated_at' => Carbon::now()->subHours(2)
                ]);
            }
        }
    }
}
