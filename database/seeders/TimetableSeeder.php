<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClassSchedule;
use App\Models\ClassRoom;
use App\Models\Subject;
use App\Models\Teacher;

class TimetableSeeder extends Seeder
{
    public function run()
    {
        // Get existing data
        $class = ClassRoom::first();
        $subjects = Subject::limit(5)->get();
        $teacher = Teacher::first();

        if (!$class || $subjects->isEmpty() || !$teacher) {
            $this->command->info('Please ensure you have at least one class, subjects, and teacher before running this seeder.');
            return;
        }

        // Sample timetable for Nursery A
        $schedules = [
            [
                'school_id' => $class->school_id,
                'class_id' => $class->id,
                'subject_id' => $subjects->first()->id,
                'teacher_id' => $teacher->id,
                'day_of_week' => 'monday',
                'start_time' => '09:00',
                'end_time' => '09:45',
                'room_number' => 'A-101',
                'notes' => 'Morning session',
            ],
            [
                'school_id' => $class->school_id,
                'class_id' => $class->id,
                'subject_id' => $subjects->get(1)->id ?? $subjects->first()->id,
                'teacher_id' => $teacher->id,
                'day_of_week' => 'monday',
                'start_time' => '10:00',
                'end_time' => '10:45',
                'room_number' => 'A-101',
                'notes' => null,
            ],
            [
                'school_id' => $class->school_id,
                'class_id' => $class->id,
                'subject_id' => $subjects->get(2)->id ?? $subjects->first()->id,
                'teacher_id' => $teacher->id,
                'day_of_week' => 'tuesday',
                'start_time' => '09:00',
                'end_time' => '09:45',
                'room_number' => 'A-101',
                'notes' => null,
            ],
            [
                'school_id' => $class->school_id,
                'class_id' => $class->id,
                'subject_id' => $subjects->get(3)->id ?? $subjects->first()->id,
                'teacher_id' => $teacher->id,
                'day_of_week' => 'wednesday',
                'start_time' => '09:00',
                'end_time' => '09:45',
                'room_number' => 'A-101',
                'notes' => null,
            ],
            [
                'school_id' => $class->school_id,
                'class_id' => $class->id,
                'subject_id' => $subjects->get(4)->id ?? $subjects->first()->id,
                'teacher_id' => $teacher->id,
                'day_of_week' => 'thursday',
                'start_time' => '09:00',
                'end_time' => '09:45',
                'room_number' => 'A-101',
                'notes' => null,
            ],
            [
                'school_id' => $class->school_id,
                'class_id' => $class->id,
                'subject_id' => $subjects->first()->id,
                'teacher_id' => $teacher->id,
                'day_of_week' => 'friday',
                'start_time' => '09:00',
                'end_time' => '09:45',
                'room_number' => 'A-101',
                'notes' => 'Weekly review',
            ],
        ];

        foreach ($schedules as $schedule) {
            ClassSchedule::create($schedule);
        }

        $this->command->info('Timetable seeded successfully!');
    }
}
