<?php

namespace App\Services;

use App\Models\ClassRoom;
use Illuminate\Support\Facades\DB;

class ClassService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = ClassRoom::class;
    }

    public function createClass(array $data)
    {
        DB::beginTransaction();
        try {
            $data['school_id'] = auth()->user()->school_id;
            
            // Create class
            $class = $this->create($data);

            // Create subjects if provided
            if (isset($data['subjects'])) {
                foreach ($data['subjects'] as $subject) {
                    $class->subjects()->create($subject);
                }
            }

            // Create schedule if provided
            if (isset($data['schedule'])) {
                foreach ($data['schedule'] as $schedule) {
                    $class->schedule()->create($schedule);
                }
            }

            DB::commit();
            return $class;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getAttendanceStats($classId, $date)
    {
        $class = $this->find($classId);
        return $class->students()
            ->withCount([
                'attendance as present_count' => function ($query) use ($date) {
                    $query->where('date', $date)->where('status', 'present');
                },
                'attendance as absent_count' => function ($query) use ($date) {
                    $query->where('date', $date)->where('status', 'absent');
                },
                'attendance as late_count' => function ($query) use ($date) {
                    $query->where('date', $date)->where('status', 'late');
                }
            ])
            ->get();
    }

    public function getTimeTable($classId)
    {
        $class = $this->find($classId, ['schedule.subject', 'schedule.teacher']);
        return $class->schedule->groupBy('day_of_week');
    }
} 