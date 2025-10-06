<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'name' => 'Student Management',
                'slug' => 'student-management',
                'description' => 'Complete student lifecycle management including admission, profiles, and academic records',
                'monthly_price' => 25.00,
                'yearly_price' => 250.00,
                'features' => [
                    'Student Registration & Admission',
                    'Student Profiles & Documents',
                    'Academic Records Management',
                    'Parent-Student Linking',
                    'Bulk Student Operations'
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Class Management',
                'slug' => 'class-management',
                'description' => 'Organize students into classes, manage sections, and handle academic structure',
                'monthly_price' => 20.00,
                'yearly_price' => 200.00,
                'features' => [
                    'Class & Section Creation',
                    'Student-Class Assignment',
                    'Teacher-Class Assignment',
                    'Class Capacity Management',
                    'Academic Year Management'
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Attendance Management',
                'slug' => 'attendance',
                'description' => 'Track student attendance with detailed reporting and analytics',
                'monthly_price' => 30.00,
                'yearly_price' => 300.00,
                'features' => [
                    'Daily Attendance Marking',
                    'Bulk Attendance Operations',
                    'Attendance Reports & Analytics',
                    'Parent Notifications',
                    'Attendance Trends'
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Fee Management',
                'slug' => 'fee-management',
                'description' => 'Complete fee collection and financial management system',
                'monthly_price' => 35.00,
                'yearly_price' => 350.00,
                'features' => [
                    'Fee Structure Creation',
                    'Student Fee Assignment',
                    'Payment Tracking',
                    'Fee Reports & Analytics',
                    'Payment Reminders'
                ],
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Timetable Management',
                'slug' => 'timetable',
                'description' => 'Create and manage class schedules and teacher timetables',
                'monthly_price' => 25.00,
                'yearly_price' => 250.00,
                'features' => [
                    'Class Schedule Creation',
                    'Teacher Timetable Management',
                    'Conflict Detection',
                    'Substitution Management',
                    'Schedule Reports'
                ],
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Assignment Management',
                'slug' => 'assignment-management',
                'description' => 'Complete assignment and homework management system with grading',
                'monthly_price' => 35.00,
                'yearly_price' => 350.00,
                'features' => [
                    'Assignment Creation & Publishing',
                    'Student Submission Portal',
                    'Digital Grading & Feedback',
                    'Assignment Analytics',
                    'Late Submission Tracking',
                    'Performance Reports'
                ],
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Communication Hub',
                'slug' => 'communication',
                'description' => 'Centralized communication between school, teachers, and parents',
                'monthly_price' => 40.00,
                'yearly_price' => 400.00,
                'features' => [
                    'SMS & Email Notifications',
                    'Announcement Broadcasting',
                    'Parent-Teacher Messaging',
                    'Event Notifications',
                    'Emergency Alerts'
                ],
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Exam Management',
                'slug' => 'exam-management',
                'description' => 'Complete examination and assessment management system',
                'monthly_price' => 30.00,
                'yearly_price' => 300.00,
                'features' => [
                    'Exam Schedule Creation',
                    'Grade Management',
                    'Report Card Generation',
                    'Performance Analytics',
                    'Result Publishing'
                ],
                'is_active' => false, // Coming soon
                'sort_order' => 8,
            ],
            [
                'name' => 'Library Management',
                'slug' => 'library',
                'description' => 'Manage school library resources and book circulation',
                'monthly_price' => 20.00,
                'yearly_price' => 200.00,
                'features' => [
                    'Book Catalog Management',
                    'Book Issue/Return',
                    'Fine Management',
                    'Reading Reports',
                    'Inventory Tracking'
                ],
                'is_active' => false, // Coming soon
                'sort_order' => 9,
            ],
            [
                'name' => 'Transport Management',
                'slug' => 'transport',
                'description' => 'School bus and transportation management system',
                'monthly_price' => 25.00,
                'yearly_price' => 250.00,
                'features' => [
                    'Route Management',
                    'Bus Tracking',
                    'Driver Management',
                    'Student-Bus Assignment',
                    'GPS Integration'
                ],
                'is_active' => false, // Coming soon
                'sort_order' => 10,
            ],
            [
                'name' => 'Analytics & Reports',
                'slug' => 'analytics',
                'description' => 'Advanced analytics and custom reporting for data-driven decisions',
                'monthly_price' => 50.00,
                'yearly_price' => 500.00,
                'features' => [
                    'Advanced Analytics Dashboard',
                    'Custom Report Builder',
                    'Data Export & Import',
                    'Performance Insights',
                    'Predictive Analytics'
                ],
                'is_active' => true,
                'sort_order' => 11,
            ],
            [
                'name' => 'Gallery Management',
                'slug' => 'gallery-management',
                'description' => 'School photo and video gallery for events and class activities',
                'monthly_price' => 20.00,
                'yearly_price' => 200.00,
                'features' => [
                    'Photo & Video Upload',
                    'Event-based Albums',
                    'Class-specific Galleries',
                    'Bulk Media Upload',
                    'Media Organization & Tagging',
                    'Download & Sharing',
                    'Storage Management'
                ],
                'is_active' => true,
                'sort_order' => 12,
            ],
            [
                'name' => 'Camera Monitoring',
                'slug' => 'camera-monitoring',
                'description' => 'Live camera monitoring system for enhanced school security and parent transparency',
                'monthly_price' => 45.00,
                'yearly_price' => 450.00,
                'features' => [
                    'Live Camera Streaming',
                    'Multi-Location Coverage',
                    'Parent Access Control',
                    'Privacy Level Management',
                    'Time-based Scheduling',
                    'Access Logging & Analytics',
                    'WebRTC Integration',
                    'Mobile App Support',
                    'Secure Token Authentication',
                    'Permission Management System'
                ],
                'is_active' => true,
                'sort_order' => 13,
            ],
            [
                'name' => 'Student Promotion System',
                'slug' => 'promotion-system',
                'description' => 'Advanced academic year management and student promotion system',
                'monthly_price' => 30.00,
                'yearly_price' => 300.00,
                'features' => [
                    'Academic Year Management',
                    'Promotion Criteria Configuration',
                    'Automated Student Evaluation',
                    'Bulk Promotion Processing',
                    'Performance-based Promotion',
                    'Manual Override Controls',
                    'Promotion Statistics & Reports',
                    'Parent Notification System',
                    'Remedial Tracking',
                    'Class Progression Management'
                ],
                'is_active' => true,
                'sort_order' => 14,
            ],
        ];

        foreach ($modules as $module) {
            Module::firstOrCreate(
                ['slug' => $module['slug']],
                $module
            );
        }
    }
}
