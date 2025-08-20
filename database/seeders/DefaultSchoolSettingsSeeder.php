<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SchoolSetting;
use App\Models\School;

class DefaultSchoolSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all schools to apply default settings
        $schools = School::all();

        foreach ($schools as $school) {
            $this->createDefaultSettings($school->id);
        }
    }

    /**
     * Create default settings for a school
     */
    private function createDefaultSettings($schoolId): void
    {
        $defaultSettings = [
            // Admission Number Settings
            [
                'key' => 'admission_number_prefix',
                'value' => 'STU',
                'type' => 'string',
                'category' => 'admission',
                'description' => 'Prefix for admission numbers (e.g., STU, SCH)'
            ],
            [
                'key' => 'admission_number_format',
                'value' => 'sequential',
                'type' => 'string',
                'category' => 'admission',
                'description' => 'Format: sequential (continuous) or year_sequential (resets yearly)'
            ],
            [
                'key' => 'admission_number_start_from',
                'value' => 1,
                'type' => 'integer',
                'category' => 'admission',
                'description' => 'Starting number for sequence'
            ],
            [
                'key' => 'admission_number_include_year',
                'value' => true,
                'type' => 'boolean',
                'category' => 'admission',
                'description' => 'Include current year in admission number'
            ],
            [
                'key' => 'admission_number_year_format',
                'value' => 'YYYY',
                'type' => 'string',
                'category' => 'admission',
                'description' => 'Year format: YYYY (2025) or YY (25)'
            ],
            [
                'key' => 'admission_number_padding',
                'value' => 4,
                'type' => 'integer',
                'category' => 'admission',
                'description' => 'Number of digits for sequential part (with zero padding)'
            ],

            // General School Settings
            [
                'key' => 'school_name_display',
                'value' => '',
                'type' => 'string',
                'category' => 'general',
                'description' => 'Display name for the school (if different from database name)'
            ],
            [
                'key' => 'school_logo_url',
                'value' => '',
                'type' => 'file_url',
                'category' => 'branding',
                'description' => 'URL to school logo image'
            ],
            [
                'key' => 'app_banner_url',
                'value' => '',
                'type' => 'file_url',
                'category' => 'branding',
                'description' => 'URL to app banner image for mobile applications'
            ],
            [
                'key' => 'primary_color',
                'value' => '#3B82F6',
                'type' => 'string',
                'category' => 'branding',
                'description' => 'Primary brand color for the school'
            ],
            [
                'key' => 'secondary_color',
                'value' => '#10B981',
                'type' => 'string',
                'category' => 'branding',
                'description' => 'Secondary brand color for the school'
            ],

            // Academic Settings
            [
                'key' => 'academic_year_start_month',
                'value' => 4,
                'type' => 'integer',
                'category' => 'academic',
                'description' => 'Month when academic year starts (1-12)'
            ],
            [
                'key' => 'attendance_required',
                'value' => true,
                'type' => 'boolean',
                'category' => 'academic',
                'description' => 'Whether attendance marking is mandatory'
            ],
            [
                'key' => 'minimum_attendance_percentage',
                'value' => 75,
                'type' => 'integer',
                'category' => 'academic',
                'description' => 'Minimum attendance percentage required for students'
            ],

            // Notification Settings
            [
                'key' => 'parent_notification_enabled',
                'value' => true,
                'type' => 'boolean',
                'category' => 'notifications',
                'description' => 'Enable notifications for parents'
            ],
            [
                'key' => 'teacher_notification_enabled',
                'value' => true,
                'type' => 'boolean',
                'category' => 'notifications',
                'description' => 'Enable notifications for teachers'
            ],

            // Module Settings
            [
                'key' => 'assignment_auto_grade',
                'value' => false,
                'type' => 'boolean',
                'category' => 'modules',
                'description' => 'Enable automatic grading for assignments'
            ],
            [
                'key' => 'assessment_result_auto_publish',
                'value' => false,
                'type' => 'boolean',
                'category' => 'modules',
                'description' => 'Automatically publish assessment results'
            ],
        ];

        foreach ($defaultSettings as $setting) {
            SchoolSetting::updateOrCreate(
                [
                    'school_id' => $schoolId,
                    'key' => $setting['key']
                ],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                    'category' => $setting['category'],
                    'description' => $setting['description'],
                    'is_active' => true
                ]
            );
        }
    }
}
