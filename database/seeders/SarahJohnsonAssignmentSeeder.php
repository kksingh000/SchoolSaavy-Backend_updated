<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassRoom;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SarahJohnsonAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates assignments for Sarah Johnson (Teacher ID: 52, User ID: 65)
     * Classes: CIS Nursery B (ID: 66), CIS KG B (ID: 68)
     */
    public function run(): void
    {
        // Teacher and School information
        $teacherId = 52;
        $schoolId = 7;
        $classIds = [66, 68]; // CIS Nursery B and CIS KG B

        // Subjects for early childhood education
        $nurserySubjects = [
            1 => 'Nursery Rhymes',
            2 => 'Phonics',
            3 => 'Numbers & Counting',
            4 => 'Art & Craft',
            5 => 'Story Time',
            6 => 'Play & Movement',
            7 => 'Colors & Shapes',
            8 => 'Social Skills'
        ];

        $kgSubjects = [
            103 => 'Play & Learn',
            104 => 'Creative Arts',
            105 => 'Number Fun',
            106 => 'Letter & Words',
            107 => 'Creative Play',
            2 => 'Phonics',
            3 => 'Numbers & Counting',
            4 => 'Art & Craft'
        ];

        // Assignment types for early childhood
        $assignmentTypes = ['homework', 'classwork', 'project', 'assessment'];

        // Create assignments for each class
        $assignments = [];

        // Assignments for CIS Nursery B (Class ID: 66)
        $nurseryAssignments = [
            [
                'title' => 'Sing Your Favorite Nursery Rhyme',
                'description' => 'Practice singing one nursery rhyme at home with your family. Be ready to sing it in class!',
                'instructions' => '1. Choose any nursery rhyme you like\n2. Practice with your parents\n3. Sing it confidently in class',
                'subject_id' => 1, // Nursery Rhymes
                'type' => 'homework',
                'max_marks' => 10,
                'days_from_now' => -5,
                'due_days_from_now' => 2
            ],
            [
                'title' => 'Letter Sound Recognition - A to E',
                'description' => 'Identify and say the sounds of letters A, B, C, D, and E',
                'instructions' => '1. Look at each letter card\n2. Say the letter name\n3. Say the letter sound\n4. Give one word that starts with that letter',
                'subject_id' => 2, // Phonics
                'type' => 'classwork',
                'max_marks' => 15,
                'days_from_now' => -3,
                'due_days_from_now' => 1
            ],
            [
                'title' => 'Count and Color - Numbers 1 to 5',
                'description' => 'Count objects and color the correct number of items',
                'instructions' => '1. Count the objects in each box\n2. Color the correct number\n3. Write the number below',
                'subject_id' => 3, // Numbers & Counting
                'type' => 'homework',
                'max_marks' => 12,
                'days_from_now' => -7,
                'due_days_from_now' => -2
            ],
            [
                'title' => 'Paper Plate Fish Craft',
                'description' => 'Create a colorful fish using a paper plate and art materials',
                'instructions' => '1. Paint the paper plate\n2. Add fish fins and tail\n3. Decorate with colorful patterns\n4. Add googly eyes',
                'subject_id' => 4, // Art & Craft
                'type' => 'project',
                'max_marks' => 20,
                'days_from_now' => -10,
                'due_days_from_now' => -5
            ],
            [
                'title' => 'My Favorite Story Character',
                'description' => 'Draw and tell about your favorite story character',
                'instructions' => '1. Think of your favorite story\n2. Draw the main character\n3. Tell 3 things about them\n4. Share with the class',
                'subject_id' => 5, // Story Time
                'type' => 'homework',
                'max_marks' => 10,
                'days_from_now' => -1,
                'due_days_from_now' => 3
            ],
            [
                'title' => 'Animal Movement Game',
                'description' => 'Learn to move like different animals',
                'instructions' => '1. Practice moving like a rabbit (hop)\n2. Practice moving like a fish (swim)\n3. Practice moving like a bird (fly)\n4. Show all movements in class',
                'subject_id' => 6, // Play & Movement
                'type' => 'classwork',
                'max_marks' => 8,
                'days_from_now' => -2,
                'due_days_from_now' => 1
            ],
            [
                'title' => 'Rainbow Colors Matching',
                'description' => 'Match objects with their correct colors',
                'instructions' => '1. Look at each object\n2. Name the color\n3. Draw a line to match\n4. Color the rainbow at the bottom',
                'subject_id' => 7, // Colors & Shapes
                'type' => 'assessment',
                'max_marks' => 15,
                'days_from_now' => -4,
                'due_days_from_now' => 0
            ],
            [
                'title' => 'Magic Words Practice',
                'description' => 'Practice using please, thank you, and sorry',
                'instructions' => '1. Say "please" when asking for something\n2. Say "thank you" when someone helps\n3. Say "sorry" when you make a mistake\n4. Use these words all day',
                'subject_id' => 8, // Social Skills
                'type' => 'homework',
                'max_marks' => 10,
                'days_from_now' => -6,
                'due_days_from_now' => -1
            ]
        ];

        // Assignments for CIS KG B (Class ID: 68)
        $kgAssignments = [
            [
                'title' => 'Learning Through Play - Building Blocks',
                'description' => 'Build different structures using blocks and describe them',
                'instructions' => '1. Build a tower with 10 blocks\n2. Build a bridge\n3. Build a house\n4. Tell us about each structure',
                'subject_id' => 103, // Play & Learn
                'type' => 'classwork',
                'max_marks' => 15,
                'days_from_now' => -8,
                'due_days_from_now' => -3
            ],
            [
                'title' => 'Nature Art Collection',
                'description' => 'Create art using natural materials like leaves and flowers',
                'instructions' => '1. Collect leaves and flowers\n2. Make a pattern with them\n3. Glue them on paper\n4. Add colors with crayons',
                'subject_id' => 104, // Creative Arts
                'type' => 'project',
                'max_marks' => 20,
                'days_from_now' => -12,
                'due_days_from_now' => -7
            ],
            [
                'title' => 'Number Fun - Count to 10',
                'description' => 'Practice counting objects up to 10 and writing numbers',
                'instructions' => '1. Count toys in each group\n2. Write the number\n3. Circle the correct number\n4. Draw objects for given numbers',
                'subject_id' => 105, // Number Fun
                'type' => 'homework',
                'max_marks' => 12,
                'days_from_now' => -5,
                'due_days_from_now' => 1
            ],
            [
                'title' => 'Letter Writing Practice - My Name',
                'description' => 'Practice writing the letters in your first name',
                'instructions' => '1. Look at your name card\n2. Trace each letter\n3. Write each letter 3 times\n4. Write your full name',
                'subject_id' => 106, // Letter & Words
                'type' => 'homework',
                'max_marks' => 10,
                'days_from_now' => -3,
                'due_days_from_now' => 2
            ],
            [
                'title' => 'Imagination Station - Dream House',
                'description' => 'Design and create your dream house using various materials',
                'instructions' => '1. Draw your dream house\n2. Use cardboard boxes\n3. Decorate with colors and stickers\n4. Present your house to friends',
                'subject_id' => 107, // Creative Play
                'type' => 'project',
                'max_marks' => 25,
                'days_from_now' => -15,
                'due_days_from_now' => -10
            ],
            [
                'title' => 'Phonics Adventure - Letter Sounds F to J',
                'description' => 'Learn and practice sounds for letters F, G, H, I, and J',
                'instructions' => '1. Listen to each letter sound\n2. Repeat the sound 5 times\n3. Find objects that start with each letter\n4. Draw one object for each letter',
                'subject_id' => 2, // Phonics
                'type' => 'assessment',
                'max_marks' => 15,
                'days_from_now' => -2,
                'due_days_from_now' => 1
            ],
            [
                'title' => 'Number Addition Fun - Adding with Objects',
                'description' => 'Learn basic addition using toys and objects',
                'instructions' => '1. Use blocks or toys\n2. Make groups as shown\n3. Count total objects\n4. Write the answer',
                'subject_id' => 3, // Numbers & Counting
                'type' => 'classwork',
                'max_marks' => 12,
                'days_from_now' => -6,
                'due_days_from_now' => -1
            ],
            [
                'title' => 'Friendship Bracelet Making',
                'description' => 'Create friendship bracelets to share with classmates',
                'instructions' => '1. Choose colorful beads\n2. String them in patterns\n3. Make at least 2 bracelets\n4. Give one to a friend',
                'subject_id' => 4, // Art & Craft
                'type' => 'project',
                'max_marks' => 18,
                'days_from_now' => -9,
                'due_days_from_now' => -4
            ]
        ];

        // Helper function to create assignment
        $createAssignment = function ($assignmentData, $classId) use ($teacherId, $schoolId) {
            $assignedDate = Carbon::now()->addDays($assignmentData['days_from_now']);
            $dueDate = Carbon::now()->addDays($assignmentData['due_days_from_now']);

            // Determine status based on dates
            $status = 'published';
            if ($assignedDate->isFuture()) {
                $status = 'draft';
            }

            return Assignment::create([
                'school_id' => $schoolId,
                'teacher_id' => $teacherId,
                'class_id' => $classId,
                'subject_id' => $assignmentData['subject_id'],
                'title' => $assignmentData['title'],
                'description' => $assignmentData['description'],
                'instructions' => $assignmentData['instructions'],
                'type' => $assignmentData['type'],
                'status' => $status,
                'assigned_date' => $assignedDate,
                'due_date' => $dueDate,
                'due_time' => $assignmentData['type'] === 'homework' ? '23:59:00' : '15:30:00',
                'max_marks' => $assignmentData['max_marks'],
                'attachments' => null,
                'allow_late_submission' => true,
                'grading_criteria' => $this->getGradingCriteria($assignmentData['type']),
                'is_active' => true,
                'created_at' => $assignedDate,
                'updated_at' => $assignedDate,
            ]);
        };

        // Create assignments for CIS Nursery B (Class ID: 66)
        echo "Creating assignments for CIS Nursery B (Class ID: 66)...\n";
        foreach ($nurseryAssignments as $assignmentData) {
            $assignment = $createAssignment($assignmentData, 66);
            echo "- Created: {$assignment->title} (ID: {$assignment->id})\n";

            // Create assignment submissions for published assignments
            if ($assignment->status === 'published') {
                $this->createAssignmentSubmissions($assignment);
            }
        }

        // Create assignments for CIS KG B (Class ID: 68)
        echo "\nCreating assignments for CIS KG B (Class ID: 68)...\n";
        foreach ($kgAssignments as $assignmentData) {
            $assignment = $createAssignment($assignmentData, 68);
            echo "- Created: {$assignment->title} (ID: {$assignment->id})\n";

            // Create assignment submissions for published assignments
            if ($assignment->status === 'published') {
                $this->createAssignmentSubmissions($assignment);
            }
        }

        echo "\n✅ Successfully created assignments for Sarah Johnson!\n";
        echo "📊 Summary:\n";
        echo "   - Teacher: Sarah Johnson (ID: 52)\n";
        echo "   - Classes: CIS Nursery B, CIS KG B\n";
        echo "   - Total Assignments: " . (count($nurseryAssignments) + count($kgAssignments)) . "\n";
        echo "   - Assignment Types: Homework, Classwork, Projects, Assessments\n";
        echo "   - Subjects: Early childhood curriculum appropriate\n";
    }

    /**
     * Get grading criteria based on assignment type
     */
    private function getGradingCriteria($type)
    {
        $criteria = [
            'homework' => 'Completion: 40%, Effort: 30%, Creativity: 30%',
            'classwork' => 'Participation: 50%, Understanding: 30%, Completion: 20%',
            'project' => 'Creativity: 40%, Effort: 30%, Presentation: 20%, Completion: 10%',
            'assessment' => 'Accuracy: 60%, Understanding: 25%, Neatness: 15%'
        ];

        return $criteria[$type] ?? 'Overall Performance: 100%';
    }

    /**
     * Create assignment submissions for all students in the class
     */
    private function createAssignmentSubmissions($assignment)
    {
        // Get all active students in the assignment's class
        $students = DB::table('class_student')
            ->join('students', 'class_student.student_id', '=', 'students.id')
            ->where('class_student.class_id', $assignment->class_id)
            ->where('class_student.is_active', true)
            ->where('students.is_active', true)
            ->select('students.id as student_id')
            ->get();

        foreach ($students as $student) {
            AssignmentSubmission::create([
                'assignment_id' => $assignment->id,
                'student_id' => $student->student_id,
                'status' => 'pending',
                'submitted_at' => null,
                'content' => null,
                'attachments' => null,
                'marks_obtained' => null,
                'teacher_feedback' => null,
                'graded_at' => null,
                'graded_by' => null,
                'is_late_submission' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        echo "  → Created submissions for {$students->count()} students\n";
    }
}
