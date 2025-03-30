<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\School;
use App\Models\Student;
use App\Models\Parents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ParentStudentSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::first();
        
        // Create 5 parents with 1-3 children each
        for ($i = 1; $i <= 5; $i++) {
            // Create parent user
            $user = User::create([
                'name' => fake()->name,
                'email' => "parent{$i}@example.com",
                'password' => Hash::make('password123'),
                'user_type' => 'parent',
                'is_active' => true,
            ]);

            // Create parent profile
            $parent = Parents::create([
                'user_id' => $user->id,
                'phone' => '9' . rand(100000000, 999999999),
                'alternate_phone' => '9' . rand(100000000, 999999999),
                'gender' => fake()->randomElement(['male', 'female']),
                'occupation' => fake()->jobTitle,
                'address' => fake()->address,
                'relationship' => fake()->randomElement(['father', 'mother']),
            ]);

            // Create 1-3 children for each parent
            $numberOfChildren = rand(1, 3);
            
            for ($j = 1; $j <= $numberOfChildren; $j++) {
                $student = Student::create([
                    'school_id' => $school->id,
                    'admission_number' => 'ADM' . str_pad($parent->id . $j, 3, '0', STR_PAD_LEFT),
                    'roll_number' => 'ROLL' . str_pad($parent->id . $j, 3, '0', STR_PAD_LEFT),
                    'first_name' => fake()->firstName,
                    'last_name' => fake()->lastName,
                    'date_of_birth' => fake()->date('Y-m-d', '-10 years'),
                    'gender' => fake()->randomElement(['male', 'female']),
                    'admission_date' => fake()->date('Y-m-d', '-2 years'),
                    'blood_group' => fake()->randomElement(['A+', 'B+', 'O+', 'AB+']),
                    'address' => fake()->address,
                    'phone' => '9' . rand(100000000, 999999999),
                    'is_active' => true,
                ]);

                // Create parent-student relationship using the model relationship
                $parent->students()->attach($student->id, [
                    'relationship' => $parent->relationship,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
} 