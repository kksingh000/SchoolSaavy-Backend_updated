<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AcademicYear;
use App\Models\School;
use Carbon\Carbon;

class AcademicYearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🗓️ Creating academic years...');

        // Get all schools
        $schools = School::all();

        foreach ($schools as $school) {
            $this->createAcademicYearsForSchool($school);
        }

        $this->command->info('✅ Academic years created successfully');
    }

    private function createAcademicYearsForSchool(School $school)
    {
        $academicYears = [
            [
                'year_label' => '2023-24',
                'display_name' => 'Academic Year 2023-2024',
                'start_date' => Carbon::create(2023, 4, 1),
                'end_date' => Carbon::create(2024, 3, 31),
                'promotion_start_date' => Carbon::create(2024, 2, 1),
                'promotion_end_date' => Carbon::create(2024, 4, 15),
                'is_current' => false,
                'status' => 'completed',
                'settings' => [
                    'term_system' => '3_terms',
                    'grading_system' => 'percentage',
                    'max_absence_days' => 30
                ]
            ],
            [
                'year_label' => '2024-25',
                'display_name' => 'Academic Year 2024-2025',
                'start_date' => Carbon::create(2024, 4, 1),
                'end_date' => Carbon::create(2025, 3, 31),
                'promotion_start_date' => Carbon::create(2025, 2, 1),
                'promotion_end_date' => Carbon::create(2025, 4, 15),
                'is_current' => true,
                'status' => 'active',
                'settings' => [
                    'term_system' => '3_terms',
                    'grading_system' => 'percentage',
                    'max_absence_days' => 30
                ]
            ],
            [
                'year_label' => '2025-26',
                'display_name' => 'Academic Year 2025-2026',
                'start_date' => Carbon::create(2025, 4, 1),
                'end_date' => Carbon::create(2026, 3, 31),
                'promotion_start_date' => Carbon::create(2026, 2, 1),
                'promotion_end_date' => Carbon::create(2026, 4, 15),
                'is_current' => false,
                'status' => 'upcoming',
                'settings' => [
                    'term_system' => '3_terms',
                    'grading_system' => 'percentage',
                    'max_absence_days' => 30
                ]
            ]
        ];

        foreach ($academicYears as $yearData) {
            AcademicYear::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'year_label' => $yearData['year_label']
                ],
                array_merge($yearData, ['school_id' => $school->id])
            );
        }

        $this->command->info("✅ Created academic years for {$school->name}");
    }
}
