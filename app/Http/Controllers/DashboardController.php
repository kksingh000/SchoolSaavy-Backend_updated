<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends BaseController
{
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->errorResponse('Unauthorized access', null, 401);
            }

            $dashboardData = [];

            // Get dashboard data based on user type
            switch ($user->user_type) {
                case 'admin':
                    $dashboardData = $this->getSchoolAdminDashboard($user);
                    break;
                case 'teacher':
                    $dashboardData = $this->getTeacherDashboard($user);
                    break;
                case 'parent':
                    $dashboardData = $this->getParentDashboard($user);
                    break;
                default:
                    return $this->errorResponse('Invalid user type', null, 400);
            }

            return $this->successResponse(
                $dashboardData,
                'Dashboard data retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    private function getSchoolAdminDashboard($user): array
    {
        $school = $user->schoolAdmin->school;

        return [
            'school_info' => [
                'name' => $school->name,
                'code' => $school->code,
                'total_students' => $school->students()->count(),
                'total_teachers' => $school->teachers()->count(),
                'total_classes' => $school->classes()->count(),
            ],
            'quick_stats' => [
                'present_today' => $this->getTodayAttendanceCount($school->id, 'present'),
                'absent_today' => $this->getTodayAttendanceCount($school->id, 'absent'),
                'pending_fees' => $this->getPendingFeesAmount($school->id),
                'active_modules' => $school->modules()->wherePivot('status', 'active')->count(),
            ],
            'recent_activities' => $this->getRecentActivities($school->id),
            'upcoming_events' => $this->getUpcomingEvents($school->id),
        ];
    }

    private function getTeacherDashboard($user): array
    {
        $teacher = $user->teacher;
        $schoolId = $user->getSchool()->id;

        // Get the 4 key metrics
        $dashboardMetrics = $this->getTeacherDashboardMetrics($teacher->id, $schoolId);

        return [
            'teacher_info' => [
                'name' => $user->name,
                'employee_id' => $teacher->employee_id,
                'classes_assigned' => $teacher->classes()->count(),
            ],
            'dashboard_metrics' => $dashboardMetrics,
            'today_schedule' => $this->getTodaySchedule($teacher->id),
            'class_attendance' => $this->getTeacherClassAttendance($teacher->id),
            'pending_tasks' => $this->getTeacherTasks($teacher->id),
        ];
    }

    /**
     * Get teacher dashboard metrics
     */
    private function getTeacherDashboardMetrics($teacherId, $schoolId): array
    {
        // 1. Total Classes (where teacher is class teacher)
        $totalClasses = DB::table('classes')
            ->where('class_teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->count();

        // 2. Total Students (across all teacher's classes)
        $totalStudents = DB::table('class_student')
            ->join('classes', 'class_student.class_id', '=', 'classes.id')
            ->join('students', 'class_student.student_id', '=', 'students.id')
            ->where('classes.class_teacher_id', $teacherId)
            ->where('classes.school_id', $schoolId)
            ->where('classes.is_active', true)
            ->where('class_student.is_active', true)
            ->distinct('students.id')
            ->count('students.id');

        // 3. Total Assignments (created by this teacher)
        $totalAssignments = Assignment::where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->count();

        // 4. Total Events for Today
        $todaysEvents = Event::where('school_id', $schoolId)
            ->whereDate('event_date', Carbon::today())
            ->where(function ($query) {
                $query->whereJsonContains('target_audience', 'all')
                    ->orWhereJsonContains('target_audience', 'teachers');
            })
            ->count();

        return [
            'total_classes' => $totalClasses,
            'total_students' => $totalStudents,
            'total_assignments' => $totalAssignments,
            'total_events_today' => $todaysEvents,
        ];
    }

    private function getParentDashboard($user): array
    {
        $parent = $user->parent;
        $children = $parent->students;

        return [
            'parent_info' => [
                'name' => $user->name,
                'children_count' => $children->count(),
            ],
            'children' => $children->map(function ($child) {
                return [
                    'id' => $child->id,
                    'name' => $child->first_name . ' ' . $child->last_name,
                    'class' => $child->currentClass->name ?? 'Not Assigned',
                    'today_attendance' => $this->getStudentTodayAttendance($child->id),
                    'pending_fees' => $this->getStudentPendingFees($child->id),
                ];
            }),
            'recent_notifications' => $this->getParentNotifications($parent->id),
            'upcoming_events' => $this->getUpcomingEvents($parent->school_id),
        ];
    }

    private function getTodayAttendanceCount($schoolId, $status): int
    {
        return DB::table('attendances')
            ->where('school_id', $schoolId)
            ->where('date', today())
            ->where('status', $status)
            ->count();
    }

    private function getPendingFeesAmount($schoolId): float
    {
        return DB::table('student_fees')
            ->join('students', 'student_fees.student_id', '=', 'students.id')
            ->where('students.school_id', $schoolId)
            ->where('student_fees.status', 'pending')
            ->sum('student_fees.amount');
    }

    private function getRecentActivities($schoolId): array
    {
        // Implement recent activities logic
        return [];
    }

    private function getUpcomingEvents($schoolId): array
    {
        // Implement upcoming events logic
        return [];
    }

    private function getTodaySchedule($teacherId): array
    {
        // Implement today's schedule logic
        return [];
    }

    private function getTeacherClassAttendance($teacherId): array
    {
        // Implement teacher class attendance logic
        return [];
    }

    private function getTeacherTasks($teacherId): array
    {
        // Implement teacher tasks logic
        return [];
    }

    private function getStudentTodayAttendance($studentId): ?string
    {
        $attendance = DB::table('attendances')
            ->where('student_id', $studentId)
            ->where('date', today())
            ->first();

        return $attendance ? $attendance->status : null;
    }

    private function getStudentPendingFees($studentId): float
    {
        return DB::table('student_fees')
            ->where('student_id', $studentId)
            ->where('status', 'pending')
            ->sum('amount');
    }

    private function getParentNotifications($parentId): array
    {
        // Implement parent notifications logic
        return [];
    }
}
