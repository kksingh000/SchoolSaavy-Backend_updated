<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\School;
use App\Models\SchoolAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SchoolAdminSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::first();

        // First check if admin exists
        $existingUser = User::where('email', 'admin@school.com')->first();
        
        if (!$existingUser) {
            $user = User::create([
                'name' => 'John Smith',
                'email' => 'admin@school.com',
                'password' => Hash::make('password123'),
                'user_type' => 'admin',
                'is_active' => true,
            ]);

            SchoolAdmin::create([
                'user_id' => $user->id,
                'school_id' => $school->id,
                'phone' => '9876543210',
                'permissions' => json_encode([
                    'manage_staff' => true,
                    'manage_students' => true,
                    'manage_finances' => true,
                    'manage_settings' => true,
                ]),
            ]);
        }
    }
} 