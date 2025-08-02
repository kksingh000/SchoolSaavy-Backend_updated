<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\ClassRoom;
use App\Models\Subject;
use App\Models\Parents;
use App\Models\Attendance;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CambridgeSchoolSeeder extends Seeder
{
    private $schoolId = 7; // Cambridge International School
    private $createdTeachers = [];
    private $createdStudents = [];
    private $createdClasses = [];
    private $createdSubjects = [];
    private $createdParents = [];

    public function run()
    {
        DB::beginTransaction();
        
        try {
            $this->command->info('🏫 Starting Cambridge International School data seeding...');
            
            // Create teachers first
            $this->createTeachers();
            
            // Create subjects
            $this->createSubjects();
            
            // Create classes with teachers
            $this->createClasses();
            
            // Create students and parents
            $this->createStudentsAndParents();
            
            // Assign students to classes
            $this->assignStudentsToClasses();
            
            // Create class schedules (timetables)
            $this->createClassSchedules();
            
            // Create some attendance records for the last few days
            $this->createAttendanceRecords();
            
            DB::commit();
            
            $this->command->info('✅ Cambridge International School data seeded successfully!');
            $this->printSummary();
            
        } catch (\Exception $e) {
            DB::rollback();
            $this->command->error('❌ Error seeding data: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createTeachers()
    {
        $this->command->info('👨‍🏫 Creating teachers...');
        
        $teachers = [
            // Early Years Teachers (Nursery to KG)
            [
                'name' => 'Ms. Sarah Johnson',
                'email' => 'sarah.johnson@cambridge.edu',
                'specializations' => ['Early Childhood Education', 'Play-based Learning'],
                'qualification' => 'B.Ed in Early Childhood, M.A in Child Development',
                'phone' => '+1234567801',
                'level' => 'early_years',
                'gender' => 'female',
                'date_of_birth' => '1990-05-15'
            ],
            [
                'name' => 'Ms. Emily Davis',
                'email' => 'emily.davis@cambridge.edu',
                'specializations' => ['Montessori Education', 'Child Psychology'],
                'qualification' => 'Montessori Diploma, B.Ed',
                'phone' => '+1234567802',
                'level' => 'early_years',
                'gender' => 'female',
                'date_of_birth' => '1992-08-22'
            ],
            
            // Primary Teachers (Grade 1-5)
            [
                'name' => 'Mr. Michael Brown',
                'email' => 'michael.brown@cambridge.edu',
                'specializations' => ['Mathematics', 'Science', 'STEM Education'],
                'qualification' => 'M.Sc Mathematics, B.Ed',
                'phone' => '+1234567803',
                'level' => 'primary',
                'gender' => 'male',
                'date_of_birth' => '1985-03-10'
            ],
            [
                'name' => 'Ms. Lisa Wilson',
                'email' => 'lisa.wilson@cambridge.edu',
                'specializations' => ['English', 'Literature', 'Creative Writing'],
                'qualification' => 'M.A English Literature, B.Ed',
                'phone' => '+1234567804',
                'level' => 'primary',
                'gender' => 'female',
                'date_of_birth' => '1988-11-05'
            ],
            [
                'name' => 'Ms. Anna Martinez',
                'email' => 'anna.martinez@cambridge.edu',
                'specializations' => ['Social Studies', 'Arts', 'History'],
                'qualification' => 'M.A History, B.Ed, Art Certification',
                'phone' => '+1234567805',
                'level' => 'primary',
                'gender' => 'female',
                'date_of_birth' => '1990-01-18'
            ],
            
            // Secondary Teachers (Grade 6-12)
            [
                'name' => 'Dr. James Anderson',
                'email' => 'james.anderson@cambridge.edu',
                'specializations' => ['Physics', 'Chemistry', 'Advanced Sciences'],
                'qualification' => 'Ph.D Physics, M.Sc Chemistry, B.Ed',
                'phone' => '+1234567806',
                'level' => 'secondary',
                'gender' => 'male',
                'date_of_birth' => '1980-07-12'
            ],
            [
                'name' => 'Ms. Jennifer Taylor',
                'email' => 'jennifer.taylor@cambridge.edu',
                'specializations' => ['Biology', 'Environmental Science', 'Life Sciences'],
                'qualification' => 'M.Sc Biology, Environmental Science Certification, B.Ed',
                'phone' => '+1234567807',
                'level' => 'secondary',
                'gender' => 'female',
                'date_of_birth' => '1987-04-25'
            ],
            [
                'name' => 'Mr. David Clark',
                'email' => 'david.clark@cambridge.edu',
                'specializations' => ['Computer Science', 'Mathematics', 'Programming'],
                'qualification' => 'M.Tech Computer Science, B.Ed',
                'phone' => '+1234567808',
                'level' => 'secondary',
                'gender' => 'male',
                'date_of_birth' => '1983-09-30'
            ],
            [
                'name' => 'Ms. Rachel Green',
                'email' => 'rachel.green@cambridge.edu',
                'specializations' => ['French', 'Spanish', 'Modern Languages'],
                'qualification' => 'M.A Modern Languages, DELE Certificate, B.Ed',
                'phone' => '+1234567809',
                'level' => 'secondary',
                'gender' => 'female',
                'date_of_birth' => '1989-12-08'
            ]
        ];

        foreach ($teachers as $index => $teacherData) {
            // Create user account
            $user = User::create([
                'name' => $teacherData['name'],
                'email' => $teacherData['email'],
                'password' => Hash::make('password123'),
                'user_type' => 'teacher',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            // Generate unique employee ID
            $existingTeachersCount = Teacher::where('school_id', $this->schoolId)->count();
            $employeeId = 'CIS' . str_pad($existingTeachersCount + $index + 1, 4, '0', STR_PAD_LEFT);

            // Create teacher profile
            $teacher = Teacher::create([
                'school_id' => $this->schoolId,
                'user_id' => $user->id,
                'employee_id' => $employeeId,
                'specializations' => $teacherData['specializations'],
                'qualification' => $teacherData['qualification'],
                'joining_date' => Carbon::now()->subYears(rand(2, 10))->addMonths(rand(1, 11)),
                'phone' => $teacherData['phone'],
                'gender' => $teacherData['gender'],
                'date_of_birth' => Carbon::parse($teacherData['date_of_birth']),
                'address' => $this->generateAddress(),
            ]);

            $this->createdTeachers[$teacherData['level']][] = $teacher;
        }
        
        $this->command->info('✅ Created ' . count($teachers) . ' teachers');
    }

    private function createSubjects()
    {
        $this->command->info('📚 Creating subjects...');
        
        $subjects = [
            // Early Years Subjects
            ['name' => 'Play & Learn', 'code' => 'CIS_PL001', 'grade_level' => 'nursery'],
            ['name' => 'Creative Arts', 'code' => 'CIS_CA001', 'grade_level' => 'nursery'],
            ['name' => 'Number Fun', 'code' => 'CIS_NF001', 'grade_level' => 'kg'],
            ['name' => 'Letter & Words', 'code' => 'CIS_LW001', 'grade_level' => 'kg'],
            ['name' => 'Creative Play', 'code' => 'CIS_CP001', 'grade_level' => 'kg'],
            
            // Primary Subjects
            ['name' => 'Mathematics', 'code' => 'CIS_MATH', 'grade_level' => 'primary'],
            ['name' => 'English', 'code' => 'CIS_ENG', 'grade_level' => 'primary'],
            ['name' => 'Science', 'code' => 'CIS_SCI', 'grade_level' => 'primary'],
            ['name' => 'Social Studies', 'code' => 'CIS_SS', 'grade_level' => 'primary'],
            ['name' => 'Art & Craft', 'code' => 'CIS_ART', 'grade_level' => 'primary'],
            ['name' => 'Physical Education', 'code' => 'CIS_PE', 'grade_level' => 'primary'],
            
            // Secondary Subjects
            ['name' => 'Advanced Mathematics', 'code' => 'CIS_AMATH', 'grade_level' => 'secondary'],
            ['name' => 'Physics', 'code' => 'CIS_PHY', 'grade_level' => 'secondary'],
            ['name' => 'Chemistry', 'code' => 'CIS_CHEM', 'grade_level' => 'secondary'],
            ['name' => 'Biology', 'code' => 'CIS_BIO', 'grade_level' => 'secondary'],
            ['name' => 'Computer Science', 'code' => 'CIS_CS', 'grade_level' => 'secondary'],
            ['name' => 'Literature', 'code' => 'CIS_LIT', 'grade_level' => 'secondary'],
            ['name' => 'History', 'code' => 'CIS_HIST', 'grade_level' => 'secondary'],
            ['name' => 'Geography', 'code' => 'CIS_GEO', 'grade_level' => 'secondary'],
            ['name' => 'French', 'code' => 'CIS_FR', 'grade_level' => 'secondary'],
            ['name' => 'Spanish', 'code' => 'CIS_ES', 'grade_level' => 'secondary'],
        ];

        foreach ($subjects as $subjectData) {
            $subject = Subject::create([
                'school_id' => $this->schoolId,
                'name' => $subjectData['name'],
                'code' => $subjectData['code'],
                'description' => 'Subject for ' . $subjectData['grade_level'] . ' level students',
                'is_active' => true,
            ]);

            $this->createdSubjects[$subjectData['grade_level']][] = $subject;
        }
        
        $this->command->info('✅ Created ' . count($subjects) . ' subjects');
    }

    private function createClasses()
    {
        $this->command->info('🏛️ Creating classes...');
        
        $classes = [
            // Early Years Classes
            ['name' => 'CIS Nursery A', 'grade_level' => 1, 'section' => 'A', 'capacity' => 15, 'teacher_level' => 'early_years'],
            ['name' => 'CIS Nursery B', 'grade_level' => 1, 'section' => 'B', 'capacity' => 15, 'teacher_level' => 'early_years'],
            ['name' => 'CIS KG A', 'grade_level' => 2, 'section' => 'A', 'capacity' => 20, 'teacher_level' => 'early_years'],
            ['name' => 'CIS KG B', 'grade_level' => 2, 'section' => 'B', 'capacity' => 20, 'teacher_level' => 'early_years'],
            
            // Primary Classes
            ['name' => 'CIS Grade 1 A', 'grade_level' => 3, 'section' => 'A', 'capacity' => 25, 'teacher_level' => 'primary'],
            ['name' => 'CIS Grade 1 B', 'grade_level' => 3, 'section' => 'B', 'capacity' => 25, 'teacher_level' => 'primary'],
            ['name' => 'CIS Grade 2 A', 'grade_level' => 4, 'section' => 'A', 'capacity' => 25, 'teacher_level' => 'primary'],
            ['name' => 'CIS Grade 2 B', 'grade_level' => 4, 'section' => 'B', 'capacity' => 25, 'teacher_level' => 'primary'],
            ['name' => 'CIS Grade 3 A', 'grade_level' => 5, 'section' => 'A', 'capacity' => 30, 'teacher_level' => 'primary'],
            ['name' => 'CIS Grade 4 A', 'grade_level' => 6, 'section' => 'A', 'capacity' => 30, 'teacher_level' => 'primary'],
            ['name' => 'CIS Grade 5 A', 'grade_level' => 7, 'section' => 'A', 'capacity' => 30, 'teacher_level' => 'primary'],
            
            // Secondary Classes
            ['name' => 'CIS Grade 6 A', 'grade_level' => 8, 'section' => 'A', 'capacity' => 35, 'teacher_level' => 'secondary'],
            ['name' => 'CIS Grade 7 A', 'grade_level' => 9, 'section' => 'A', 'capacity' => 35, 'teacher_level' => 'secondary'],
            ['name' => 'CIS Grade 8 A', 'grade_level' => 10, 'section' => 'A', 'capacity' => 35, 'teacher_level' => 'secondary'],
            ['name' => 'CIS Grade 9 A', 'grade_level' => 11, 'section' => 'A', 'capacity' => 30, 'teacher_level' => 'secondary'],
            ['name' => 'CIS Grade 10 A', 'grade_level' => 12, 'section' => 'A', 'capacity' => 30, 'teacher_level' => 'secondary'],
        ];

        foreach ($classes as $index => $classData) {
            // Assign class teacher based on level
            $teacherLevel = $classData['teacher_level'];
            $availableTeachers = $this->createdTeachers[$teacherLevel] ?? [];
            $classTeacher = $availableTeachers[array_rand($availableTeachers)] ?? null;

            $class = ClassRoom::create([
                'school_id' => $this->schoolId,
                'name' => $classData['name'],
                'grade_level' => $classData['grade_level'],
                'section' => $classData['section'],
                'capacity' => $classData['capacity'],
                'class_teacher_id' => $classTeacher ? $classTeacher->id : null,
                'description' => 'Class for ' . $classData['name'] . ' students',
            ]);

            $this->createdClasses[] = $class;
        }
        
        $this->command->info('✅ Created ' . count($classes) . ' classes');
    }

    private function createStudentsAndParents()
    {
        $this->command->info('👶 Creating students and parents...');
        
        $firstNames = [
            'boys' => ['Liam', 'Noah', 'Oliver', 'Elijah', 'William', 'James', 'Benjamin', 'Lucas', 'Henry', 'Alexander', 'Mason', 'Michael', 'Ethan', 'Daniel', 'Jacob', 'Logan', 'Jackson', 'Sebastian', 'Jack', 'Owen'],
            'girls' => ['Emma', 'Olivia', 'Ava', 'Isabella', 'Sophia', 'Charlotte', 'Mia', 'Amelia', 'Harper', 'Evelyn', 'Abigail', 'Emily', 'Elizabeth', 'Sofia', 'Madison', 'Avery', 'Ella', 'Scarlett', 'Grace', 'Chloe']
        ];
        
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin'];
        
        $admissionNumber = 2001;
        
        foreach ($this->createdClasses as $class) {
            $studentsToCreate = $class->capacity - 5; // Leave some spots for new admissions
            
            for ($i = 1; $i <= $studentsToCreate; $i++) {
                $gender = rand(0, 1) ? 'male' : 'female';
                $firstName = $firstNames[$gender === 'male' ? 'boys' : 'girls'][array_rand($firstNames[$gender === 'male' ? 'boys' : 'girls'])];
                $lastName = $lastNames[array_rand($lastNames)];
                
                // Create student
                $student = Student::create([
                    'school_id' => $this->schoolId,
                    'admission_number' => 'CIS' . $admissionNumber++,
                    'roll_number' => str_pad($i, 2, '0', STR_PAD_LEFT),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'date_of_birth' => $this->generateDateOfBirth($class->grade_level),
                    'gender' => $gender,
                    'admission_date' => Carbon::now()->subMonths(rand(1, 36)),
                    'blood_group' => $this->getRandomBloodGroup(),
                    'address' => $this->generateAddress(),
                    'phone' => '+1234567' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT),
                    'is_active' => true,
                ]);

                $this->createdStudents[] = $student;
                
                // Create parents for this student
                $this->createParentsForStudent($student, $firstName, $lastName);
            }
        }
        
        $this->command->info('✅ Created ' . count($this->createdStudents) . ' students with parents');
    }

    private function createParentsForStudent($student, $firstName, $lastName)
    {
        $parentFirstNames = [
            'male' => ['David', 'Michael', 'John', 'Robert', 'William', 'James', 'Christopher', 'Joseph', 'Thomas', 'Charles'],
            'female' => ['Mary', 'Patricia', 'Jennifer', 'Linda', 'Elizabeth', 'Barbara', 'Susan', 'Jessica', 'Sarah', 'Karen']
        ];

        // Create father
        $fatherUser = User::create([
            'name' => $parentFirstNames['male'][array_rand($parentFirstNames['male'])] . ' ' . $lastName,
            'email' => strtolower($lastName) . '.father.' . $student->id . '@cambridge.edu',
            'password' => Hash::make('password123'),
            'user_type' => 'parent',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $father = Parents::create([
            'user_id' => $fatherUser->id,
            'phone' => '+1234567' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT),
            'gender' => 'male',
            'occupation' => $this->getRandomOccupation(),
            'address' => $student->address,
            'relationship' => 'father',
        ]);

        // Create mother
        $motherUser = User::create([
            'name' => $parentFirstNames['female'][array_rand($parentFirstNames['female'])] . ' ' . $lastName,
            'email' => strtolower($lastName) . '.mother.' . $student->id . '@cambridge.edu',
            'password' => Hash::make('password123'),
            'user_type' => 'parent',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $mother = Parents::create([
            'user_id' => $motherUser->id,
            'phone' => '+1234567' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT),
            'gender' => 'female',
            'occupation' => $this->getRandomOccupation(),
            'address' => $student->address,
            'relationship' => 'mother',
        ]);

        // Link parents to student
        DB::table('parent_student')->insert([
            [
                'parent_id' => $father->id,
                'student_id' => $student->id,
                'relationship' => 'father',
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'parent_id' => $mother->id,
                'student_id' => $student->id,
                'relationship' => 'mother',
                'is_primary' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        $this->createdParents[] = $father;
        $this->createdParents[] = $mother;
    }

    private function assignStudentsToClasses()
    {
        $this->command->info('🎒 Assigning students to classes...');
        
        $studentIndex = 0;
        
        foreach ($this->createdClasses as $class) {
            $studentsInClass = $class->capacity - 5;
            
            for ($i = 0; $i < $studentsInClass; $i++) {
                if ($studentIndex < count($this->createdStudents)) {
                    DB::table('class_student')->insert([
                        'class_id' => $class->id,
                        'student_id' => $this->createdStudents[$studentIndex]->id,
                        'roll_number' => str_pad($i + 1, 2, '0', STR_PAD_LEFT),
                        'enrolled_date' => $this->createdStudents[$studentIndex]->admission_date,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $studentIndex++;
                }
            }
        }
        
        $this->command->info('✅ Assigned students to classes');
    }

    private function createClassSchedules()
    {
        $this->command->info('📅 Creating class schedules...');
        
        $timeSlots = [
            ['08:00:00', '08:45:00'],
            ['08:45:00', '09:30:00'],
            ['09:45:00', '10:30:00'],
            ['10:30:00', '11:15:00'],
            ['11:30:00', '12:15:00'],
            ['12:15:00', '13:00:00'],
        ];
        
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        
        foreach ($this->createdClasses as $class) {
            // Determine subjects based on grade level
            $subjects = $this->getSubjectsForClass($class);
            $teachers = $this->getTeachersForClass($class);
            
            foreach ($daysOfWeek as $day) {
                foreach ($timeSlots as $index => $slot) {
                    if ($index < count($subjects)) {
                        $subject = $subjects[$index % count($subjects)];
                        $teacher = $teachers[array_rand($teachers)];
                        
                        DB::table('class_schedules')->insert([
                            'school_id' => $this->schoolId,
                            'class_id' => $class->id,
                            'subject_id' => $subject->id,
                            'teacher_id' => $teacher->id,
                            'day_of_week' => $day,
                            'start_time' => $slot[0],
                            'end_time' => $slot[1],
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
        
        $this->command->info('✅ Created class schedules');
    }

    private function createAttendanceRecords()
    {
        $this->command->info('📊 Creating attendance records for the last 5 days...');
        
        $dates = [];
        for ($i = 4; $i >= 0; $i--) {
            $dates[] = Carbon::now()->subDays($i);
        }
        
        foreach ($dates as $date) {
            if ($date->isWeekend()) continue; // Skip weekends
            
            foreach ($this->createdClasses as $class) {
                // Get students in this class
                $studentIds = DB::table('class_student')
                    ->where('class_id', $class->id)
                    ->where('is_active', true)
                    ->pluck('student_id');
                
                foreach ($studentIds as $studentId) {
                    // 90% attendance rate
                    $isPresent = rand(1, 100) <= 90;
                    $status = $isPresent ? 'present' : (rand(1, 100) <= 10 ? 'late' : 'absent');
                    
                    Attendance::create([
                        'school_id' => $this->schoolId,
                        'class_id' => $class->id,
                        'student_id' => $studentId,
                        'date' => $date->toDateString(),
                        'status' => $status,
                        'check_in_time' => $status === 'present' ? '08:00:00' : ($status === 'late' ? '08:15:00' : null),
                        'check_out_time' => $status !== 'absent' ? '15:00:00' : null,
                        'marked_by' => $class->class_teacher_id ? User::where('user_type', 'teacher')->first()->id : 1,
                    ]);
                }
            }
        }
        
        $this->command->info('✅ Created attendance records');
    }

    // Helper methods
    private function generateDateOfBirth($gradeLevel)
    {
        $baseAge = 3 + $gradeLevel; // Nursery starts around age 3-4
        return Carbon::now()->subYears($baseAge)->subMonths(rand(0, 11))->subDays(rand(0, 30));
    }

    private function getRandomBloodGroup()
    {
        $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        return $bloodGroups[array_rand($bloodGroups)];
    }

    private function generateAddress()
    {
        $streets = ['Main Street', 'Oak Avenue', 'Pine Road', 'Maple Drive', 'Cedar Lane', 'Elm Street', 'Park Avenue', 'Garden Road'];
        $areas = ['Downtown', 'Riverside', 'Hillside', 'Parkview', 'Greenwood', 'Lakeside', 'Sunset', 'Northfield'];
        
        return rand(1, 999) . ' ' . $streets[array_rand($streets)] . ', ' . $areas[array_rand($areas)] . ', City';
    }

    private function getRandomOccupation()
    {
        $occupations = ['Engineer', 'Doctor', 'Teacher', 'Lawyer', 'Accountant', 'Manager', 'Sales Executive', 'Consultant', 'Architect', 'Nurse', 'Designer', 'Analyst'];
        return $occupations[array_rand($occupations)];
    }

    private function getSubjectsForClass($class)
    {
        if ($class->grade_level <= 2) {
            return $this->createdSubjects['nursery'] ?? [];
        } elseif ($class->grade_level <= 7) {
            return $this->createdSubjects['primary'] ?? [];
        } else {
            return $this->createdSubjects['secondary'] ?? [];
        }
    }

    private function getTeachersForClass($class)
    {
        if ($class->grade_level <= 2) {
            return $this->createdTeachers['early_years'] ?? [];
        } elseif ($class->grade_level <= 7) {
            return $this->createdTeachers['primary'] ?? [];
        } else {
            return $this->createdTeachers['secondary'] ?? [];
        }
    }

    private function printSummary()
    {
        $totalTeachers = array_sum(array_map('count', $this->createdTeachers));
        $totalSubjects = array_sum(array_map('count', $this->createdSubjects));
        
        $this->command->info("\n📊 SEEDING SUMMARY:");
        $this->command->info("🏫 School: Cambridge International School (ID: {$this->schoolId})");
        $this->command->info("👨‍🏫 Teachers: {$totalTeachers}");
        $this->command->info("🏛️ Classes: " . count($this->createdClasses));
        $this->command->info("👶 Students: " . count($this->createdStudents));
        $this->command->info("👨‍👩‍👧‍👦 Parents: " . count($this->createdParents));
        $this->command->info("📚 Subjects: {$totalSubjects}");
        $this->command->info("📅 Class schedules created for all classes");
        $this->command->info("📊 Attendance records for last 5 working days");
        $this->command->info("\n🔑 TEST CREDENTIALS:");
        $this->command->info("Teacher Login: sarah.johnson@cambridge.edu / password123");
        $this->command->info("Teacher Login: michael.brown@cambridge.edu / password123");
        $this->command->info("All teacher emails follow: firstname.lastname@cambridge.edu");
    }
}
