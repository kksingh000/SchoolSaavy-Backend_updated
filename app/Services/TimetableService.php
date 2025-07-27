<?php

namespace App\Services;

use App\Models\ClassSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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
            // Add school_id to data
            $data['school_id'] = Auth::user()->getSchoolId();

            // Validate for schedule conflicts
            $this->validateScheduleConflicts($data);

            $schedule = $this->create($data);

            DB::commit();
            return $schedule->load(['class', 'subject', 'teacher.user']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateClassSchedule($id, array $data)
    {
        DB::beginTransaction();
        try {
            $schedule = $this->find($id);

            // If time or day is being updated, validate conflicts
            if (isset($data['start_time']) || isset($data['end_time']) || isset($data['day_of_week']) || isset($data['teacher_id'])) {
                $conflictData = array_merge($schedule->toArray(), $data);
                $this->validateScheduleConflicts($conflictData, $id);
            }

            $schedule->update($data);

            DB::commit();
            return $schedule->fresh(['class', 'subject', 'teacher.user']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteClassSchedule($id)
    {
        DB::beginTransaction();
        try {
            $schedule = $this->find($id);
            $schedule->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getClassTimetable($classId)
    {
        $schedules = ClassSchedule::where('class_id', $classId)
            ->active()
            ->with(['subject', 'teacher.user'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // Group by day of week for easier frontend consumption
        $timetable = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            $timetable[$day] = $schedules->filter(function ($schedule) use ($day) {
                return $schedule->day_of_week === $day;
            })->values();
        }

        return $timetable;
    }

    public function getTeacherTimetable($teacherId)
    {
        $schedules = ClassSchedule::where('teacher_id', $teacherId)
            ->active()
            ->with(['class', 'subject'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // Group by day of week
        $timetable = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            $timetable[$day] = $schedules->filter(function ($schedule) use ($day) {
                return $schedule->day_of_week === $day;
            })->values();
        }

        return $timetable;
    }

    public function getWeeklyOverview()
    {
        $schoolId = Auth::user()->getSchoolId();

        $schedules = ClassSchedule::where('school_id', $schoolId)
            ->active()
            ->with(['class', 'subject', 'teacher.user'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // Group by day for overview
        $overview = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            $daySchedules = $schedules->filter(function ($schedule) use ($day) {
                return $schedule->day_of_week === $day;
            })->values();

            $overview[$day] = [
                'total_classes' => $daySchedules->count(),
                'schedules' => $daySchedules
            ];
        }

        return $overview;
    }

    protected function validateScheduleConflicts($data, $excludeId = null)
    {
        $query = ClassSchedule::where('teacher_id', $data['teacher_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('is_active', true)
            ->where(function ($query) use ($data) {
                $query->where(function ($q) use ($data) {
                    // Check if start_time falls within existing slot
                    $q->where('start_time', '<=', $data['start_time'])
                        ->where('end_time', '>', $data['start_time']);
                })->orWhere(function ($q) use ($data) {
                    // Check if end_time falls within existing slot
                    $q->where('start_time', '<', $data['end_time'])
                        ->where('end_time', '>=', $data['end_time']);
                })->orWhere(function ($q) use ($data) {
                    // Check if new slot encompasses existing slot
                    $q->where('start_time', '>=', $data['start_time'])
                        ->where('end_time', '<=', $data['end_time']);
                });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new \Exception('Teacher schedule conflict detected for this time slot');
        }

        // Check for class schedule conflicts
        $classQuery = ClassSchedule::where('class_id', $data['class_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('is_active', true)
            ->where(function ($query) use ($data) {
                $query->where(function ($q) use ($data) {
                    $q->where('start_time', '<=', $data['start_time'])
                        ->where('end_time', '>', $data['start_time']);
                })->orWhere(function ($q) use ($data) {
                    $q->where('start_time', '<', $data['end_time'])
                        ->where('end_time', '>=', $data['end_time']);
                })->orWhere(function ($q) use ($data) {
                    $q->where('start_time', '>=', $data['start_time'])
                        ->where('end_time', '<=', $data['end_time']);
                });
            });

        if ($excludeId) {
            $classQuery->where('id', '!=', $excludeId);
        }

        if ($classQuery->exists()) {
            throw new \Exception('Class schedule conflict detected for this time slot');
        }
    }
}
