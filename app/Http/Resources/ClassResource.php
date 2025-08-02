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
            'grade' => $this->grade,
            'section' => $this->section,
            'academic_year' => $this->academic_year,
            'class_teacher' => new TeacherResource($this->whenLoaded('classTeacher')),
            'room_number' => $this->room_number,
            'capacity' => $this->capacity,
            'description' => $this->description,
            'students_count' => $this->students_count ?? $this->students()->count(),
            'students' => StudentResource::collection($this->whenLoaded('students')),
            'todays_attendance' => $this->when($this->relationLoaded('todaysAttendance'), function () {
                $totalStudents = $this->whenLoaded('students', function () {
                    return $this->students->count();
                }, 0);

                $presentCount = $this->todaysAttendance->where('status', 'present')->count();
                $absentCount = $this->todaysAttendance->where('status', 'absent')->count();
                $lateCount = $this->todaysAttendance->where('status', 'late')->count();
                $excusedCount = $this->todaysAttendance->where('status', 'excused')->count();

                return [
                    'date' => today()->toDateString(),
                    'total_students' => $totalStudents,
                    'present_count' => $presentCount,
                    'absent_count' => $absentCount,
                    'late_count' => $lateCount,
                    'excused_count' => $excusedCount,
                    'attendance_percentage' => $totalStudents > 0
                        ? round(($presentCount / $totalStudents) * 100, 2)
                        : 0,
                    'records' => $this->todaysAttendance->map(function ($attendance) {
                        $student = $attendance->student;
                        return [
                            'student_id' => $attendance->student_id,
                            'student_name' => $student ? ($student->first_name . ' ' . $student->last_name) : null,
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
