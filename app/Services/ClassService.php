<?php

namespace App\Services;

use App\Models\ClassRoom;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ClassService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = ClassRoom::class;
    }

    public function find($id, $relations = [])
    {
        $query = $this->model::query();

        // Handle custom relationships
        if (!empty($relations)) {
            $customRelations = [];
            $normalRelations = [];

            foreach ($relations as $relation) {
                if ($relation === 'students') {
                    $customRelations[] = 'activeStudents';
                } else {
                    $normalRelations[] = $relation;
                }
            }

            if (!empty($normalRelations)) {
                $query->with($normalRelations);
            }

            if (!empty($customRelations)) {
                $query->with($customRelations);
            }
        }

        return $query->findOrFail($id);
    }

    public function createClass(array $data)
    {
        DB::beginTransaction();
        try {
            $data['school_id'] = Auth::user()->getSchoolId();

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

    public function assignStudents($classId, $studentIds)
    {
        DB::beginTransaction();
        try {
            $class = $this->find($classId);
            $schoolId = Auth::user()->getSchoolId();

            // Validate that all students belong to the same school
            $validStudents = \App\Models\Student::whereIn('id', $studentIds)
                ->where('school_id', $schoolId)
                ->pluck('id')
                ->toArray();

            if (count($validStudents) !== count($studentIds)) {
                throw new \Exception('Some students do not belong to your school');
            }

            // Prepare data for pivot table
            $pivotData = [];
            $nextRollNumber = $this->getNextRollNumber($classId);

            foreach ($validStudents as $index => $studentId) {
                $pivotData[$studentId] = [
                    'enrolled_date' => now(),
                    'is_active' => true,
                    'roll_number' => $nextRollNumber + $index,
                ];
            }

            // Sync students with the class
            $class->students()->syncWithoutDetaching($pivotData);

            DB::commit();
            return $class->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function removeStudents($classId, $studentIds)
    {
        DB::beginTransaction();
        try {
            $class = $this->find($classId);

            // Update pivot to mark as inactive instead of deleting
            foreach ($studentIds as $studentId) {
                $class->students()->updateExistingPivot($studentId, [
                    'left_date' => now(),
                    'is_active' => false,
                ]);
            }

            DB::commit();
            return $class->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getStudents($classId)
    {
        $class = $this->find($classId);
        return $class->activeStudents()
            ->withPivot(['roll_number', 'enrolled_date'])
            ->get();
    }

    public function getTimetable($classId)
    {
        $class = $this->find($classId);

        // This would require a class_schedules table - let's return a placeholder for now
        return [
            'message' => 'Timetable feature requires class_schedules table implementation',
            'class_id' => $classId,
            'class_name' => $class->name,
        ];
    }

    protected function getNextRollNumber($classId)
    {
        $class = \App\Models\ClassRoom::find($classId);
        $lastRollNumber = $class->activeStudents()
            ->max('class_student.roll_number');

        return ($lastRollNumber ?? 0) + 1;
    }
    public function getAttendanceStats($classId, $date)
    {
        $class = $this->find($classId);
        return $class->activeStudents()
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
}
