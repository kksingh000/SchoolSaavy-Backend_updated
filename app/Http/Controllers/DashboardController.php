<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Event;
use App\Traits\OctaneCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardController extends BaseController
{
    use OctaneCache;
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
                case 'school_admin':  // Fixed: was 'admin', should be 'school_admin'
                    $dashboardData = $this->getSchoolAdminDashboard($user);
                    break;
                case 'teacher':
                    $dashboardData = $this->getTeacherDashboard($user);
                    break;
                case 'parent':
                    $dashboardData = $this->getParentDashboard($user);
                    break;
                default:
                    return $this->errorResponse('Invalid user type: ' . $user->user_type, null, 400);
            }

            return $this->successResponse(
                $dashboardData,
                'Dashboard data retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error retrieving dashboard data: ' . $e->getMessage() . ' | Line: ' . $e->getLine() . ' | File: ' . $e->getFile());
            return $this->errorResponse('An error occurred while retrieving dashboard data: ' . $e->getMessage(), null, 500);
        }
    }

    private function getSchoolAdminDashboard($user): array
    {
        // Add null checking for schoolAdmin relationship
        if (!$user->schoolAdmin || !$user->schoolAdmin->school) {
            throw new \Exception('School admin data not found or school not assigned');
        }

        $school = $user->schoolAdmin->school;
        $schoolId = $school->id;

        return [
            'school_info' => [
                'name' => $school->name,
                'code' => $school->code,
                'total_students' => $school->students()->count(),
                'total_teachers' => $school->teachers()->count(),
                'total_classes' => $school->classes()->count(),
            ],
            'quick_stats' => [
                'present_today' => $this->getTodayAttendanceCount($schoolId, 'present'),
                'absent_today' => $this->getTodayAttendanceCount($schoolId, 'absent'),
                'pending_fees' => $this->getPendingFeesAmount($schoolId),
                'active_modules' => $school->modules()->wherePivot('status', 'active')->count(),
            ],
            'recent_activities' => $this->getRecentActivities($schoolId),
            'upcoming_events' => $this->getUpcomingEvents($schoolId),
        ];
    }

    private function getTeacherDashboard($user): array
    {
        // Add null checking for teacher relationship
        if (!$user->teacher) {
            throw new \Exception('Teacher data not found');
        }

        $teacher = $user->teacher;
        $school = $user->getSchool();

        if (!$school) {
            throw new \Exception('Teacher school not found');
        }

        $schoolId = $school->id;

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
        // Add null checking for parent relationship
        if (!$user->parent) {
            throw new \Exception('Parent data not found');
        }

        $parent = $user->parent;
        $children = $parent->students;

        // Get school_id from the first child (assuming all children are from the same school)
        $schoolId = $children->first()?->school_id;

        // Get current academic year if available from request
        $currentAcademicYearId = request('academic_year_id');

        return [
            'parent_info' => [
                'name' => $user->name,
                'children_count' => $children->count(),
            ],
            'children' => $children->map(function ($child) use ($currentAcademicYearId) {
                // Get current class using the proper method
                $currentClass = null;
                if ($currentAcademicYearId) {
                    $currentClass = $child->getCurrentClassForYear($currentAcademicYearId);
                } else {
                    // Fallback: get the first active class
                    $currentClass = $child->currentClass()->first();
                }

                return [
                    'id' => $child->id,
                    'name' => $child->first_name . ' ' . $child->last_name,
                    'class' => $currentClass?->name ?? 'Not Assigned',
                    'today_attendance' => $this->getStudentTodayAttendance($child->id),
                    'pending_fees' => $this->getStudentPendingFees($child->id),
                ];
            }),
            'recent_notifications' => $this->getParentNotifications($parent->id),
            'upcoming_events' => $schoolId ? $this->getUpcomingEvents($schoolId) : [],
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

    /**
     * Get attendance graph data for last 5 days (Admin only)
     * Cached with Octane for better performance
     */
    public function getAttendanceGraphData(): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if user is school admin
            if ($user->user_type !== 'school_admin') {
                return $this->errorResponse('Access denied. Only school admins can access this data.', null, 403);
            }

            // Get school ID
            $schoolId = $user->schoolAdmin?->school_id;

            if (!$schoolId) {
                return $this->errorResponse('School not found for this admin', null, 404);
            }

            // Use the OctaneCache trait for better caching
            $attendanceData = $this->cacheSchoolQuery(
                'attendance_graph_' . now()->format('Y-m-d'),
                function () use ($schoolId) {
                    return $this->calculateAttendanceGraphData($schoolId);
                },
                $this->getCacheTTL('attendance') // 15 minutes
            );

            return $this->successResponse(
                $attendanceData,
                'Attendance graph data retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error retrieving attendance graph data: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while retrieving attendance data', null, 500);
        }
    }

    /**
     * Calculate attendance data for last 5 days
     */
    private function calculateAttendanceGraphData($schoolId): array
    {
        $dates = [];
        $presentData = [];
        $absentData = [];

        // Get last 5 days (excluding weekends if needed)
        for ($i = 4; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dateString = $date->format('Y-m-d');
            $dates[] = $date->format('M d'); // Format: "Aug 25"

            // Get attendance counts for this date
            $attendanceStats = DB::table('attendances')
                ->select('status', DB::raw('COUNT(*) as count'))
                ->where('school_id', $schoolId)
                ->whereDate('date', $dateString)
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $presentData[] = $attendanceStats['present'] ?? 0;
            $absentData[] = $attendanceStats['absent'] ?? 0;
        }

        // Calculate additional metrics
        $totalPresent = array_sum($presentData);
        $totalAbsent = array_sum($absentData);
        $totalRecords = $totalPresent + $totalAbsent;
        $averageAttendanceRate = $totalRecords > 0 ? round(($totalPresent / $totalRecords) * 100, 1) : 0;

        return [
            'chart_data' => [
                'dates' => $dates,
                'present' => $presentData,
                'absent' => $absentData,
            ],
            'summary' => [
                'total_present_5_days' => $totalPresent,
                'total_absent_5_days' => $totalAbsent,
                'average_attendance_rate' => $averageAttendanceRate . '%',
                'best_day' => [
                    'date' => $dates[array_search(max($presentData), $presentData)] ?? 'N/A',
                    'present_count' => max($presentData)
                ],
                'worst_day' => [
                    'date' => $dates[array_search(max($absentData), $absentData)] ?? 'N/A',
                    'absent_count' => max($absentData)
                ]
            ],
            'cached_at' => now()->toISOString(),
            'cache_expires_in_minutes' => 30
        ];
    }
}
