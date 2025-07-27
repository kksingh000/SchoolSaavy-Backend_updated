<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;
use App\Models\School;
use App\Models\User;
use Carbon\Carbon;

class EventSeeder extends Seeder
{
    public function run()
    {
        $school = School::first();
        $admin = User::where('user_type', 'admin')->first();

        if (!$school || !$admin) {
            $this->command->info('Please ensure you have at least one school and admin user before running this seeder.');
            return;
        }

        $events = [
            [
                'school_id' => $school->id,
                'created_by' => $admin->id,
                'title' => 'Welcome Assembly',
                'description' => 'Welcome assembly for new academic year. All students and parents are invited.',
                'type' => 'announcement',
                'priority' => 'high',
                'event_date' => Carbon::today()->addDays(3),
                'start_time' => '09:00',
                'end_time' => '10:30',
                'location' => 'Main Auditorium',
                'target_audience' => ['all'],
                'affected_classes' => null,
                'is_recurring' => false,
                'requires_acknowledgment' => true,
                'is_published' => true,
                'published_at' => now(),
            ],
            [
                'school_id' => $school->id,
                'created_by' => $admin->id,
                'title' => 'Parent-Teacher Meeting',
                'description' => 'Monthly parent-teacher meeting to discuss student progress.',
                'type' => 'meeting',
                'priority' => 'medium',
                'event_date' => Carbon::today()->addDays(7),
                'start_time' => '14:00',
                'end_time' => '17:00',
                'location' => 'Classrooms',
                'target_audience' => ['parents', 'teachers'],
                'affected_classes' => [1], // Nursery A
                'is_recurring' => true,
                'recurrence_type' => 'monthly',
                'recurrence_end_date' => Carbon::today()->addYear(),
                'requires_acknowledgment' => true,
                'is_published' => true,
                'published_at' => now(),
            ],
            [
                'school_id' => $school->id,
                'created_by' => $admin->id,
                'title' => 'Independence Day Holiday',
                'description' => 'School will remain closed for Independence Day celebration.',
                'type' => 'holiday',
                'priority' => 'medium',
                'event_date' => Carbon::create(2025, 8, 15),
                'start_time' => null,
                'end_time' => null,
                'location' => null,
                'target_audience' => ['all'],
                'affected_classes' => null,
                'is_recurring' => true,
                'recurrence_type' => 'yearly',
                'recurrence_end_date' => Carbon::create(2030, 8, 15),
                'requires_acknowledgment' => false,
                'is_published' => true,
                'published_at' => now(),
            ],
            [
                'school_id' => $school->id,
                'created_by' => $admin->id,
                'title' => 'Mathematics Quiz Competition',
                'description' => 'Inter-class mathematics quiz competition for nursery students.',
                'type' => 'academic',
                'priority' => 'medium',
                'event_date' => Carbon::today()->addDays(10),
                'start_time' => '10:00',
                'end_time' => '12:00',
                'location' => 'Activity Room',
                'target_audience' => ['students', 'teachers'],
                'affected_classes' => [1],
                'is_recurring' => false,
                'requires_acknowledgment' => false,
                'is_published' => true,
                'published_at' => now(),
            ],
            [
                'school_id' => $school->id,
                'created_by' => $admin->id,
                'title' => 'Emergency Drill Practice',
                'description' => 'Fire safety and emergency evacuation drill for all students and staff.',
                'type' => 'emergency',
                'priority' => 'urgent',
                'event_date' => Carbon::today()->addDays(2),
                'start_time' => '11:00',
                'end_time' => '11:30',
                'location' => 'Entire School Premises',
                'target_audience' => ['all'],
                'affected_classes' => null,
                'is_recurring' => true,
                'recurrence_type' => 'monthly',
                'recurrence_end_date' => Carbon::today()->addYear(),
                'requires_acknowledgment' => true,
                'is_published' => true,
                'published_at' => now(),
            ],
            [
                'school_id' => $school->id,
                'created_by' => $admin->id,
                'title' => 'Annual Sports Day',
                'description' => 'Annual sports day celebration with various competitions and activities.',
                'type' => 'sports',
                'priority' => 'high',
                'event_date' => Carbon::today()->addDays(30),
                'start_time' => '08:00',
                'end_time' => '16:00',
                'location' => 'School Playground',
                'target_audience' => ['all'],
                'affected_classes' => null,
                'is_recurring' => true,
                'recurrence_type' => 'yearly',
                'recurrence_end_date' => Carbon::today()->addYears(5),
                'requires_acknowledgment' => false,
                'is_published' => true,
                'published_at' => now(),
            ],
        ];

        foreach ($events as $eventData) {
            Event::create($eventData);
        }

        $this->command->info('Events seeded successfully!');
    }
}
