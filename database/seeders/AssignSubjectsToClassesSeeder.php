<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClassRoom;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

class AssignSubjectsToClassesSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $this->command->info('Starting to assign subjects to classes...');

        // Get the school ID (Cambridge International School)
        $schoolId = 7;

        // Get all classes for this school
        $classes = ClassRoom::where('school_id', $schoolId)->get();
        $subjects = Subject::where('school_id', $schoolId)->get()->keyBy('code');

        $this->command->info("Found {$classes->count()} classes and {$subjects->count()} subjects");

        foreach ($classes as $class) {
            $this->assignSubjectsToClass($class, $subjects);
        }

        $this->command->info('Subject assignment completed!');
    }

    private function assignSubjectsToClass($class, $subjects)
    {
        $gradeLevel = $class->grade_level;
        $className = $class->name;

        // Skip if class already has subjects assigned
        if ($class->subjects()->count() > 0) {
            $this->command->info("Skipping {$className} - already has subjects assigned");
            return;
        }

        $subjectsToAssign = [];

        // Define subjects by grade level
        switch ($gradeLevel) {
            case 0: // Nursery
                $subjectsToAssign = $this->getNurserySubjects($subjects);
                break;
            case 1: // KG/Nursery
                $subjectsToAssign = $this->getKGSubjects($subjects);
                break;
            case 2: // KG-2
                $subjectsToAssign = $this->getKG2Subjects($subjects);
                break;
            case 3: // Grade 1
                $subjectsToAssign = $this->getGrade1Subjects($subjects);
                break;
            case 4: // Grade 2
                $subjectsToAssign = $this->getGrade2Subjects($subjects);
                break;
            case 5: // Grade 3
                $subjectsToAssign = $this->getGrade3Subjects($subjects);
                break;
            case 6: // Grade 4
                $subjectsToAssign = $this->getGrade4Subjects($subjects);
                break;
            case 7: // Grade 5
                $subjectsToAssign = $this->getGrade5Subjects($subjects);
                break;
            case 8: // Grade 6
                $subjectsToAssign = $this->getGrade6Subjects($subjects);
                break;
            case 9: // Grade 7
                $subjectsToAssign = $this->getGrade7Subjects($subjects);
                break;
            case 10: // Grade 8
                $subjectsToAssign = $this->getGrade8Subjects($subjects);
                break;
            case 11: // Grade 9
                $subjectsToAssign = $this->getGrade9Subjects($subjects);
                break;
            case 12: // Grade 10
                $subjectsToAssign = $this->getGrade10Subjects($subjects);
                break;
            case 13: // Grade 11
                $subjectsToAssign = $this->getGrade11Subjects($subjects, $className);
                break;
            case 14: // Grade 12
                $subjectsToAssign = $this->getGrade12Subjects($subjects, $className);
                break;
            default:
                $subjectsToAssign = $this->getDefaultSubjects($subjects);
                break;
        }

        if (!empty($subjectsToAssign)) {
            $class->subjects()->sync($subjectsToAssign);
            $this->command->info("Assigned " . count($subjectsToAssign) . " subjects to {$className}");
        } else {
            $this->command->warn("No subjects assigned to {$className} (Grade Level: {$gradeLevel})");
        }
    }

    private function getNurserySubjects($subjects)
    {
        return $this->getSubjectIds($subjects, [
            'NR',      // Nursery Rhymes
            'CIS_CA001', // Creative Arts
            'CIS_PL001', // Play & Learn
            'CIS_CP001', // Creative Play
            'PM'       // Play & Movement
        ]);
    }

    private function getKGSubjects($subjects)
    {
        return $this->getSubjectIds($subjects, [
            'NR',      // Nursery Rhymes
            'PHO',     // Phonics
            'CIS_NF001', // Number Fun
            'CIS_LW001', // Letter & Words
            'CIS_CA001', // Creative Arts
            'CIS_CP001', // Creative Play
            'PM'       // Play & Movement
        ]);
    }

    private function getKG2Subjects($subjects)
    {
        return $this->getSubjectIds($subjects, [
            'PHO',     // Phonics
            'NUM',     // Numbers & Counting
            'ST',      // Story Time
            'ART',     // Art & Craft
            'PM',      // Play & Movement
            'SS',      // Social Skills
            'CS'       // Colors & Shapes
        ]);
    }

    private function getGrade1Subjects($subjects)
    {
        return $this->getSubjectIds($subjects, [
            'CIS_ENG',  // English
            'CIS_MATH', // Mathematics
            'CIS_SCI',  // Science
            'CIS_ART',  // Art & Craft
            'CIS_PE',   // Physical Education
            'CIS_SS'    // Social Studies
        ]);
    }

    private function getGrade2Subjects($subjects)
    {
        return $this->getSubjectIds($subjects, [
            'CIS_ENG',  // English
            'CIS_MATH', // Mathematics
            'CIS_SCI',  // Science
            'CIS_ART',  // Art & Craft
            'CIS_PE',   // Physical Education
            'CIS_SS'    // Social Studies
        ]);
    }

    private function getGrade3Subjects($subjects)
    {
        return $this->getSubjectIds($subjects, [
            'CIS_ENG',  // English
            'CIS_MATH', // Mathematics
            'CIS_SCI',  // Science
            'CIS_ART',  // Art & Craft
            'CIS_PE',   // Physical Education
            'CIS_SS',   // Social Studies
            'CIS_CS'    // Computer Science
        ]);
    }

    private function getGrade4Subjects($subjects)
    {
        return $this->getSubjectIds($subjects, [
            'CIS_ENG',  // English
            'CIS_MATH', // Mathematics
            'CIS_SCI',  // Science
            'CIS_ART',  // Art & Craft
            'CIS_PE',   // Physical Education
            'CIS_SS',   // Social Studies
            'CIS_CS'    // Computer Science
        ]);
    }

    private function getGrade5Subjects($subjects)
    {
        return $this->getSubjectIds($subjects, [
            'CIS_ENG',  // English
            'CIS_MATH', // Mathematics
            'CIS_SCI',  // Science
            'CIS_ART',  // Art & Craft
            'CIS_PE',   // Physical Education
            'CIS_SS',   // Social Studies
            'CIS_CS'    // Computer Science
        ]);
    }

    private function getGrade6Subjects($subjects)
    {
        return $this->getSubjectIds($subjects, [
            'CIS_ENG',  // English
            'CIS_MATH', // Mathematics
            'CIS_SCI',  // Science
            'CIS_ART',  // Art & Craft
            'CIS_PE',   // Physical Education
            'CIS_SS',   // Social Studies
            'CIS_CS'    // Computer Science
        ]);
    }

    private function getGrade7Subjects($subjects)
    {
        return $this->getSubjectIds($subjects, [
            'CIS_ENG',  // English
            'CIS_MATH', // Mathematics
            'CIS_SCI',  // Science
            'CIS_ART',  // Art & Craft
            'CIS_PE',   // Physical Education
            'CIS_SS',   // Social Studies
            'CIS_CS'    // Computer Science
        ]);
    }

    private function getGrade8Subjects($subjects)
    {
        return $this->getSubjectIds($subjects, [
            'CIS_ENG',  // English
            'CIS_MATH', // Mathematics
            'CIS_SCI',  // Science
            'CIS_ART',  // Art & Craft
            'CIS_PE',   // Physical Education
            'CIS_SS',   // Social Studies
            'CIS_CS'    // Computer Science
        ]);
    }

    private function getGrade9Subjects($subjects)
    {
        return $this->getSubjectIds($subjects, [
            'CIS_ENG',  // English
            'CIS_MATH', // Mathematics
            'CIS_BIO',  // Biology
            'CIS_CHEM', // Chemistry
            'CIS_PHY',  // Physics
            'CIS_HIST', // History
            'CIS_GEO',  // Geography
            'CIS_CS',   // Computer Science
            'CIS_PE'    // Physical Education
        ]);
    }

    private function getGrade10Subjects($subjects)
    {
        return $this->getSubjectIds($subjects, [
            'CIS_ENG',  // English
            'CIS_MATH', // Mathematics
            'CIS_BIO',  // Biology
            'CIS_CHEM', // Chemistry
            'CIS_PHY',  // Physics
            'CIS_HIST', // History
            'CIS_GEO',  // Geography
            'CIS_CS',   // Computer Science
            'CIS_PE'    // Physical Education
        ]);
    }

    private function getGrade11Subjects($subjects, $className)
    {
        // Stream-based subjects for Grade 11
        if (str_contains($className, 'Science')) {
            return $this->getSubjectIds($subjects, [
                'CIS_ENG',   // English
                'CIS_AMATH', // Advanced Mathematics
                'CIS_BIO',   // Biology
                'CIS_CHEM',  // Chemistry
                'CIS_PHY',   // Physics
                'CIS_CS'     // Computer Science
            ]);
        } elseif (str_contains($className, 'Commerce')) {
            return $this->getSubjectIds($subjects, [
                'CIS_ENG',  // English
                'CIS_MATH', // Mathematics
                'BS',       // Business Studies
                'CIS_CS'    // Computer Science
            ]);
        } elseif (str_contains($className, 'Arts')) {
            return $this->getSubjectIds($subjects, [
                'CIS_ENG',  // English
                'CIS_LIT',  // Literature
                'CIS_HIST', // History
                'CIS_GEO',  // Geography
                'CIS_FR',   // French
                'CIS_ES'    // Spanish
            ]);
        }

        return $this->getDefaultSubjects($subjects);
    }

    private function getGrade12Subjects($subjects, $className)
    {
        // Stream-based subjects for Grade 12 (same as Grade 11)
        return $this->getGrade11Subjects($subjects, $className);
    }

    private function getDefaultSubjects($subjects)
    {
        return $this->getSubjectIds($subjects, [
            'CIS_ENG',  // English
            'CIS_MATH', // Mathematics
            'CIS_SCI',  // Science
            'CIS_ART',  // Art & Craft
            'CIS_PE'    // Physical Education
        ]);
    }

    private function getSubjectIds($subjects, $codes)
    {
        $ids = [];
        foreach ($codes as $code) {
            if (isset($subjects[$code])) {
                $ids[] = $subjects[$code]->id;
            }
        }
        return $ids;
    }
}
