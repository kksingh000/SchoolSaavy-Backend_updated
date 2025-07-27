<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AssessmentType;

class AssessmentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $assessmentTypes = [
            [
                'name' => 'UT',
                'display_name' => 'Unit Test',
                'description' => 'Monthly unit assessments to evaluate student understanding',
                'frequency' => 'monthly',
                'weightage_percentage' => 25,
                'sort_order' => 1,
                'is_active' => true,
                'settings' => json_encode([
                    'allow_retakes' => false,
                    'auto_publish_results' => true,
                    'max_attempts' => 1,
                    'time_limit_minutes' => 90,
                    'negative_marking' => false
                ]),
                'school_id' => 7,
            ],
            [
                'name' => 'FA',
                'display_name' => 'Formative Assessment',
                'description' => 'Continuous assessment throughout the term',
                'frequency' => 'weekly',
                'weightage_percentage' => 20,
                'sort_order' => 2,
                'is_active' => true,
                'settings' => json_encode([
                    'allow_retakes' => true,
                    'auto_publish_results' => true,
                    'max_attempts' => 2,
                    'time_limit_minutes' => 60,
                    'negative_marking' => false
                ]),
                'school_id' => 7,
            ],
            [
                'name' => 'SA',
                'display_name' => 'Summative Assessment',
                'description' => 'End of term comprehensive assessment',
                'frequency' => 'quarterly',
                'weightage_percentage' => 35,
                'sort_order' => 3,
                'is_active' => true,
                'settings' => json_encode([
                    'allow_retakes' => false,
                    'auto_publish_results' => false,
                    'max_attempts' => 1,
                    'time_limit_minutes' => 180,
                    'negative_marking' => true
                ]),
                'school_id' => 7,
            ],
            [
                'name' => 'FINAL',
                'display_name' => 'Final Examination',
                'description' => 'Annual final examination',
                'frequency' => 'yearly',
                'weightage_percentage' => 50,
                'sort_order' => 4,
                'is_active' => true,
                'settings' => json_encode([
                    'allow_retakes' => false,
                    'auto_publish_results' => false,
                    'max_attempts' => 1,
                    'time_limit_minutes' => 240,
                    'negative_marking' => true
                ]),
                'school_id' => 7,
            ],
            [
                'name' => 'QUIZ',
                'display_name' => 'Class Quiz',
                'description' => 'Quick knowledge check quiz',
                'frequency' => 'weekly',
                'weightage_percentage' => 10,
                'sort_order' => 5,
                'is_active' => true,
                'settings' => json_encode([
                    'allow_retakes' => true,
                    'auto_publish_results' => true,
                    'max_attempts' => 3,
                    'time_limit_minutes' => 30,
                    'negative_marking' => false
                ]),
                'school_id' => 7,
            ]
        ];

        foreach ($assessmentTypes as $type) {
            AssessmentType::create($type);
        }
    }
}
