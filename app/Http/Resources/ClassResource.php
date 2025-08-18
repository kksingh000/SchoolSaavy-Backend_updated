<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClassResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'grade_level' => $this->grade_level,
            'section' => $this->section,
            'class_teacher' => new TeacherResource($this->whenLoaded('classTeacher')),
            'capacity' => $this->capacity,
            'description' => $this->description,
            'students_count' => $this->whenLoaded('students', function () {
                return $this->students->count();
            }, $this->students_count ?? 0),
            'students' => StudentResource::collection($this->whenLoaded('students')),
            'todays_attendance' => $this->when($this->relationLoaded('todaysAttendance'), function () {
                $totalStudents = $this->whenLoaded('students', function () {
                    return $this->students->count();
                }, 0);

                // Use already loaded attendance data instead of querying again
                $attendanceCollection = $this->todaysAttendance;
                $presentCount = $attendanceCollection->where('status', 'present')->count();
                $absentCount = $attendanceCollection->where('status', 'absent')->count();
                $lateCount = $attendanceCollection->where('status', 'late')->count();
                $excusedCount = $attendanceCollection->where('status', 'excused')->count();
                $leaveCount = $attendanceCollection->where('status', 'leave')->count();

                return [
                    'date' => today()->toDateString(),
                    'total_students' => $totalStudents,
                    'present_count' => $presentCount,
                    'absent_count' => $absentCount,
                    'late_count' => $lateCount,
                    'excused_count' => $excusedCount,
                    'leave_count' => $leaveCount,
                    'attendance_percentage' => $totalStudents > 0
                        ? round(($presentCount / $totalStudents) * 100, 2)
                        : 0,
                    'records' => $attendanceCollection->map(function ($attendance) {
                        return [
                            'student_id' => $attendance->student_id,
                            'student_name' => $attendance->student
                                ? ($attendance->student->first_name . ' ' . $attendance->student->last_name)
                                : null,
                            'admission_number' => $attendance->student->admission_number ?? null,
                            'status' => $attendance->status,
                            'check_in_time' => $attendance->check_in_time,
                            'check_out_time' => $attendance->check_out_time,
                            'remarks' => $attendance->remarks,
                        ];
                    })
                ];
            }),
            'subjects' => SubjectResource::collection($this->whenLoaded('subjects')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
