<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subject;
use App\Models\School;

class SubjectSeeder extends Seeder
{
    public function run()
    {
        $school = School::first();

        if (!$school) {
            $this->command->info('Please ensure you have at least one school before running this seeder.');
            return;
        }

        $subjects = [
            [
                'school_id' => $school->id,
                'name' => 'Mathematics',
                'code' => 'MATH',
                'description' => 'Basic mathematics for nursery level',
                'is_active' => true,
            ],
            [
                'school_id' => $school->id,
                'name' => 'English',
                'code' => 'ENG',
                'description' => 'English language and literature',
                'is_active' => true,
            ],
            [
                'school_id' => $school->id,
                'name' => 'Science',
                'code' => 'SCI',
                'description' => 'Basic science concepts',
                'is_active' => true,
            ],
            [
                'school_id' => $school->id,
                'name' => 'Art & Craft',
                'code' => 'ART',
                'description' => 'Creative arts and crafts',
                'is_active' => true,
            ],
            [
                'school_id' => $school->id,
                'name' => 'Physical Education',
                'code' => 'PE',
                'description' => 'Physical activities and sports',
                'is_active' => true,
            ],
        ];

        foreach ($subjects as $subject) {
            Subject::create($subject);
        }

        $this->command->info('Subjects seeded successfully!');
    }
}
