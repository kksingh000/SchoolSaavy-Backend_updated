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

            // Check which students are already assigned to this class
            $existingStudents = $class->students()->pluck('students.id')->toArray();
            $newStudents = array_diff($validStudents, $existingStudents);

            if (empty($newStudents)) {
                throw new \Exception('All selected students are already assigned to this class');
            }

            // Prepare data for pivot table
            $pivotData = [];
            $nextRollNumber = $this->getNextRollNumber($classId);

            foreach ($newStudents as $index => $studentId) {
                $pivotData[$studentId] = [
                    'enrolled_date' => now(),
                    'is_active' => true,
                    'roll_number' => $nextRollNumber + $index,
                ];
            }

            // Attach only new students
            $class->students()->attach($pivotData);

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
        // Use the TimetableService to get the actual timetable
        $timetableService = app(\App\Services\TimetableService::class);
        return $timetableService->getClassTimetable($classId);
    }

    protected function getNextRollNumber($classId)
    {
        $class = \App\Models\ClassRoom::find($classId);
        $lastRollNumber = $class->activeStudents()
            ->max('class_student.roll_number');

        return ($lastRollNumber ?? 0) + 1;
    }

    public function fixRollNumbers($classId)
    {
        DB::beginTransaction();
        try {
            $class = $this->find($classId);
            $students = $class->activeStudents()->orderBy('class_student.enrolled_date')->get();

            foreach ($students as $index => $student) {
                $class->students()->updateExistingPivot($student->id, [
                    'roll_number' => $index + 1
                ]);
            }

            DB::commit();
            return $class->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function removeDuplicateStudents($classId)
    {
        DB::beginTransaction();
        try {
            // Find and remove duplicate entries, keeping the latest one
            $duplicates = DB::table('class_student')
                ->select('student_id', DB::raw('COUNT(*) as count'), DB::raw('MIN(id) as keep_id'))
                ->where('class_id', $classId)
                ->groupBy('student_id')
                ->having('count', '>', 1)
                ->get();

            foreach ($duplicates as $duplicate) {
                DB::table('class_student')
                    ->where('class_id', $classId)
                    ->where('student_id', $duplicate->student_id)
                    ->where('id', '!=', $duplicate->keep_id)
                    ->delete();
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
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

    public function getClassesByTeacher($teacherId)
    {
        $currentDate = now()->format('Y-m-d');

        $classes = ClassRoom::where('class_teacher_id', $teacherId)
            ->with(['activeStudents'])
            ->withCount(['activeStudents as total_students'])
            ->get();

        // Get attendance data for all classes in a single query
        $attendanceData = DB::table('attendances')
            ->join('class_student', 'attendances.student_id', '=', 'class_student.student_id')
            ->whereIn('class_student.class_id', $classes->pluck('id'))
            ->where('class_student.is_active', true)
            ->where('attendances.date', $currentDate)
            ->where('attendances.status', 'present')
            ->select('class_student.class_id', DB::raw('COUNT(*) as present_count'))
            ->groupBy('class_student.class_id')
            ->get()
            ->keyBy('class_id');

        return $classes->map(function ($class) use ($attendanceData) {
            $totalStudents = $class->total_students;
            $presentCount = $attendanceData->get($class->id)?->present_count ?? 0;

            $attendancePercentage = $totalStudents > 0 ? round(($presentCount / $totalStudents) * 100) : 0;

            // Convert to array and add attendance data
            $classArray = $class->toArray();
            $classArray['attendance'] = $attendancePercentage;
            $classArray['students_count'] = $totalStudents;

            // Remove the activeStudents relation data to keep response clean
            unset($classArray['active_students']);

            return $classArray;
        });
    }
}
