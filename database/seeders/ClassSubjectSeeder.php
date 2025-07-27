<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClassRoom;
use App\Models\Subject;
use App\Models\School;

class ClassSubjectSeeder extends Seeder
{
    public function run()
    {
        $school = School::first();

        if (!$school) {
            $this->command->info('Please ensure you have at least one school before running this seeder.');
            return;
        }

        // Get subjects
        $subjects = Subject::where('school_id', $school->id)->get();

        if ($subjects->count() === 0) {
            $this->command->info('Please run SubjectSeeder first to create subjects.');
            return;
        }

        // Get classes by grade level
        $nurseryClasses = ClassRoom::where('school_id', $school->id)
            ->where('grade_level', 0) // Nursery
            ->get();

        $class1 = ClassRoom::where('school_id', $school->id)
            ->where('grade_level', 1)
            ->get();

        $class2 = ClassRoom::where('school_id', $school->id)
            ->where('grade_level', 2)
            ->get();

        $class3 = ClassRoom::where('school_id', $school->id)
            ->where('grade_level', 3)
            ->get();

        // Define subject assignments by grade
        $gradeSubjects = [
            'nursery' => [
                'Nursery Rhymes',
                'Colors & Shapes',
                'Play & Movement',
                'Art & Craft',
                'Social Skills'
            ],
            'class1' => [
                'Nursery Rhymes',
                'Phonics',
                'Numbers & Counting',
                'Colors & Shapes',
                'Art & Craft',
                'Story Time',
                'Play & Movement'
            ],
            'class2' => [
                'Phonics',
                'Numbers & Counting',
                'Story Time',
                'Art & Craft',
                'Play & Movement',
                'Social Skills',
                'Colors & Shapes'
            ],
            'class3' => [
                'Phonics',
                'Numbers & Counting',
                'Story Time',
                'Art & Craft',
                'Play & Movement',
                'Social Skills'
            ]
        ];

        // Assign subjects to Nursery classes
        foreach ($nurseryClasses as $class) {
            $this->assignSubjectsToClass($class, $subjects, $gradeSubjects['nursery']);
        }

        // Assign subjects to Class 1
        foreach ($class1 as $class) {
            $this->assignSubjectsToClass($class, $subjects, $gradeSubjects['class1']);
        }

        // Assign subjects to Class 2
        foreach ($class2 as $class) {
            $this->assignSubjectsToClass($class, $subjects, $gradeSubjects['class2']);
        }

        // Assign subjects to Class 3
        foreach ($class3 as $class) {
            $this->assignSubjectsToClass($class, $subjects, $gradeSubjects['class3']);
        }

        $this->command->info('Class subjects assigned successfully!');
    }

    private function assignSubjectsToClass($class, $subjects, $subjectNames)
    {
        // Get subject IDs based on names
        $subjectIds = $subjects->whereIn('name', $subjectNames)->pluck('id')->toArray();

        // Sync subjects to class (this will remove existing and add new ones)
        $class->subjects()->sync($subjectIds);

        $this->command->info("Assigned " . count($subjectIds) . " subjects to {$class->name}");
    }
}
