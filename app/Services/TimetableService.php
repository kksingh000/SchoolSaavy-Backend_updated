<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\TeacherSchedule;
use Illuminate\Support\Facades\DB;

class TimetableService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = ClassSchedule::class;
    }

    public function createClassSchedule(array $data)
    {
        DB::beginTransaction();
        try {
            // Validate for schedule conflicts
            $this->validateScheduleConflicts($data);

            $schedule = $this->create([
                'class_id' => $data['class_id'],
                'subject_id' => $data['subject_id'],
                'teacher_id' => $data['teacher_id'],
                'day_of_week' => $data['day_of_week'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'room_number' => $data['room_number'],
                'school_id' => auth()->user()->school_id,
            ]);

            // Create teacher schedule entry
            TeacherSchedule::create([
                'teacher_id' => $data['teacher_id'],
                'class_schedule_id' => $schedule->id,
                'school_id' => auth()->user()->school_id,
            ]);

            DB::commit();
            return $schedule;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getClassTimetable($classId)
    {
        return ClassSchedule::where('class_id', $classId)
            ->with(['subject', 'teacher'])
            ->get()
            ->groupBy('day_of_week');
    }

    public function getTeacherTimetable($teacherId)
    {
        return TeacherSchedule::where('teacher_id', $teacherId)
            ->with(['classSchedule.class', 'classSchedule.subject'])
            ->get()
            ->groupBy('classSchedule.day_of_week');
    }

    protected function validateScheduleConflicts($data)
    {
        // Check for teacher schedule conflicts
        $teacherConflict = ClassSchedule::where('teacher_id', $data['teacher_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where(function ($query) use ($data) {
                $query->whereBetween('start_time', [$data['start_time'], $data['end_time']])
                    ->orWhereBetween('end_time', [$data['start_time'], $data['end_time']]);
            })
            ->exists();

        if ($teacherConflict) {
            throw new \Exception('Teacher schedule conflict detected');
        }

        // Check for class schedule conflicts
        $classConflict = ClassSchedule::where('class_id', $data['class_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where(function ($query) use ($data) {
                $query->whereBetween('start_time', [$data['start_time'], $data['end_time']])
                    ->orWhereBetween('end_time', [$data['start_time'], $data['end_time']]);
            })
            ->exists();

        if ($classConflict) {
            throw new \Exception('Class schedule conflict detected');
        }
    }
} 