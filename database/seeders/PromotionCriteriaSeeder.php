<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PromotionCriteria;
use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\School;

class PromotionCriteriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📝 Creating promotion criteria...');

        // Get all schools with their academic years and classes
        $schools = School::with(['classes', 'academicYears'])->get();

        foreach ($schools as $school) {
            $this->createPromotionCriteriaForSchool($school);
        }

        $this->command->info('✅ Promotion criteria created successfully');
    }

    private function createPromotionCriteriaForSchool(School $school)
    {
        // Get current and upcoming academic years
        $academicYears = $school->academicYears()
            ->whereIn('status', ['active', 'upcoming'])
            ->get();

        if ($academicYears->isEmpty()) {
            $this->command->warn("No active/upcoming academic years found for {$school->name}");
            return;
        }

        // Get classes ordered by grade level
        $classes = $school->classes()
            ->orderBy('grade_level')
            ->get()
            ->groupBy('grade_level');

        foreach ($academicYears as $academicYear) {
            $createdCount = 0;

            foreach ($classes as $gradeLevel => $gradeLevelClasses) {
                foreach ($gradeLevelClasses as $class) {
                    // Find next grade level class for promotion
                    $nextGradeLevelClasses = $classes->get($gradeLevel + 1);
                    $nextClass = $nextGradeLevelClasses ? $nextGradeLevelClasses->first() : null;

                    // Skip if this is the highest grade (no promotion)
                    if ($gradeLevel >= 14) { // Grade 12 equivalent
                        continue;
                    }

                    $criteria = $this->getCriteriaForGradeLevel($gradeLevel);

                    PromotionCriteria::firstOrCreate(
                        [
                            'school_id' => $school->id,
                            'from_class_id' => $class->id,
                            'academic_year_id' => $academicYear->id
                        ],
                        array_merge($criteria, [
                            'to_class_id' => $nextClass ? $nextClass->id : null,
                            'is_active' => true
                        ])
                    );

                    $createdCount++;
                }
            }

            $this->command->info("✅ Created {$createdCount} promotion criteria for {$school->name} - {$academicYear->year_label}");
        }
    }

    private function getCriteriaForGradeLevel($gradeLevel)
    {
        // Primary school criteria (Nursery to Grade 5)
        if ($gradeLevel <= 7) {
            return [
                'minimum_attendance_percentage' => 75.00,
                'minimum_assignment_average' => 40.00,
                'minimum_assessment_average' => 40.00,
                'minimum_overall_percentage' => 45.00,
                'promotion_weightages' => [
                    'attendance' => 25,
                    'assignments' => 35,
                    'assessments' => 40
                ],
                'minimum_attendance_days' => 150,
                'maximum_disciplinary_actions' => 8,
                'require_parent_meeting' => false,
                'grace_marks_allowed' => 5.00,
                'allow_conditional_promotion' => true,
                'has_remedial_option' => true,
                'remedial_subjects' => ['English', 'Mathematics']
            ];
        }

        // Middle school criteria (Grade 6-8)
        if ($gradeLevel <= 10) {
            return [
                'minimum_attendance_percentage' => 80.00,
                'minimum_assignment_average' => 50.00,
                'minimum_assessment_average' => 50.00,
                'minimum_overall_percentage' => 50.00,
                'promotion_weightages' => [
                    'attendance' => 20,
                    'assignments' => 40,
                    'assessments' => 40
                ],
                'minimum_attendance_days' => 160,
                'maximum_disciplinary_actions' => 5,
                'require_parent_meeting' => false,
                'grace_marks_allowed' => 4.00,
                'allow_conditional_promotion' => true,
                'has_remedial_option' => true,
                'remedial_subjects' => ['English', 'Mathematics', 'Science']
            ];
        }

        // High school criteria (Grade 9-10)
        if ($gradeLevel <= 12) {
            return [
                'minimum_attendance_percentage' => 85.00,
                'minimum_assignment_average' => 55.00,
                'minimum_assessment_average' => 55.00,
                'minimum_overall_percentage' => 55.00,
                'promotion_weightages' => [
                    'attendance' => 15,
                    'assignments' => 35,
                    'assessments' => 50
                ],
                'minimum_attendance_days' => 170,
                'maximum_disciplinary_actions' => 3,
                'require_parent_meeting' => true,
                'grace_marks_allowed' => 3.00,
                'allow_conditional_promotion' => false,
                'has_remedial_option' => true,
                'remedial_subjects' => ['English', 'Mathematics', 'Science', 'History']
            ];
        }

        // Senior high school criteria (Grade 11-12)
        return [
            'minimum_attendance_percentage' => 90.00,
            'minimum_assignment_average' => 60.00,
            'minimum_assessment_average' => 60.00,
            'minimum_overall_percentage' => 60.00,
            'promotion_weightages' => [
                'attendance' => 10,
                'assignments' => 30,
                'assessments' => 60
            ],
            'minimum_attendance_days' => 180,
            'maximum_disciplinary_actions' => 2,
            'require_parent_meeting' => true,
            'grace_marks_allowed' => 2.00,
            'allow_conditional_promotion' => false,
            'has_remedial_option' => false,
            'remedial_subjects' => null
        ];
    }
}
