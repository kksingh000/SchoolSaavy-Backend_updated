<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Parents;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\AssignmentSubmission;
use App\Models\AssessmentResult;
use App\Models\Event;
use App\Models\FeePayment;
use App\Models\StudentFee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ParentService
{
    /**
     * Verify if student belongs to the parent
     */
    public function verifyParentStudentRelationship($parentId, $studentId): bool
    {
        return DB::table('parent_student')
            ->where('parent_id', $parentId)
            ->where('student_id', $studentId)
            ->exists();
    }

    /**
     * Get student statistics for parent dashboard
     * Optimized with caching and efficient queries
     */
    public function getStudentStatistics($parentId, $studentId): array
    {
        // Verify relationship first
        if (!$this->verifyParentStudentRelationship($parentId, $studentId)) {
            throw new \Exception('Student does not belong to this parent.');
        }

        // Cache key for 5 minutes to optimize performance
        $cacheKey = "parent_stats_{$parentId}_{$studentId}";

        return Cache::remember($cacheKey, 300, function () use ($studentId) {
            return $this->calculateStudentStatistics($studentId);
        });
    }

    /**
     * Calculate comprehensive student statistics
     */
    private function calculateStudentStatistics($studentId): array
    {
        $student = Student::with(['currentClass', 'school'])->findOrFail($studentId);
        $currentDate = Carbon::now();
        $currentMonth = $currentDate->month;
        $currentYear = $currentDate->year;

        // Get current class ID for filtering
        $currentClass = $student->currentClass()->first();
        $classId = $currentClass?->id;

        // Parallel queries for better performance
        $stats = [
            // Basic student info
            'student_info' => [
                'id' => $student->id,
                'name' => $student->name,
                'admission_number' => $student->admission_number,
                'class' => $currentClass ? $currentClass->name . ' - ' . $currentClass->section : 'Not Assigned',
                'school' => $student->school->name,
            ],

            // Attendance Statistics
            'attendance' => $this->getAttendanceStats($studentId, $currentMonth, $currentYear),

            // Assignment Statistics
            'assignments' => $this->getAssignmentStats($studentId, $classId),

            // Assessment/Exam Statistics
            'assessments' => $this->getAssessmentStats($studentId, $currentMonth, $currentYear),

            // Fee Statistics
            'fees' => $this->getFeeStats($studentId),

            // Upcoming Events
            'events' => $this->getUpcomingEvents($student->school_id, $classId),

            // Recent Activity Summary
            'recent_activity' => $this->getRecentActivity($studentId, $classId),
        ];

        return $stats;
    }

    /**
     * Get attendance statistics
     */
    private function getAttendanceStats($studentId, $month, $year): array
    {
        $attendanceQuery = Attendance::where('student_id', $studentId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year);

        $totalDays = $attendanceQuery->count();
        $presentDays = $attendanceQuery->where('status', 'present')->count();
        $absentDays = $attendanceQuery->where('status', 'absent')->count();
        $lateDays = $attendanceQuery->where('status', 'late')->count();

        return [
            'total_days' => $totalDays,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'late_days' => $lateDays,
            'attendance_percentage' => $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0,
            'this_month' => Carbon::now()->format('F Y'),
        ];
    }

    /**
     * Get assignment statistics
     */
    private function getAssignmentStats($studentId, $classId): array
    {
        if (!$classId) {
            return [
                'total_assignments' => 0,
                'pending_submissions' => 0,
                'submitted_assignments' => 0,
                'graded_assignments' => 0,
                'overdue_assignments' => 0,
                'average_grade' => null,
            ];
        }

        // Get all assignments for the class
        $assignmentIds = Assignment::where('class_id', $classId)
            ->where('is_active', true)
            ->pluck('id');

        $totalAssignments = $assignmentIds->count();

        // Get submission statistics
        $submissions = AssignmentSubmission::where('student_id', $studentId)
            ->whereIn('assignment_id', $assignmentIds);

        $submittedCount = $submissions->count();
        $gradedCount = $submissions->where('status', 'graded')->count();
        $pendingCount = $totalAssignments - $submittedCount;

        // Overdue assignments
        $overdueCount = Assignment::whereIn('id', $assignmentIds)
            ->where('due_date', '<', Carbon::now())
            ->whereNotIn('id', function ($query) use ($studentId) {
                $query->select('assignment_id')
                    ->from('assignment_submissions')
                    ->where('student_id', $studentId);
            })
            ->count();

        // Average grade
        $averageGrade = $submissions->where('status', 'graded')
            ->avg('marks_obtained');

        return [
            'total_assignments' => $totalAssignments,
            'pending_submissions' => $pendingCount,
            'submitted_assignments' => $submittedCount,
            'graded_assignments' => $gradedCount,
            'overdue_assignments' => $overdueCount,
            'average_grade' => $averageGrade ? round($averageGrade, 2) : null,
        ];
    }

    /**
     * Get assessment/exam statistics
     */
    private function getAssessmentStats($studentId, $month, $year): array
    {
        $results = AssessmentResult::where('student_id', $studentId)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->whereNotNull('result_published_at');

        $totalExams = $results->count();
        $passedExams = $results->where('result_status', 'pass')->count();
        $failedExams = $results->where('result_status', 'fail')->count();
        $averageMarks = $results->avg('marks_obtained');
        $averagePercentage = $results->avg('percentage');

        return [
            'total_exams' => $totalExams,
            'passed_exams' => $passedExams,
            'failed_exams' => $failedExams,
            'average_marks' => $averageMarks ? round($averageMarks, 2) : null,
            'average_percentage' => $averagePercentage ? round($averagePercentage, 2) : null,
            'this_month' => Carbon::now()->format('F Y'),
        ];
    }

    /**
     * Get fee statistics
     */
    private function getFeeStats($studentId): array
    {
        $totalFees = StudentFee::where('student_id', $studentId)->sum('amount');
        $paidAmount = FeePayment::whereHas('studentFee', function ($query) use ($studentId) {
            $query->where('student_id', $studentId);
        })->sum('amount');

        $pendingAmount = $totalFees - $paidAmount;
        $overdueFees = StudentFee::where('student_id', $studentId)
            ->where('due_date', '<', Carbon::now())
            ->where('status', 'pending')
            ->sum('amount');

        return [
            'total_fees' => round($totalFees, 2),
            'paid_amount' => round($paidAmount, 2),
            'pending_amount' => round($pendingAmount, 2),
            'overdue_amount' => round($overdueFees, 2),
            'payment_status' => $pendingAmount <= 0 ? 'paid' : 'pending',
        ];
    }

    /**
     * Get upcoming events
     */
    private function getUpcomingEvents($schoolId, $classId): array
    {
        $query = Event::where('school_id', $schoolId)
            ->where('event_date', '>=', Carbon::now())
            ->where('is_published', true)
            ->orderBy('event_date', 'asc')
            ->limit(5);

        // Filter by class if available
        if ($classId) {
            $query->where(function ($q) use ($classId) {
                $q->whereNull('affected_classes')
                    ->orWhereJsonContains('affected_classes', $classId);
            });
        }

        $events = $query->get(['id', 'title', 'event_date', 'start_time', 'end_time', 'type']);

        return $events->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->title,
                'event_date' => $event->event_date->format('Y-m-d'),
                'start_time' => $event->start_time ? $event->start_time->format('H:i') : null,
                'end_time' => $event->end_time ? $event->end_time->format('H:i') : null,
                'type' => $event->type,
                'days_remaining' => Carbon::now()->diffInDays($event->event_date),
            ];
        })->toArray();
    }

    /**
     * Get recent activity summary
     */
    private function getRecentActivity($studentId, $classId): array
    {
        $activity = [];

        // Recent attendance (last 7 days)
        $recentAttendance = Attendance::where('student_id', $studentId)
            ->where('date', '>=', Carbon::now()->subDays(7))
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get(['date', 'status']);

        // Recent assignment submissions
        $recentSubmissions = AssignmentSubmission::where('student_id', $studentId)
            ->with('assignment:id,title,due_date')
            ->orderBy('submitted_at', 'desc')
            ->limit(3)
            ->get(['assignment_id', 'status', 'submitted_at', 'marks_obtained']);

        // Recent grades/results
        $recentGrades = AssessmentResult::where('student_id', $studentId)
            ->with('assessment:id,title,assessment_date')
            ->whereNotNull('result_published_at')
            ->orderBy('result_published_at', 'desc')
            ->limit(3)
            ->get(['assessment_id', 'marks_obtained', 'percentage', 'grade', 'result_published_at']);

        return [
            'recent_attendance' => $recentAttendance->toArray(),
            'recent_submissions' => $recentSubmissions->toArray(),
            'recent_grades' => $recentGrades->toArray(),
        ];
    }

    /**
     * Get all children for a parent
     */
    public function getParentChildren($parentId): array
    {
        $parent = Parents::with(['students.currentClass', 'students.school'])
            ->findOrFail($parentId);

        return $parent->students->map(function ($student) {
            $currentClass = $student->currentClass()->first();

            return [
                // Basic student information
                'id' => $student->id,
                'admission_number' => $student->admission_number,
                'roll_number' => $student->roll_number,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'date_of_birth' => $student->date_of_birth,
                'gender' => $student->gender,
                'admission_date' => $student->admission_date,
                'blood_group' => $student->blood_group,
                'profile_photo' => $student->profile_photo,
                'address' => $student->address,
                'phone' => $student->phone,
                'is_active' => $student->is_active,
                'created_at' => $student->created_at,
                'updated_at' => $student->updated_at,

                // Class information
                'class_id' => $currentClass?->id,
                'class_name' => $currentClass?->name,
                'class_section' => $currentClass?->section,
                'class_title' => $currentClass ? $currentClass->name . ' - ' . $currentClass->section : 'Not Assigned',

                // School information
                'school_id' => $student->school->id,
                'school_name' => $student->school->name,

                // Computed fields
                'full_name' => $student->name,
            ];
        })->toArray();
    }

    /**
     * Clear cache for specific parent-student combination
     */
    public function clearStudentStatsCache($parentId, $studentId): void
    {
        $cacheKey = "parent_stats_{$parentId}_{$studentId}";
        Cache::forget($cacheKey);
    }
}
