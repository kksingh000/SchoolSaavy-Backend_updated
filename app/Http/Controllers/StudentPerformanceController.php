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

class StudentPerformanceController extends BaseController
{
    /**
     * Get comprehensive student performance report
     */
    public function getPerformanceReport($studentId, Request $request): JsonResponse
    {
        try {
            $month = $request->input('month', Carbon::now()->month);
            $year = $request->input('year', Carbon::now()->year);

            $student = Student::findOrFail($studentId);

            // Get performance data
            $attendanceData = $this->getAttendancePerformance($studentId, $month, $year);
            $assignmentData = $this->getAssignmentPerformance($studentId, $month, $year);
            $overallGrade = $this->calculateOverallGrade($assignmentData);
            $trends = $this->getPerformanceTrends($studentId, $year);

            return $this->successResponse([
                'student' => [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'admission_number' => $student->admission_number,
                    'class' => $student->currentClass()->first()?->name ?? 'Not Assigned',
                ],
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
            ], 'Student performance report generated successfully');
        } catch (\Exception $e) {
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
     * Get performance trends for the year
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
}
