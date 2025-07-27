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
                'name' => 'Nursery Rhymes',
                'code' => 'NR',
                'description' => 'Fun nursery rhymes and songs for early learning',
                'is_active' => true,
            ],
            [
                'school_id' => $school->id,
                'name' => 'Phonics',
                'code' => 'PHO',
                'description' => 'Letter sounds and basic phonics for reading readiness',
                'is_active' => true,
            ],
            [
                'school_id' => $school->id,
                'name' => 'Numbers & Counting',
                'code' => 'NUM',
                'description' => 'Basic numbers, counting and early math concepts',
                'is_active' => true,
            ],
            [
                'school_id' => $school->id,
                'name' => 'Art & Craft',
                'code' => 'ART',
                'description' => 'Creative arts, drawing, coloring and simple crafts',
                'is_active' => true,
            ],
            [
                'school_id' => $school->id,
                'name' => 'Story Time',
                'code' => 'ST',
                'description' => 'Interactive storytelling and picture books',
                'is_active' => true,
            ],
            [
                'school_id' => $school->id,
                'name' => 'Play & Movement',
                'code' => 'PM',
                'description' => 'Physical activities, games and motor skills development',
                'is_active' => true,
            ],
            [
                'school_id' => $school->id,
                'name' => 'Colors & Shapes',
                'code' => 'CS',
                'description' => 'Learning colors, shapes and basic patterns',
                'is_active' => true,
            ],
            [
                'school_id' => $school->id,
                'name' => 'Social Skills',
                'code' => 'SS',
                'description' => 'Sharing, friendship and basic social interaction',
                'is_active' => true,
            ],
        ];

        foreach ($subjects as $subject) {
            Subject::create($subject);
        }

        $this->command->info('Subjects seeded successfully!');
    }
}
