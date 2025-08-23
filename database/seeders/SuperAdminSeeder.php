<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Check if super admin already exists
        $existingUser = User::where('email', 'superadmin@schoolsavvy.com')->first();

        if (!$existingUser) {
            $user = User::create([
                'name' => 'Super Administrator',
                'email' => 'superadmin@schoolsavvy.com',
                'password' => Hash::make('SuperAdmin@123'),
                'user_type' => 'super_admin',
                'is_active' => true,
            ]);

            SuperAdmin::create([
                'user_id' => $user->id,
                'phone' => '+1-555-0000',
                'permissions' => json_encode([
                    'manage_schools' => true,
                    'view_analytics' => true,
                    'manage_platform' => true,
                    'system_settings' => true,
                ]),
            ]);

            $this->command->info('Super Admin created successfully!');
            $this->command->info('Email: superadmin@schoolsavvy.com');
            $this->command->info('Password: SuperAdmin@123');
        } else {
            $this->command->info('Super Admin already exists.');
        }
    }
}
