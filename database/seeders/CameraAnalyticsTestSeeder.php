<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SchoolCamera;
use App\Models\CameraAccessLog;
use App\Models\CameraPermission;
use App\Models\User;
use Carbon\Carbon;

class CameraAnalyticsTestSeeder extends Seeder
{
    /**
     * Run the database seeds for camera analytics testing
     */
    public function run(): void
    {
        // Get existing cameras and users
        $cameras = SchoolCamera::where('school_id', 1)->get();
        $parents = User::take(3)->get(); // Just get any users for testing
        
        if ($cameras->isEmpty()) {
            $this->command->info('No cameras found. Please create cameras first.');
            return;
        }
        
        if ($parents->isEmpty()) {
            $this->command->info('No users found. Please create users first.');
            return;
        }

        $this->command->info('Creating camera analytics test data...');

        // Create camera permissions
        foreach ($cameras as $camera) {
            foreach ($parents as $index => $parent) {
                CameraPermission::create([
                    'school_id' => 1,
                    'camera_id' => $camera->id,
                    'parent_id' => $parent->id,
                    'student_id' => 1, // Using student ID 1 for testing
                    'access_granted' => true,
                    'request_status' => $index % 3 === 0 ? 'approved' : ($index % 3 === 1 ? 'pending' : 'rejected'),
                    'permission_type' => 'permanent',
                    'justification' => 'Test permission for analytics',
                    'approved_by' => 1,
                    'approved_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }

        // Create camera access logs for the past 30 days
        foreach ($cameras as $camera) {
            foreach ($parents->take(2) as $parent) {
                // Create multiple access sessions
                for ($i = 0; $i < rand(3, 8); $i++) {
                    $startTime = Carbon::now()->subDays(rand(0, 30))->addHours(rand(8, 18));
                    $sessionDuration = rand(300, 3600); // 5 minutes to 1 hour
                    $endTime = $startTime->copy()->addSeconds($sessionDuration);

                    CameraAccessLog::create([
                        'camera_id' => $camera->id,
                        'parent_id' => $parent->id,
                        'student_id' => 1, // Using student ID 1 for testing
                        'access_start_time' => $startTime,
                        'access_end_time' => $endTime,
                        'duration_seconds' => $sessionDuration,
                        'ip_address' => '192.168.1.' . rand(100, 200),
                        'user_agent' => 'Mozilla/5.0 (Test Browser)',
                        'device_type' => rand(0, 1) ? 'mobile' : 'desktop',
                        'access_result' => 'success',
                    ]);
                }
            }
        }

        $this->command->info('Camera analytics test data created successfully!');
        $this->command->info('- Camera Permissions: ' . CameraPermission::count());
        $this->command->info('- Camera Access Logs: ' . CameraAccessLog::count());
    }
}