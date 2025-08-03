<?php

namespace App\Services;

use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

class AttendanceService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = Attendance::class;
    }

    public function getAll($filters = [], $relations = [])
    {
        $query = $this->model::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        // Extract per_page from filters before applying other filters
        $perPage = $filters['per_page'] ?? 15;
        unset($filters['per_page']);

        foreach ($filters as $field => $value) {
            if (method_exists($this, 'filter' . ucfirst($field))) {
                $this->{'filter' . ucfirst($field)}($query, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query->paginate($perPage);
    }

    public function markBulkAttendance(array $data)
    {
        DB::beginTransaction();
        try {
            foreach ($data['attendances'] as $record) {
                $attendanceData = [
                    'student_id' => $record['student_id'],
                    'class_id' => $data['class_id'],
                    'date' => $data['date'],
                    'status' => $record['status'],
                    'remarks' => $record['remarks'] ?? null,
                    'marked_by' => auth()->id(),
                    'school_id' => auth()->user()->getSchoolId(),
                ];

                // Use updateOrCreate to handle duplicates
                Attendance::updateOrCreate(
                    [
                        'student_id' => $record['student_id'],
                        'date' => $data['date'],
                        'school_id' => auth()->user()->getSchoolId(),
                    ],
                    $attendanceData
                );
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function markSingleAttendance(array $data)
    {
        try {
            $attendanceData = [
                'student_id' => $data['student_id'],
                'class_id' => $data['class_id'],
                'date' => $data['date'],
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? null,
                'marked_by' => auth()->id(),
                'school_id' => auth()->user()->getSchoolId(),
            ];

            // Use updateOrCreate to handle duplicates
            return Attendance::updateOrCreate(
                [
                    'student_id' => $data['student_id'],
                    'date' => $data['date'],
                    'school_id' => auth()->user()->getSchoolId(),
                ],
                $attendanceData
            );
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getClassAttendanceReport($classId, $startDate, $endDate)
    {
        return Attendance::where('class_id', $classId)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('student')
            ->get()
            ->groupBy(['date', 'status']);
    }

    public function getStudentAttendanceReport($studentId, $startDate, $endDate)
    {
        return Attendance::where('student_id', $studentId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('status');
    }

    public function getClassAttendanceByDate($classId, $date)
    {
        // Get the class with all its active students
        $class = \App\Models\ClassRoom::with(['activeStudents'])->findOrFail($classId);

        // Get attendance records for this class and date
        $attendanceRecords = Attendance::where('class_id', $classId)
            ->where('date', $date)
            ->with(['student', 'markedBy'])
            ->get()
            ->keyBy('student_id');

        // Build the students array with attendance status
        $students = $class->activeStudents->map(function ($student) use ($attendanceRecords) {
            $attendance = $attendanceRecords->get($student->id);

            return [
                'id' => $student->id,
                'name' => $student->first_name . ' ' . $student->last_name,
                'admission_number' => $student->admission_number,
                'attendance_status' => $attendance ? $attendance->status : null,
                'marked_at' => $attendance ? $attendance->created_at : null,
                'marked_by' => $attendance ? $attendance->marked_by : null,
                'remarks' => $attendance ? $attendance->remarks : null,
            ];
        });

        // Calculate summary
        $totalStudents = $students->count();
        $present = $students->where('attendance_status', 'present')->count();
        $absent = $students->where('attendance_status', 'absent')->count();
        $late = $students->where('attendance_status', 'late')->count();
        $excused = $students->where('attendance_status', 'excused')->count();
        $leave = $students->where('attendance_status', 'leave')->count();
        $notMarked = $students->whereNull('attendance_status')->count();

        return [
            'class_id' => (int) $classId,
            'class_name' => $class->name,
            'date' => $date,
            'students' => $students->values(),
            'attendance_summary' => [
                'total_students' => $totalStudents,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'excused' => $excused,
                'leave' => $leave,
                'not_marked' => $notMarked,
            ]
        ];
    }
}
