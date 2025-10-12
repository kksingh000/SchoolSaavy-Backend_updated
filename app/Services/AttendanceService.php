<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Import Attendance Events
use App\Events\Attendance\StudentMarkedAbsent;
use App\Events\Attendance\StudentMarkedLate;

/**
 * @see file:copilot-instructions.md
 * 
 * AttendanceService - Handles attendance management business logic
 * 
 * CRITICAL SECURITY: All queries MUST include school_id isolation
 * IMPORTANT: marked_by field stores user_id (Auth::id()), not teacher_id
 * 
 * NOTIFICATION EVENTS: Fires events for absent and late attendance
 */

class AttendanceService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = Attendance::class;
    }

    public function getAll($filters = [], $relations = [])
    {
        $query = $this->model::query();

        // CRITICAL: Always filter by school_id for multi-tenant security
        $query->where('school_id', request()->school_id);

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
            $eventsToFire = []; // Collect events to fire after commit
            $timestamp = now();

            // Get all student IDs upfront for efficient querying
            $studentIds = array_column($data['attendances'], 'student_id');
            
            // Load all students with relationships in ONE query (not N queries)
            $students = Student::with('parents.user')
                ->whereIn('id', $studentIds)
                ->get()
                ->keyBy('id');

            // Prepare bulk insert/update data
            $attendanceData = [];
            foreach ($data['attendances'] as $record) {
                $attendanceData[] = [
                    'student_id' => $record['student_id'],
                    'class_id' => $data['class_id'],
                    'date' => $data['date'],
                    'status' => $record['status'],
                    'remarks' => $record['remarks'] ?? null,
                    'marked_by' => Auth::id(),
                    'school_id' => request()->school_id,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            // Bulk upsert (insert or update) - MUCH faster than individual queries
            Attendance::upsert(
                $attendanceData,
                ['student_id', 'date', 'school_id'], // Unique keys
                ['status', 'remarks', 'marked_by', 'class_id', 'updated_at'] // Update these fields
            );

            // Load attendance records for events (only for absent/late)
            $absentLateStudentIds = [];
            foreach ($data['attendances'] as $record) {
                if (in_array($record['status'], ['absent', 'late'])) {
                    $absentLateStudentIds[] = $record['student_id'];
                }
            }

            if (!empty($absentLateStudentIds)) {
                $attendances = Attendance::where('date', $data['date'])
                    ->where('school_id', request()->school_id)
                    ->whereIn('student_id', $absentLateStudentIds)
                    ->get()
                    ->keyBy('student_id');

                // Collect events to fire AFTER commit
                foreach ($data['attendances'] as $record) {
                    if (in_array($record['status'], ['absent', 'late'])) {
                        $student = $students->get($record['student_id']);
                        $attendance = $attendances->get($record['student_id']);
                        
                        if ($student && $attendance) {
                            $eventsToFire[] = [
                                'student' => $student,
                                'attendance' => $attendance,
                                'date' => $data['date'],
                                'status' => $record['status']
                            ];
                        }
                    }
                }
            }

            DB::commit();

            // Fire all events AFTER transaction commits (async, non-blocking)
            // This happens OUTSIDE the transaction, so it won't block the API response
            foreach ($eventsToFire as $eventData) {
                $this->fireAttendanceEvent(
                    $eventData['student'],
                    $eventData['attendance'],
                    $eventData['date'],
                    $eventData['status']
                );
            }

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
                'marked_by' => Auth::id(),
                'school_id' => request()->school_id,
            ];

            // Use updateOrCreate to handle duplicates
            $attendance = Attendance::updateOrCreate(
                [
                    'student_id' => $data['student_id'],
                    'date' => $data['date'],
                    'school_id' => request()->school_id,
                ],
                $attendanceData
            );

            // Fire notification events based on status
            $student = Student::with('parents.user')->find($data['student_id']);
            if ($student) {
                $this->fireAttendanceEvent($student, $attendance, $data['date'], $data['status']);
            }

            return $attendance;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Fire appropriate attendance event based on status
     * 
     * @param Student $student
     * @param Attendance $attendance
     * @param string $date
     * @param string $status
     */
    protected function fireAttendanceEvent(Student $student, Attendance $attendance, string $date, string $status): void
    {
        try {
            switch ($status) {
                case 'absent':
                    event(new StudentMarkedAbsent($student, $attendance, $date));
                    Log::info('StudentMarkedAbsent event fired', [
                        'student_id' => $student->id,
                        'date' => $date,
                        'attendance_id' => $attendance->id
                    ]);
                    break;

                case 'late':
                    // Extract arrival time from remarks or check_in_time if available
                    $arrivalTime = $attendance->check_in_time ?? null;
                    event(new StudentMarkedLate($student, $attendance, $date, $arrivalTime));
                    Log::info('StudentMarkedLate event fired', [
                        'student_id' => $student->id,
                        'date' => $date,
                        'attendance_id' => $attendance->id,
                        'arrival_time' => $arrivalTime
                    ]);
                    break;

                // For present, excused, leave - no immediate notification needed
                // These can be handled by scheduled jobs if needed
            }
        } catch (\Exception $e) {
            // Log error but don't fail the attendance marking
            Log::error('Failed to fire attendance event', [
                'error' => $e->getMessage(),
                'student_id' => $student->id,
                'status' => $status
            ]);
        }
    }

    public function getClassAttendanceReport($classId, $startDate, $endDate)
    {
        return Attendance::where('class_id', $classId)
            ->where('school_id', request()->school_id)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('student')
            ->get()
            ->groupBy(['date', 'status']);
    }

    public function getStudentAttendanceReport($studentId, $startDate, $endDate)
    {
        return Attendance::where('student_id', $studentId)
            ->where('school_id', request()->school_id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('status');
    }

    public function getClassAttendanceByDate($classId, $date)
    {
        // Get the class with all its active students - ensure same school
        $class = \App\Models\ClassRoom::where('id', $classId)
            ->where('school_id', request()->school_id)
            ->with(['activeStudents'])
            ->firstOrFail();

        // Get attendance records for this class and date
        $attendanceRecords = Attendance::where('class_id', $classId)
            ->where('school_id', request()->school_id)
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
