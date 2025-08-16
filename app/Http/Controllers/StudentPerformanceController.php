<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Attendance;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentPerformanceController extends BaseController
{
    /**
     * Get comprehensive student performance report
     */
    public function getPerformanceReport($studentId, Request $request): JsonResponse
    {
        try {
            $startTime = microtime(true);

            $month = $request->input('month', Carbon::now()->month);
            $year = $request->input('year', Carbon::now()->year);

            $student = Student::with([
                'parents.user:id,name,email',
                'currentClass:id,name'
            ])->findOrFail($studentId);
            $student->currentClass = $student->currentClass ? $student->currentClass->first() : null;
            // Get performance data with optimized methods
            $attendanceData = $this->getAttendancePerformance($studentId, $month, $year);
            $assignmentData = $this->getAssignmentPerformance($studentId, $month, $year);
            $overallGrade = $this->calculateOverallGrade($assignmentData);
            $trends = $this->getPerformanceTrendsOptimized($studentId, $year, $student->currentClass);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return $this->successResponse([
                'student' => [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'admission_number' => $student->admission_number,
                    'class' => $student->currentClass->name ?? 'Not Assigned',
                ],
                'parents' => $student->parents->map(function ($parent) {
                    return [
                        'id' => $parent->id,
                        'name' => $parent->user->name ?? '',
                        'email' => $parent->user->email ?? '',
                        'phone' => $parent->phone ?? '',
                        'alternate_phone' => $parent->alternate_phone ?? '',
                        'gender' => $parent->gender ?? '',
                        'occupation' => $parent->occupation ?? '',
                        'address' => $parent->address ?? '',
                        'relationship' => $parent->pivot->relationship ?? $parent->relationship ?? '',
                        'is_primary' => $parent->pivot->is_primary ?? false,
                    ];
                }),
                'period' => [
                    'month' => $month,
                    'year' => $year,
                    'month_name' => Carbon::create($year, $month, 1)->format('F'),
                ],
                'attendance_performance' => $attendanceData,
                'assignment_performance' => $assignmentData,
                'overall_grade' => $overallGrade,
                'performance_trends' => $trends,
                'recommendations' => $this->generateRecommendations($attendanceData, $assignmentData),
                'execution_time_ms' => $executionTime, // Debug info
            ], 'Student performance report generated successfully');
        } catch (\Exception $e) {
            Log::error($e);
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get monthly attendance performance
     */
    private function getAttendancePerformance($studentId, $month, $year)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $attendanceRecords = Attendance::where('student_id', $studentId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $totalDays = $attendanceRecords->count();
        $presentDays = $attendanceRecords->where('status', 'present')->count();
        $absentDays = $attendanceRecords->where('status', 'absent')->count();
        $leaveDays = $attendanceRecords->where('status', 'leave')->count();
        $lateDays = $attendanceRecords->where('status', 'late')->count();

        return [
            'total_school_days' => $totalDays,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'leave_days' => $leaveDays,
            'late_days' => $lateDays,
            'attendance_percentage' => $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0,
            'absent_percentage' => $totalDays > 0 ? round(($absentDays / $totalDays) * 100, 2) : 0,
            'leave_percentage' => $totalDays > 0 ? round(($leaveDays / $totalDays) * 100, 2) : 0,
            'late_percentage' => $totalDays > 0 ? round(($lateDays / $totalDays) * 100, 2) : 0,
            'attendance_grade' => $this->getAttendanceGrade($totalDays > 0 ? ($presentDays / $totalDays) * 100 : 0),
        ];
    }

    /**
     * Get monthly assignment performance
     */
    private function getAssignmentPerformance($studentId, $month, $year)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Get student's current class
        $student = Student::find($studentId);
        $currentClass = $student->currentClass()->first();

        if (!$currentClass) {
            return $this->getEmptyAssignmentPerformance();
        }

        // Get assignments for the month
        $assignments = Assignment::where('class_id', $currentClass->id)
            ->where('status', 'published')
            ->whereBetween('due_date', [$startDate, $endDate])
            ->with(['submissions' => function ($q) use ($studentId) {
                $q->where('student_id', $studentId);
            }])
            ->get();

        $totalAssignments = $assignments->count();
        $submittedAssignments = 0;
        $gradedAssignments = 0;
        $lateSubmissions = 0;
        $totalMarks = 0;
        $obtainedMarks = 0;
        $subjectPerformance = [];

        foreach ($assignments as $assignment) {
            $submission = $assignment->submissions->first();

            if ($submission) {
                $submittedAssignments++;

                if ($submission->is_late_submission) {
                    $lateSubmissions++;
                }

                if ($submission->status === 'graded') {
                    $gradedAssignments++;
                    $totalMarks += $assignment->max_marks;
                    $obtainedMarks += $submission->marks_obtained ?? 0;

                    // Subject-wise performance
                    $subjectName = $assignment->subject->name ?? 'Unknown';
                    if (!isset($subjectPerformance[$subjectName])) {
                        $subjectPerformance[$subjectName] = [
                            'total_assignments' => 0,
                            'submitted' => 0,
                            'graded' => 0,
                            'total_marks' => 0,
                            'obtained_marks' => 0,
                            'average_percentage' => 0,
                        ];
                    }

                    $subjectPerformance[$subjectName]['total_assignments']++;
                    $subjectPerformance[$subjectName]['submitted']++;
                    $subjectPerformance[$subjectName]['graded']++;
                    $subjectPerformance[$subjectName]['total_marks'] += $assignment->max_marks;
                    $subjectPerformance[$subjectName]['obtained_marks'] += $submission->marks_obtained ?? 0;
                }
            }
        }

        // Calculate subject averages
        foreach ($subjectPerformance as $subject => &$data) {
            $data['average_percentage'] = $data['total_marks'] > 0 ?
                round(($data['obtained_marks'] / $data['total_marks']) * 100, 2) : 0;
        }

        $overallPercentage = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0;
        $submissionRate = $totalAssignments > 0 ? round(($submittedAssignments / $totalAssignments) * 100, 2) : 0;

        return [
            'total_assignments' => $totalAssignments,
            'submitted_assignments' => $submittedAssignments,
            'graded_assignments' => $gradedAssignments,
            'pending_assignments' => $totalAssignments - $submittedAssignments,
            'late_submissions' => $lateSubmissions,
            'submission_rate' => $submissionRate,
            'late_submission_rate' => $submittedAssignments > 0 ? round(($lateSubmissions / $submittedAssignments) * 100, 2) : 0,
            'total_marks' => $totalMarks,
            'obtained_marks' => $obtainedMarks,
            'overall_percentage' => $overallPercentage,
            'assignment_grade' => $this->getAssignmentGrade($overallPercentage),
            'subject_performance' => $subjectPerformance,
        ];
    }

    /**
     * Get performance trends for the year (LEGACY - kept for compatibility)
     */
    private function getPerformanceTrends($studentId, $year)
    {
        $trends = [];

        for ($month = 1; $month <= 12; $month++) {
            $attendanceData = $this->getAttendancePerformance($studentId, $month, $year);
            $assignmentData = $this->getAssignmentPerformance($studentId, $month, $year);

            $trends[] = [
                'month' => $month,
                'month_name' => Carbon::create($year, $month, 1)->format('M'),
                'attendance_percentage' => $attendanceData['attendance_percentage'],
                'assignment_percentage' => $assignmentData['overall_percentage'],
                'submission_rate' => $assignmentData['submission_rate'],
            ];
        }

        return $trends;
    }

    /**
     * Get performance trends for the year - OPTIMIZED VERSION
     */
    private function getPerformanceTrendsOptimized($studentId, $year, $currentClass = null)
    {
        // Use passed currentClass or fall back to database query
        if (!$currentClass) {
            $student = Student::find($studentId);
            $currentClass = $student->currentClass()->first();
        }

        if (!$currentClass) {
            // Return empty trends if no class assigned
            $trends = [];
            for ($month = 1; $month <= 12; $month++) {
                $trends[] = [
                    'month' => $month,
                    'month_name' => Carbon::create($year, $month, 1)->format('M'),
                    'attendance_percentage' => 0,
                    'assignment_percentage' => 0,
                    'submission_rate' => 0,
                ];
            }
            return $trends;
        }

        // Get all attendance data for the year in one query
        $attendanceRecords = Attendance::where('student_id', $studentId)
            ->whereYear('date', $year)
            ->get()
            ->groupBy(function ($record) {
                return Carbon::parse($record->date)->month;
            });
        // Get all assignments and submissions for the year in optimized queries
        $assignments = Assignment::where('class_id', $currentClass->id)
            ->where('status', 'published')
            ->whereYear('due_date', $year)
            ->with(['submissions' => function ($query) use ($studentId) {
                $query->where('student_id', $studentId);
            }, 'subject'])
            ->get()
            ->groupBy(function ($assignment) {
                return Carbon::parse($assignment->due_date)->month;
            });

        $trends = [];
        for ($month = 1; $month <= 12; $month++) {
            // Calculate attendance for this month
            $monthAttendance = $attendanceRecords->get($month, collect());
            $attendancePerformance = $this->calculateAttendanceFromRecords($monthAttendance);

            // Calculate assignments for this month
            $monthAssignments = $assignments->get($month, collect());
            $assignmentPerformance = $this->calculateAssignmentFromAssignments($monthAssignments);

            $trends[] = [
                'month' => $month,
                'month_name' => Carbon::create($year, $month, 1)->format('M'),
                'attendance_percentage' => $attendancePerformance['attendance_percentage'],
                'assignment_percentage' => $assignmentPerformance['overall_percentage'],
                'submission_rate' => $assignmentPerformance['submission_rate'],
            ];
        }

        return $trends;
    }

    /**
     * Calculate assignment performance from assignments collection (for trends)
     */
    private function calculateAssignmentFromAssignments($assignments)
    {
        $totalAssignments = $assignments->count();
        $submittedAssignments = 0;
        $gradedAssignments = 0;
        $lateSubmissions = 0;
        $totalMarks = 0;
        $obtainedMarks = 0;

        foreach ($assignments as $assignment) {
            $submission = $assignment->submissions->first();

            if ($submission) {
                $submittedAssignments++;

                if ($submission->is_late_submission) {
                    $lateSubmissions++;
                }

                if ($submission->status === 'graded') {
                    $gradedAssignments++;
                    $totalMarks += $assignment->max_marks ?? 0;
                    $obtainedMarks += $submission->marks_obtained ?? 0;
                }
            }
        }

        $overallPercentage = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0;
        $submissionRate = $totalAssignments > 0 ? round(($submittedAssignments / $totalAssignments) * 100, 2) : 0;

        return [
            'total_assignments' => $totalAssignments,
            'submitted_assignments' => $submittedAssignments,
            'graded_assignments' => $gradedAssignments,
            'overall_percentage' => $overallPercentage,
            'submission_rate' => $submissionRate,
        ];
    }

    /**
     * Calculate overall grade
     */
    private function calculateOverallGrade($assignmentData)
    {
        $assignmentPercentage = $assignmentData['overall_percentage'];

        if ($assignmentPercentage >= 90) return 'A+';
        if ($assignmentPercentage >= 80) return 'A';
        if ($assignmentPercentage >= 70) return 'B+';
        if ($assignmentPercentage >= 60) return 'B';
        if ($assignmentPercentage >= 50) return 'C+';
        if ($assignmentPercentage >= 40) return 'C';
        return 'F';
    }

    /**
     * Get attendance grade
     */
    private function getAttendanceGrade($percentage)
    {
        if ($percentage >= 95) return 'Excellent';
        if ($percentage >= 85) return 'Good';
        if ($percentage >= 75) return 'Satisfactory';
        if ($percentage >= 65) return 'Needs Improvement';
        return 'Poor';
    }

    /**
     * Get assignment grade
     */
    private function getAssignmentGrade($percentage)
    {
        if ($percentage >= 90) return 'Outstanding';
        if ($percentage >= 80) return 'Excellent';
        if ($percentage >= 70) return 'Good';
        if ($percentage >= 60) return 'Satisfactory';
        if ($percentage >= 50) return 'Needs Improvement';
        return 'Poor';
    }

    /**
     * Generate recommendations
     */
    private function generateRecommendations($attendanceData, $assignmentData)
    {
        $recommendations = [];

        // Attendance recommendations
        if ($attendanceData['attendance_percentage'] < 75) {
            $recommendations[] = [
                'type' => 'attendance',
                'priority' => 'high',
                'message' => 'Attendance is below acceptable level. Please ensure regular school attendance.',
            ];
        } elseif ($attendanceData['attendance_percentage'] < 85) {
            $recommendations[] = [
                'type' => 'attendance',
                'priority' => 'medium',
                'message' => 'Consider improving attendance for better academic performance.',
            ];
        }

        // Assignment recommendations
        if ($assignmentData['submission_rate'] < 70) {
            $recommendations[] = [
                'type' => 'assignment',
                'priority' => 'high',
                'message' => 'Many assignments are not being submitted. Please catch up on pending work.',
            ];
        }

        if ($assignmentData['late_submission_rate'] > 30) {
            $recommendations[] = [
                'type' => 'assignment',
                'priority' => 'medium',
                'message' => 'Try to submit assignments on time to avoid late submission penalties.',
            ];
        }

        if ($assignmentData['overall_percentage'] < 60) {
            $recommendations[] = [
                'type' => 'academic',
                'priority' => 'high',
                'message' => 'Academic performance needs improvement. Consider additional study time or tutoring.',
            ];
        }

        // Positive reinforcement
        if ($attendanceData['attendance_percentage'] >= 95 && $assignmentData['overall_percentage'] >= 80) {
            $recommendations[] = [
                'type' => 'positive',
                'priority' => 'low',
                'message' => 'Excellent performance! Keep up the great work.',
            ];
        }

        return $recommendations;
    }

    /**
     * Get empty assignment performance structure
     */
    private function getEmptyAssignmentPerformance()
    {
        return [
            'total_assignments' => 0,
            'submitted_assignments' => 0,
            'graded_assignments' => 0,
            'pending_assignments' => 0,
            'late_submissions' => 0,
            'submission_rate' => 0,
            'late_submission_rate' => 0,
            'total_marks' => 0,
            'obtained_marks' => 0,
            'overall_percentage' => 0,
            'assignment_grade' => 'No Data',
            'subject_performance' => [],
        ];
    }

    /**
     * Get class-wide performance comparison
     */
    public function getClassPerformanceComparison($studentId, Request $request): JsonResponse
    {
        try {
            $month = $request->input('month', Carbon::now()->month);
            $year = $request->input('year', Carbon::now()->year);

            $student = Student::findOrFail($studentId);
            $currentClass = $student->currentClass()->first();

            if (!$currentClass) {
                return $this->errorResponse('Student is not assigned to any class');
            }

            // Get all students in the same class
            $classStudents = $currentClass->students()
                ->where('class_student.is_active', true)
                ->get();

            $studentPerformances = [];
            $totalAttendancePercentage = 0;
            $totalAssignmentPercentage = 0;
            $validStudents = 0;

            foreach ($classStudents as $classStudent) {
                $attendanceData = $this->getAttendancePerformance($classStudent->id, $month, $year);
                $assignmentData = $this->getAssignmentPerformance($classStudent->id, $month, $year);

                $studentPerformances[] = [
                    'student_id' => $classStudent->id,
                    'student_name' => $classStudent->first_name . ' ' . $classStudent->last_name,
                    'attendance_percentage' => $attendanceData['attendance_percentage'],
                    'assignment_percentage' => $assignmentData['overall_percentage'],
                    'is_current_student' => $classStudent->id == $studentId,
                ];

                $totalAttendancePercentage += $attendanceData['attendance_percentage'];
                $totalAssignmentPercentage += $assignmentData['overall_percentage'];
                $validStudents++;
            }

            $classAverageAttendance = $validStudents > 0 ? $totalAttendancePercentage / $validStudents : 0;
            $classAverageAssignment = $validStudents > 0 ? $totalAssignmentPercentage / $validStudents : 0;

            // Find current student's performance
            $currentStudentPerformance = collect($studentPerformances)
                ->where('student_id', $studentId)
                ->first();

            return $this->successResponse([
                'class_info' => [
                    'id' => $currentClass->id,
                    'name' => $currentClass->name,
                    'total_students' => $validStudents,
                ],
                'class_averages' => [
                    'attendance_percentage' => round($classAverageAttendance, 2),
                    'assignment_percentage' => round($classAverageAssignment, 2),
                ],
                'student_ranking' => [
                    'attendance_rank' => $this->getStudentRank($studentPerformances, $studentId, 'attendance_percentage'),
                    'assignment_rank' => $this->getStudentRank($studentPerformances, $studentId, 'assignment_percentage'),
                ],
                'current_student_performance' => $currentStudentPerformance,
                'class_performance' => $studentPerformances,
            ], 'Class performance comparison retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get student rank in class
     */
    private function getStudentRank($performances, $studentId, $field)
    {
        $sorted = collect($performances)->sortByDesc($field)->values();
        $rank = $sorted->search(function ($item) use ($studentId) {
            return $item['student_id'] == $studentId;
        });

        return $rank !== false ? $rank + 1 : null;
    }

    /**
     * Get comprehensive class performance analytics
     */
    public function getClassPerformanceAnalytics($classId, Request $request): JsonResponse
    {
        try {
            $startTime = microtime(true);

            // Support different period types: 'month', 'term', 'year', 'overall'
            $period = $request->input('period', 'month');
            $month = $request->input('month', Carbon::now()->month);
            $year = $request->input('year', Carbon::now()->year);
            $term = $request->input('term'); // 1, 2, or 3 for terms

            // Get class with students
            $class = \App\Models\ClassRoom::with(['activeStudents'])->findOrFail($classId);

            if ($class->activeStudents->isEmpty()) {
                return $this->errorResponse('No active students found in this class');
            }

            // Calculate date range based on period type
            [$startDate, $endDate, $periodLabel] = $this->calculateDateRange($period, $month, $year, $term);

            // Get attendance analytics
            $attendanceAnalytics = $this->getClassAttendanceAnalytics($classId, $startDate, $endDate);

            // Get assignment analytics  
            $assignmentAnalytics = $this->getClassAssignmentAnalytics($classId, $startDate, $endDate);

            // Get individual student performances efficiently
            $studentPerformances = [];
            $totalAttendancePercentage = 0;
            $totalAssignmentPercentage = 0;
            $validStudents = 0;

            // Get all attendance data for the class in one query
            $attendanceData = Attendance::whereIn('student_id', $class->activeStudents->pluck('id'))
                ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get()
                ->groupBy('student_id');

            // Get all assignments and submissions for the class in optimized queries
            $assignments = Assignment::where('class_id', $classId)
                ->where('status', 'published')
                ->whereBetween('due_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->with(['subject'])
                ->get();

            $submissions = AssignmentSubmission::whereIn('assignment_id', $assignments->pluck('id'))
                ->whereIn('student_id', $class->activeStudents->pluck('id'))
                ->get()
                ->groupBy('student_id');

            foreach ($class->activeStudents as $student) {
                // Calculate attendance for this student
                $studentAttendance = $attendanceData->get($student->id, collect());
                $attendancePerformance = $this->calculateAttendanceFromRecords($studentAttendance);

                // Calculate assignments for this student
                $studentSubmissions = $submissions->get($student->id, collect());
                $assignmentPerformance = $this->calculateAssignmentFromSubmissions($assignments, $studentSubmissions);

                $studentPerformances[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->first_name . ' ' . $student->last_name,
                    'admission_number' => $student->admission_number,
                    'attendance_percentage' => $attendancePerformance['attendance_percentage'],
                    'assignment_percentage' => $assignmentPerformance['overall_percentage'],
                    'submission_rate' => $assignmentPerformance['submission_rate'],
                    'overall_grade' => $this->calculateOverallGrade($assignmentPerformance),
                ];

                $totalAttendancePercentage += $attendancePerformance['attendance_percentage'];
                $totalAssignmentPercentage += $assignmentPerformance['overall_percentage'];
                $validStudents++;
            }

            // Calculate class averages
            $classAverageAttendance = $validStudents > 0 ? round($totalAttendancePercentage / $validStudents, 2) : 0;
            $classAverageAssignment = $validStudents > 0 ? round($totalAssignmentPercentage / $validStudents, 2) : 0;

            // Sort students by overall performance
            $sortedStudents = collect($studentPerformances)->sortByDesc('assignment_percentage')->values();

            $executionTime = round((microtime(true) - $startTime) * 1000, 2); // Convert to milliseconds

            return $this->successResponse([
                'class_info' => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'section' => $class->section,
                    'total_students' => $validStudents,
                    'teacher' => $class->classTeacher ? $class->classTeacher->user->name : 'Not Assigned',
                ],
                'period' => [
                    'type' => $period,
                    'label' => $periodLabel,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'month' => $period === 'month' ? $month : null,
                    'year' => $year,
                    'term' => $period === 'term' ? ($term ?: $this->getCurrentTerm()) : null,
                ],
                'class_averages' => [
                    'attendance_percentage' => $classAverageAttendance,
                    'assignment_percentage' => $classAverageAssignment,
                ],
                'attendance_analytics' => $attendanceAnalytics,
                'assignment_analytics' => $assignmentAnalytics,
                'student_performances' => $sortedStudents,
                'performance_distribution' => $this->getPerformanceDistribution($studentPerformances),
                'top_performers' => $sortedStudents->take(5),
                'needs_attention' => $sortedStudents->where('assignment_percentage', '<', 60)->take(5),
                'execution_time_ms' => $executionTime, // Debug info
            ], 'Class performance analytics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get class attendance analytics
     */
    private function getClassAttendanceAnalytics($classId, $startDate, $endDate)
    {
        $attendanceData = DB::table('attendances')
            ->where('class_id', $classId)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_records,
                SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN status = "excused" THEN 1 ELSE 0 END) as excused_count,
                SUM(CASE WHEN status = "leave" THEN 1 ELSE 0 END) as leave_count
            ')
            ->first();

        $totalRecords = $attendanceData->total_records;

        return [
            'total_attendance_records' => $totalRecords,
            'present' => $attendanceData->present_count,
            'absent' => $attendanceData->absent_count,
            'late' => $attendanceData->late_count,
            'excused' => $attendanceData->excused_count,
            'leave' => $attendanceData->leave_count,
            'overall_attendance_percentage' => $totalRecords > 0 ? round(($attendanceData->present_count / $totalRecords) * 100, 2) : 0,
        ];
    }

    /**
     * Get class assignment analytics
     */
    private function getClassAssignmentAnalytics($classId, $startDate, $endDate)
    {
        $assignments = Assignment::where('class_id', $classId)
            ->where('status', 'published')
            ->whereBetween('due_date', [$startDate, $endDate])
            ->with(['submissions'])
            ->get();

        $totalAssignments = $assignments->count();
        $totalSubmissions = $assignments->sum(function ($assignment) {
            return $assignment->submissions->count();
        });

        $submittedCount = $assignments->sum(function ($assignment) {
            return $assignment->submissions->where('status', '!=', 'pending')->count();
        });

        $gradedCount = $assignments->sum(function ($assignment) {
            return $assignment->submissions->where('status', 'graded')->count();
        });

        $lateSubmissions = $assignments->sum(function ($assignment) {
            return $assignment->submissions->where('is_late_submission', true)->count();
        });

        return [
            'total_assignments' => $totalAssignments,
            'total_submissions_expected' => $totalSubmissions,
            'total_submitted' => $submittedCount,
            'total_graded' => $gradedCount,
            'late_submissions' => $lateSubmissions,
            'submission_rate' => $totalSubmissions > 0 ? round(($submittedCount / $totalSubmissions) * 100, 2) : 0,
            'grading_rate' => $submittedCount > 0 ? round(($gradedCount / $submittedCount) * 100, 2) : 0,
            'late_submission_rate' => $submittedCount > 0 ? round(($lateSubmissions / $submittedCount) * 100, 2) : 0,
        ];
    }

    /**
     * Get performance distribution
     */
    private function getPerformanceDistribution($studentPerformances)
    {
        $performances = collect($studentPerformances);

        return [
            'excellent' => $performances->where('assignment_percentage', '>=', 90)->count(),
            'good' => $performances->whereBetween('assignment_percentage', [70, 89])->count(),
            'satisfactory' => $performances->whereBetween('assignment_percentage', [60, 69])->count(),
            'needs_improvement' => $performances->where('assignment_percentage', '<', 60)->count(),
        ];
    }

    /**
     * Calculate date range based on period type
     */
    private function calculateDateRange($period, $month, $year, $term = null)
    {
        switch ($period) {
            case 'month':
                $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                $endDate = Carbon::create($year, $month, 1)->endOfMonth();
                $periodLabel = Carbon::create($year, $month, 1)->format('F Y');
                break;

            case 'term':
                // Assuming 3 terms per year: Jan-Apr, May-Aug, Sep-Dec
                $termRanges = [
                    1 => ['start' => 1, 'end' => 4],   // Term 1: Jan-Apr
                    2 => ['start' => 5, 'end' => 8],   // Term 2: May-Aug  
                    3 => ['start' => 9, 'end' => 12],  // Term 3: Sep-Dec
                ];

                $currentTerm = $term ?: $this->getCurrentTerm();
                $range = $termRanges[$currentTerm] ?? $termRanges[1];

                $startDate = Carbon::create($year, $range['start'], 1)->startOfMonth();
                $endDate = Carbon::create($year, $range['end'], 1)->endOfMonth();
                $periodLabel = "Term {$currentTerm} - {$year}";
                break;

            case 'year':
                $startDate = Carbon::create($year, 1, 1)->startOfYear();
                $endDate = Carbon::create($year, 12, 31)->endOfYear();
                $periodLabel = "Academic Year {$year}";
                break;

            case 'overall':
            default:
                $startDate = Carbon::create(2020, 1, 1); // Start from a reasonable past date
                $endDate = Carbon::now();
                $periodLabel = "Overall Performance";
                break;
        }

        return [$startDate, $endDate, $periodLabel];
    }

    /**
     * Get current term based on current month
     */
    private function getCurrentTerm()
    {
        $currentMonth = Carbon::now()->month;

        if ($currentMonth >= 1 && $currentMonth <= 4) {
            return 1;
        } elseif ($currentMonth >= 5 && $currentMonth <= 8) {
            return 2;
        } else {
            return 3;
        }
    }

    /**
     * Get attendance performance by date range
     */
    private function getAttendancePerformanceByDateRange($studentId, $startDate, $endDate)
    {
        $attendanceRecords = Attendance::where('student_id', $studentId)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        $totalDays = $attendanceRecords->count();
        $presentDays = $attendanceRecords->where('status', 'present')->count();
        $absentDays = $attendanceRecords->where('status', 'absent')->count();
        $leaveDays = $attendanceRecords->where('status', 'leave')->count();
        $lateDays = $attendanceRecords->where('status', 'late')->count();
        $excusedDays = $attendanceRecords->where('status', 'excused')->count();

        return [
            'total_school_days' => $totalDays,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'leave_days' => $leaveDays,
            'late_days' => $lateDays,
            'excused_days' => $excusedDays,
            'attendance_percentage' => $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0,
            'absent_percentage' => $totalDays > 0 ? round(($absentDays / $totalDays) * 100, 2) : 0,
            'leave_percentage' => $totalDays > 0 ? round(($leaveDays / $totalDays) * 100, 2) : 0,
            'late_percentage' => $totalDays > 0 ? round(($lateDays / $totalDays) * 100, 2) : 0,
            'excused_percentage' => $totalDays > 0 ? round(($excusedDays / $totalDays) * 100, 2) : 0,
            'attendance_grade' => $this->getAttendanceGrade($totalDays > 0 ? ($presentDays / $totalDays) * 100 : 0),
        ];
    }

    /**
     * Get assignment performance by date range
     */
    private function getAssignmentPerformanceByDateRange($studentId, $startDate, $endDate)
    {
        // Get student's current class
        $student = Student::find($studentId);
        $currentClass = $student->currentClass()->first();

        if (!$currentClass) {
            return $this->getEmptyAssignmentPerformance();
        }

        // Get assignments for the date range with submissions eagerly loaded
        $assignments = Assignment::where('class_id', $currentClass->id)
            ->where('status', 'published')
            ->whereBetween('due_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with(['submissions' => function ($query) use ($studentId) {
                $query->where('student_id', $studentId);
            }, 'subject'])
            ->get();

        $totalAssignments = $assignments->count();
        $submittedAssignments = 0;
        $gradedAssignments = 0;
        $lateSubmissions = 0;
        $totalMarks = 0;
        $obtainedMarks = 0;
        $subjectPerformance = [];

        foreach ($assignments as $assignment) {
            $submission = $assignment->submissions->first(); // Now using eager loaded relationship

            if ($submission) {
                $submittedAssignments++;

                if ($submission->submitted_at > $assignment->due_date) {
                    $lateSubmissions++;
                }

                if ($submission->marks_obtained !== null) {
                    $gradedAssignments++;
                    $totalMarks += $assignment->total_marks ?? 0;
                    $obtainedMarks += $submission->marks_obtained ?? 0;

                    // Subject-wise performance
                    $subjectName = $assignment->subject->name ?? 'Unknown';
                    if (!isset($subjectPerformance[$subjectName])) {
                        $subjectPerformance[$subjectName] = [
                            'total_marks' => 0,
                            'obtained_marks' => 0,
                            'assignments_count' => 0,
                        ];
                    }

                    $subjectPerformance[$subjectName]['total_marks'] += $assignment->total_marks ?? 0;
                    $subjectPerformance[$subjectName]['obtained_marks'] += $submission->marks_obtained ?? 0;
                    $subjectPerformance[$subjectName]['assignments_count']++;
                }
            }
        }

        // Calculate subject averages
        foreach ($subjectPerformance as $subject => &$data) {
            $data['average_percentage'] = $data['total_marks'] > 0 ?
                round(($data['obtained_marks'] / $data['total_marks']) * 100, 2) : 0;
        }

        $overallPercentage = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0;
        $submissionRate = $totalAssignments > 0 ? round(($submittedAssignments / $totalAssignments) * 100, 2) : 0;

        return [
            'total_assignments' => $totalAssignments,
            'submitted_assignments' => $submittedAssignments,
            'graded_assignments' => $gradedAssignments,
            'pending_assignments' => $totalAssignments - $submittedAssignments,
            'late_submissions' => $lateSubmissions,
            'submission_rate' => $submissionRate,
            'late_submission_rate' => $submittedAssignments > 0 ? round(($lateSubmissions / $submittedAssignments) * 100, 2) : 0,
            'total_marks' => $totalMarks,
            'obtained_marks' => $obtainedMarks,
            'overall_percentage' => $overallPercentage,
            'assignment_grade' => $this->getAssignmentGrade($overallPercentage),
            'subject_performance' => $subjectPerformance,
        ];
    }

    /**
     * Calculate attendance performance from attendance records collection
     */
    private function calculateAttendanceFromRecords($attendanceRecords)
    {
        $totalDays = $attendanceRecords->count();
        $presentDays = $attendanceRecords->where('status', 'present')->count();
        $absentDays = $attendanceRecords->where('status', 'absent')->count();
        $leaveDays = $attendanceRecords->where('status', 'leave')->count();
        $lateDays = $attendanceRecords->where('status', 'late')->count();
        $excusedDays = $attendanceRecords->where('status', 'excused')->count();

        return [
            'total_school_days' => $totalDays,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'leave_days' => $leaveDays,
            'late_days' => $lateDays,
            'excused_days' => $excusedDays,
            'attendance_percentage' => $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0,
            'absent_percentage' => $totalDays > 0 ? round(($absentDays / $totalDays) * 100, 2) : 0,
            'leave_percentage' => $totalDays > 0 ? round(($leaveDays / $totalDays) * 100, 2) : 0,
            'late_percentage' => $totalDays > 0 ? round(($lateDays / $totalDays) * 100, 2) : 0,
            'excused_percentage' => $totalDays > 0 ? round(($excusedDays / $totalDays) * 100, 2) : 0,
            'attendance_grade' => $this->getAttendanceGrade($totalDays > 0 ? ($presentDays / $totalDays) * 100 : 0),
        ];
    }

    /**
     * Calculate assignment performance from assignments and submissions collections
     */
    private function calculateAssignmentFromSubmissions($assignments, $studentSubmissions)
    {
        $totalAssignments = $assignments->count();
        $submittedAssignments = 0;
        $gradedAssignments = 0;
        $lateSubmissions = 0;
        $totalMarks = 0;
        $obtainedMarks = 0;
        $subjectPerformance = [];

        // Create a map of submissions by assignment_id for quick lookup
        $submissionMap = $studentSubmissions->keyBy('assignment_id');

        foreach ($assignments as $assignment) {
            $submission = $submissionMap->get($assignment->id);

            if ($submission) {
                $submittedAssignments++;

                if ($submission->submitted_at > $assignment->due_date) {
                    $lateSubmissions++;
                }

                if ($submission->marks_obtained !== null) {
                    $gradedAssignments++;
                    $totalMarks += $assignment->total_marks ?? 0;
                    $obtainedMarks += $submission->marks_obtained ?? 0;

                    // Subject-wise performance
                    $subjectName = $assignment->subject->name ?? 'Unknown';
                    if (!isset($subjectPerformance[$subjectName])) {
                        $subjectPerformance[$subjectName] = [
                            'total_marks' => 0,
                            'obtained_marks' => 0,
                            'assignments_count' => 0,
                        ];
                    }

                    $subjectPerformance[$subjectName]['total_marks'] += $assignment->total_marks ?? 0;
                    $subjectPerformance[$subjectName]['obtained_marks'] += $submission->marks_obtained ?? 0;
                    $subjectPerformance[$subjectName]['assignments_count']++;
                }
            }
        }

        // Calculate subject averages
        foreach ($subjectPerformance as $subject => &$data) {
            $data['average_percentage'] = $data['total_marks'] > 0 ?
                round(($data['obtained_marks'] / $data['total_marks']) * 100, 2) : 0;
        }

        $overallPercentage = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0;
        $submissionRate = $totalAssignments > 0 ? round(($submittedAssignments / $totalAssignments) * 100, 2) : 0;

        return [
            'total_assignments' => $totalAssignments,
            'submitted_assignments' => $submittedAssignments,
            'graded_assignments' => $gradedAssignments,
            'pending_assignments' => $totalAssignments - $submittedAssignments,
            'late_submissions' => $lateSubmissions,
            'submission_rate' => $submissionRate,
            'late_submission_rate' => $submittedAssignments > 0 ? round(($lateSubmissions / $submittedAssignments) * 100, 2) : 0,
            'total_marks' => $totalMarks,
            'obtained_marks' => $obtainedMarks,
            'overall_percentage' => $overallPercentage,
            'assignment_grade' => $this->getAssignmentGrade($overallPercentage),
            'subject_performance' => $subjectPerformance,
        ];
    }
}
