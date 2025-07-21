<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\ClassRoom;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class ClassSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::first();

        if (!$school) {
            $this->command->error('No school found. Please run SchoolSeeder first.');
            return;
        }

        // Get available teachers
        $teachers = Teacher::where('school_id', $school->id)->get();

        if ($teachers->isEmpty()) {
            $this->command->error('No teachers found. Please run TeacherSeeder first.');
            return;
        }

        $classes = [
            // Kindergarten/Pre-Primary
            [
                'name' => 'Nursery A',
                'section' => 'A',
                'grade_level' => 0,
                'capacity' => 20,
                'description' => 'Nursery section for age 3-4 years',
            ],
            [
                'name' => 'Nursery B',
                'section' => 'B',
                'grade_level' => 0,
                'capacity' => 20,
                'description' => 'Nursery section for age 3-4 years',
            ],
            [
                'name' => 'KG-1 A',
                'section' => 'A',
                'grade_level' => 1,
                'capacity' => 25,
                'description' => 'Kindergarten Grade 1 - Section A',
            ],
            [
                'name' => 'KG-1 B',
                'section' => 'B',
                'grade_level' => 1,
                'capacity' => 25,
                'description' => 'Kindergarten Grade 1 - Section B',
            ],
            [
                'name' => 'KG-2 A',
                'section' => 'A',
                'grade_level' => 2,
                'capacity' => 25,
                'description' => 'Kindergarten Grade 2 - Section A',
            ],

            // Primary School (Grades 1-5)
            [
                'name' => 'Grade 1A',
                'section' => 'A',
                'grade_level' => 3,
                'capacity' => 30,
                'description' => 'First Grade - Section A',
            ],
            [
                'name' => 'Grade 1B',
                'section' => 'B',
                'grade_level' => 3,
                'capacity' => 30,
                'description' => 'First Grade - Section B',
            ],
            [
                'name' => 'Grade 2A',
                'section' => 'A',
                'grade_level' => 4,
                'capacity' => 30,
                'description' => 'Second Grade - Section A',
            ],
            [
                'name' => 'Grade 2B',
                'section' => 'B',
                'grade_level' => 4,
                'capacity' => 30,
                'description' => 'Second Grade - Section B',
            ],
            [
                'name' => 'Grade 3A',
                'section' => 'A',
                'grade_level' => 5,
                'capacity' => 32,
                'description' => 'Third Grade - Section A',
            ],
            [
                'name' => 'Grade 3B',
                'section' => 'B',
                'grade_level' => 5,
                'capacity' => 32,
                'description' => 'Third Grade - Section B',
            ],
            [
                'name' => 'Grade 4A',
                'section' => 'A',
                'grade_level' => 6,
                'capacity' => 32,
                'description' => 'Fourth Grade - Section A',
            ],
            [
                'name' => 'Grade 4B',
                'section' => 'B',
                'grade_level' => 6,
                'capacity' => 32,
                'description' => 'Fourth Grade - Section B',
            ],
            [
                'name' => 'Grade 5A',
                'section' => 'A',
                'grade_level' => 7,
                'capacity' => 35,
                'description' => 'Fifth Grade - Section A',
            ],
            [
                'name' => 'Grade 5B',
                'section' => 'B',
                'grade_level' => 7,
                'capacity' => 35,
                'description' => 'Fifth Grade - Section B',
            ],

            // Middle School (Grades 6-8)
            [
                'name' => 'Grade 6A',
                'section' => 'A',
                'grade_level' => 8,
                'capacity' => 35,
                'description' => 'Sixth Grade - Section A (Middle School)',
            ],
            [
                'name' => 'Grade 6B',
                'section' => 'B',
                'grade_level' => 8,
                'capacity' => 35,
                'description' => 'Sixth Grade - Section B (Middle School)',
            ],
            [
                'name' => 'Grade 7A',
                'section' => 'A',
                'grade_level' => 9,
                'capacity' => 35,
                'description' => 'Seventh Grade - Section A (Middle School)',
            ],
            [
                'name' => 'Grade 7B',
                'section' => 'B',
                'grade_level' => 9,
                'capacity' => 35,
                'description' => 'Seventh Grade - Section B (Middle School)',
            ],
            [
                'name' => 'Grade 8A',
                'section' => 'A',
                'grade_level' => 10,
                'capacity' => 40,
                'description' => 'Eighth Grade - Section A (Middle School)',
            ],
            [
                'name' => 'Grade 8B',
                'section' => 'B',
                'grade_level' => 10,
                'capacity' => 40,
                'description' => 'Eighth Grade - Section B (Middle School)',
            ],

            // High School (Grades 9-12)
            [
                'name' => 'Grade 9A',
                'section' => 'A',
                'grade_level' => 11,
                'capacity' => 40,
                'description' => 'Ninth Grade - Section A (High School)',
            ],
            [
                'name' => 'Grade 9B',
                'section' => 'B',
                'grade_level' => 11,
                'capacity' => 40,
                'description' => 'Ninth Grade - Section B (High School)',
            ],
            [
                'name' => 'Grade 10A',
                'section' => 'A',
                'grade_level' => 12,
                'capacity' => 40,
                'description' => 'Tenth Grade - Section A (High School)',
            ],
            [
                'name' => 'Grade 10B',
                'section' => 'B',
                'grade_level' => 12,
                'capacity' => 40,
                'description' => 'Tenth Grade - Section B (High School)',
            ],
            [
                'name' => 'Grade 11 Science',
                'section' => 'Science',
                'grade_level' => 13,
                'capacity' => 35,
                'description' => 'Eleventh Grade - Science Stream',
            ],
            [
                'name' => 'Grade 11 Commerce',
                'section' => 'Commerce',
                'grade_level' => 13,
                'capacity' => 35,
                'description' => 'Eleventh Grade - Commerce Stream',
            ],
            [
                'name' => 'Grade 11 Arts',
                'section' => 'Arts',
                'grade_level' => 13,
                'capacity' => 30,
                'description' => 'Eleventh Grade - Arts Stream',
            ],
            [
                'name' => 'Grade 12 Science',
                'section' => 'Science',
                'grade_level' => 14,
                'capacity' => 35,
                'description' => 'Twelfth Grade - Science Stream',
            ],
            [
                'name' => 'Grade 12 Commerce',
                'section' => 'Commerce',
                'grade_level' => 14,
                'capacity' => 35,
                'description' => 'Twelfth Grade - Commerce Stream',
            ],
            [
                'name' => 'Grade 12 Arts',
                'section' => 'Arts',
                'grade_level' => 14,
                'capacity' => 30,
                'description' => 'Twelfth Grade - Arts Stream',
            ],
        ];

        foreach ($classes as $index => $classData) {
            // Assign a class teacher (cycle through available teachers)
            $classTeacher = $teachers[$index % $teachers->count()];

            ClassRoom::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'name' => $classData['name'],
                    'section' => $classData['section'],
                ],
                [
                    'school_id' => $school->id,
                    'name' => $classData['name'],
                    'section' => $classData['section'],
                    'grade_level' => $classData['grade_level'],
                    'capacity' => $classData['capacity'],
                    'class_teacher_id' => $classTeacher->id,
                    'description' => $classData['description'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✅ Created ' . count($classes) . ' classes for ' . $school->name);
        $this->command->info('📊 Grade levels: Nursery (0) to Grade 12 (14)');
        $this->command->info('👥 Total capacity: ' . array_sum(array_column($classes, 'capacity')) . ' students');
    }
}
