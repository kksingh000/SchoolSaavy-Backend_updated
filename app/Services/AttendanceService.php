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

    public function markBulkAttendance(array $data)
    {
        DB::beginTransaction();
        try {
            $attendanceRecords = collect($data['attendances'])->map(function ($record) use ($data) {
                return [
                    'student_id' => $record['student_id'],
                    'class_id' => $data['class_id'],
                    'date' => $data['date'],
                    'status' => $record['status'],
                    'remarks' => $record['remarks'] ?? null,
                    'marked_by' => auth()->id(),
                    'school_id' => auth()->user()->getSchoolId(),
                ];
            });

            Attendance::insert($attendanceRecords->toArray());
            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
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
}
