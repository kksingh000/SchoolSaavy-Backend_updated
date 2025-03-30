<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\School;
use App\Models\Teacher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::first();
        
        $teachers = [
            [
                'name' => 'Sarah Johnson',
                'email' => 'sarah@school.com',
                'subject' => 'Mathematics',
                'qualification' => 'M.Sc. Mathematics',
            ],
            [
                'name' => 'Michael Brown',
                'email' => 'michael@school.com',
                'subject' => 'Science',
                'qualification' => 'M.Sc. Physics',
            ],
            [
                'name' => 'Emily Davis',
                'email' => 'emily@school.com',
                'subject' => 'English',
                'qualification' => 'M.A. English Literature',
            ],
        ];

        foreach ($teachers as $teacherData) {
            $user = User::create([
                'name' => $teacherData['name'],
                'email' => $teacherData['email'],
                'password' => Hash::make('password123'),
                'user_type' => 'teacher',
                'is_active' => true,
            ]);

            Teacher::create([
                'user_id' => $user->id,
                'school_id' => $school->id,
                'employee_id' => 'EMP' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
                'phone' => '9' . rand(100000000, 999999999),
                'date_of_birth' => fake()->date('Y-m-d', '-30 years'),
                'joining_date' => fake()->date('Y-m-d', '-2 years'),
                'gender' => fake()->randomElement(['male', 'female']),
                'qualification' => $teacherData['qualification'],
                'address' => fake()->address,
                'specializations' => json_encode([$teacherData['subject']]),
            ]);
        }
    }
} 