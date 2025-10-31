<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Parents;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\AssignmentSubmission;
use App\Models\AssessmentResult;
use App\Models\Event;
use App\Models\GalleryAlbum;
use App\Models\GalleryMedia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Traits\GeneratesFileUrls;
use App\Http\Resources\ParentStudentResource;

class ParentService
{
    use GeneratesFileUrls;

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
        // Get total fees from installments
        $totalFees = DB::table('fee_installments')
            ->join('student_fee_plans', 'fee_installments.student_fee_plan_id', '=', 'student_fee_plans.id')
            ->where('student_fee_plans.student_id', $studentId)
            ->sum('fee_installments.amount');
            
        // Get paid amount from payments table (new system)
        $paidAmount = DB::table('payments')
            ->where('student_id', $studentId)
            ->where('status', 'Success')
            ->sum('amount');

        $pendingAmount = $totalFees - $paidAmount;
        
        // Get overdue fees
        $overdueFees = DB::table('fee_installments')
            ->join('student_fee_plans', 'fee_installments.student_fee_plan_id', '=', 'student_fee_plans.id')
            ->where('student_fee_plans.student_id', $studentId)
            ->where('fee_installments.due_date', '<', Carbon::now())
            ->whereIn('fee_installments.status', ['Pending', 'Overdue'])
            ->sum(DB::raw('fee_installments.amount - COALESCE(fee_installments.paid_amount, 0)'));

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
        // Use JOIN query for better performance instead of Octane concurrency
        // Since this is simple data mapping, a single optimized query is more efficient
        $students = DB::table('parent_student')
            ->join('students', 'parent_student.student_id', '=', 'students.id')
            ->join('schools', 'students.school_id', '=', 'schools.id')
            ->leftJoin('class_student', function ($join) {
                $join->on('students.id', '=', 'class_student.student_id')
                    ->where('class_student.is_active', true);
            })
            ->leftJoin('classes', 'class_student.class_id', '=', 'classes.id')
            ->where('parent_student.parent_id', $parentId)
            ->where('students.is_active', true)
            ->select([
                // Basic student information
                'students.id',
                'students.admission_number',
                'class_student.roll_number',
                'students.first_name',
                'students.last_name',
                'students.date_of_birth',
                'students.gender',
                'students.admission_date',
                'students.blood_group',
                'students.profile_photo',
                'students.address',
                'students.phone',
                'students.is_active',
                'students.created_at',
                'students.updated_at',

                // Class information
                'classes.id as class_id',
                'classes.name as class_name',
                'classes.section as class_section',

                // School information  
                'schools.id as school_id',
                'schools.name as school_name',

                // Computed full name
                DB::raw("CONCAT(students.first_name, ' ', students.last_name) as full_name")
            ])
            ->get();

        // Use Laravel Resource to format the response
        return ParentStudentResource::collection($students)->toArray(request());
    }

    /**
     * Clear cache for specific parent-student combination
     */
    public function clearStudentStatsCache($parentId, $studentId): void
    {
        $cacheKey = "parent_stats_{$parentId}_{$studentId}";
        Cache::forget($cacheKey);
    }

    /**
     * Get student attendance records with proper timezone handling
     */
    public function getStudentAttendance($parentId, $studentId, $month = null, $year = null, $limit = 30): array
    {
        // Verify relationship first
        if (!$this->verifyParentStudentRelationship($parentId, $studentId)) {
            throw new \Exception('Student does not belong to this parent.');
        }

        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        // Get student for school isolation
        $student = Student::findOrFail($studentId);

        // Create date range with proper timezone handling
        $startDate = Carbon::create($year, $month, 1, 0, 0, 0, config('app.timezone'))->utc();
        $endDate = Carbon::create($year, $month, 1, 23, 59, 59, config('app.timezone'))->endOfMonth()->utc();

        // Get attendance records with school isolation
        $attendance = Attendance::where('student_id', $studentId)
            ->where('school_id', $student->school_id)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->orderBy('date', 'desc')
            ->limit($limit)
            ->get(['date', 'status', 'check_in_time', 'check_out_time', 'remarks']);

        // Transform dates back to local timezone for display
        $attendance->transform(function ($record) {
            $record->date = Carbon::parse($record->date)->setTimezone(config('app.timezone'));
            return $record;
        });

        return [
            'attendance_records' => $attendance,
            'month' => $month,
            'year' => $year,
            'total_records' => $attendance->count(),
        ];
    }

    /**
     * Get student assignments with submission status and pagination
     */
    public function getStudentAssignments($parentId, $studentId, $status = null, $perPage = 15): array
    {
        // Verify relationship first
        if (!$this->verifyParentStudentRelationship($parentId, $studentId)) {
            throw new \Exception('Student does not belong to this parent.');
        }

        // Get student's current class
        $student = Student::with('currentClass')->findOrFail($studentId);
        $currentClass = $student->currentClass()->first();

        if (!$currentClass) {
            return [
                'assignments' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => null,
                    'to' => null,
                    'has_more_pages' => false,
                ],
                'filtered_by' => $status ? ['status' => $status] : null,
                'message' => 'Student is not assigned to any class.',
            ];
        }

        // Build base query with school isolation
        $query = Assignment::where('class_id', $currentClass->id)
            ->where('school_id', $student->school_id)
            ->where('is_active', true)
            ->with(['subject:id,name', 'teacher.user:id,name'])
            ->orderBy('due_date', 'desc');

        // Add status filtering at database level where possible
        if ($status) {
            switch ($status) {
                case 'submitted':
                    $query->whereHas('submissions', function ($q) use ($studentId) {
                        $q->where('student_id', $studentId);
                    });
                    break;

                case 'pending':
                    $query->whereDoesntHave('submissions', function ($q) use ($studentId) {
                        $q->where('student_id', $studentId);
                    })->where('due_date', '>=', now());
                    break;

                case 'overdue':
                    $query->whereDoesntHave('submissions', function ($q) use ($studentId) {
                        $q->where('student_id', $studentId);
                    })->where('due_date', '<', now());
                    break;

                case 'graded':
                    $query->whereHas('submissions', function ($q) use ($studentId) {
                        $q->where('student_id', $studentId)
                            ->where('status', 'graded');
                    });
                    break;
            }
        }

        // Use Laravel's built-in pagination
        $paginatedAssignments = $query->paginate($perPage);

        // Transform assignments with submission status
        $assignments = $paginatedAssignments->getCollection()->map(function ($assignment) use ($studentId) {
            $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
                ->where('student_id', $studentId)
                ->first();

            $isOverdue = !$submission && $assignment->due_date < now();
            $assignmentStatus = $submission ? $submission->status : ($isOverdue ? 'overdue' : 'pending');

            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'subject' => $assignment->subject->name ?? 'N/A',
                'teacher' => $assignment->teacher->user->name ?? 'N/A',
                'assigned_date' => $assignment->assigned_date->format('Y-m-d'),
                'due_date' => $assignment->due_date->format('Y-m-d'),
                'max_marks' => $assignment->max_marks,
                'status' => $assignmentStatus,
                'submission' => $submission ? [
                    'submitted_at' => $submission->submitted_at?->format('Y-m-d H:i:s'),
                    'marks_obtained' => $submission->marks_obtained,
                    'grade_percentage' => $submission->grade_percentage,
                    'teacher_feedback' => $submission->teacher_feedback,
                    'is_late_submission' => $submission->is_late_submission,
                ] : null,
            ];
        });

        return [
            'assignments' => $assignments->toArray(),
            'pagination' => [
                'current_page' => $paginatedAssignments->currentPage(),
                'per_page' => $paginatedAssignments->perPage(),
                'total' => $paginatedAssignments->total(),
                'last_page' => $paginatedAssignments->lastPage(),
                'from' => $paginatedAssignments->firstItem(),
                'to' => $paginatedAssignments->lastItem(),
                'has_more_pages' => $paginatedAssignments->hasMorePages(),
            ],
            'filtered_by' => $status ? ['status' => $status] : null,
        ];
    }

    /**
     * Get detailed assignment information for a student
     */
    public function getAssignmentDetails($parentId, $studentId, $assignmentId): array
    {
        // Verify relationship first
        if (!$this->verifyParentStudentRelationship($parentId, $studentId)) {
            throw new \Exception('Student does not belong to this parent.');
        }

        // Get student for school isolation
        $student = Student::with('currentClass')->findOrFail($studentId);

        // Get assignment with all related data
        $assignment = Assignment::where('id', $assignmentId)
            ->where('school_id', $student->school_id)
            ->where('is_active', true)
            ->with([
                'subject:id,name',
                'teacher.user:id,name',
                'class:id,name,section'
            ])
            ->first();

        if (!$assignment) {
            throw new \Exception('Assignment not found or access denied.');
        }

        // Verify student has access to this assignment (same class)
        $currentClass = $student->currentClass()->first();
        if (!$currentClass || $assignment->class_id !== $currentClass->id) {
            throw new \Exception('Student does not have access to this assignment.');
        }

        // Get student's submission for this assignment
        $submission = AssignmentSubmission::where('assignment_id', $assignmentId)
            ->where('student_id', $studentId)
            ->first();

        // Calculate assignment status
        $isOverdue = !$submission && $assignment->due_date < now();
        $assignmentStatus = $submission ? $submission->status : ($isOverdue ? 'overdue' : 'pending');

        // Get class performance statistics
        $classPerformance = $assignment->getClassPerformance();

        return [
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'instructions' => $assignment->instructions,
                'type' => $assignment->type,
                'status' => $assignment->status,
                'assigned_date' => $assignment->assigned_date->format('Y-m-d'),
                'due_date' => $assignment->due_date->format('Y-m-d'),
                'due_time' => $assignment->due_time ? $assignment->due_time->format('H:i') : null,
                'max_marks' => $assignment->max_marks,
                'allow_late_submission' => $assignment->allow_late_submission,
                'grading_criteria' => $assignment->grading_criteria,
                'attachments' => $this->formatAssignmentAttachments($assignment->attachments ?? []),
                'is_overdue' => $assignment->is_overdue,
                'days_until_due' => $assignment->days_until_due,
                'can_accept_submissions' => $assignment->canAcceptSubmissions(),
            ],
            'subject' => [
                'id' => $assignment->subject->id,
                'name' => $assignment->subject->name,
            ],
            'teacher' => [
                'id' => $assignment->teacher->user->id,
                'name' => $assignment->teacher->user->name,
            ],
            'class' => [
                'id' => $assignment->class->id,
                'name' => $assignment->class->name,
                'section' => $assignment->class->section,
                'full_name' => $assignment->class->name . ' - ' . $assignment->class->section,
            ],
            'student_status' => $assignmentStatus,
            'submission' => $submission ? [
                'id' => $submission->id,
                'status' => $submission->status,
                'submitted_at' => $submission->submitted_at?->format('Y-m-d H:i:s'),
                'submission_text' => $submission->content,
                'attachments' => $this->formatSubmissionAttachments($submission->attachments ?? []),
                'attachment_count' => count($submission->attachments ?? []),
                'has_attachments' => !empty($submission->attachments),
                'has_text_content' => !empty($submission->content),
                'marks_obtained' => $submission->marks_obtained,
                'grade_percentage' => $submission->grade_percentage,
                'grade_letter' => $submission->grade_letter,
                'teacher_feedback' => $submission->teacher_feedback,
                'grading_details' => $submission->grading_details,
                'is_late_submission' => $submission->is_late_submission,
                'graded_at' => $submission->graded_at?->format('Y-m-d H:i:s'),
                'graded_by' => $submission->graded_by,
                'created_at' => $submission->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $submission->updated_at->format('Y-m-d H:i:s'),
                'submission_summary' => $this->generateSubmissionSummary($submission),
            ] : null,
            'class_performance' => $classPerformance,
            'submission_stats' => $assignment->submission_stats,
        ];
    }

    /**
     * Format submission attachments with detailed file information
     */
    private function formatSubmissionAttachments(array $attachments): array
    {
        if (empty($attachments)) {
            return [];
        }

        $formattedAttachments = [];

        foreach ($attachments as $attachment) {
            // Handle both old and new attachment formats
            if (is_string($attachment)) {
                // Old format: just file path
                $fileInfo = $this->getFileInfoFromPath($attachment);
                $formattedAttachments[] = array_merge([
                    'name' => basename($attachment),
                    'original_name' => basename($attachment),
                    'filename' => basename($attachment),
                    'url' => $this->buildFileUrl($attachment),
                    'path' => $attachment,
                    'type' => pathinfo($attachment, PATHINFO_EXTENSION),
                    'mime_type' => $this->getMimeTypeFromExtension(pathinfo($attachment, PATHINFO_EXTENSION)),
                    'size' => null,
                    'size_human' => 'Unknown',
                    'is_image' => $this->isImageFile(pathinfo($attachment, PATHINFO_EXTENSION)),
                    'uploaded_at' => null,
                    'has_thumbnail' => false,
                ], $fileInfo);
            } elseif (is_array($attachment)) {
                // New format: detailed file information from FileUploadController
                $attachmentUrl = $attachment['url'] ?? '';
                $attachmentPath = $attachment['path'] ?? $this->extractPathFromUrl($attachmentUrl);
                $attachmentName = $attachment['name'] ?? $attachment['filename'] ?? basename($attachmentPath);
                $attachmentType = $attachment['type'] ?? pathinfo($attachmentName, PATHINFO_EXTENSION);

                // Get additional file info if missing
                $fileInfo = [];
                if (!isset($attachment['size']) || !isset($attachment['mime_type'])) {
                    $fileInfo = $this->getFileInfoFromPath($attachmentPath);
                }

                $formattedAttachments[] = [
                    'name' => $attachmentName,
                    'original_name' => $attachment['name'] ?? $attachmentName,
                    'filename' => $attachment['filename'] ?? basename($attachmentPath),
                    'url' => $attachmentUrl ?: $this->buildFileUrl($attachmentPath),
                    'path' => $attachmentPath,
                    'type' => $attachmentType,
                    'mime_type' => $attachment['mime_type'] ?? $fileInfo['mime_type'] ?? $this->getMimeTypeFromExtension($attachmentType),
                    'size' => $attachment['size'] ?? $fileInfo['size'] ?? null,
                    'size_human' => isset($attachment['size']) ? $this->formatBytes($attachment['size']) : (isset($fileInfo['size']) ? $this->formatBytes($fileInfo['size']) : ($attachment['size_human'] ?? 'Unknown')),
                    'is_image' => $attachment['is_image'] ?? $this->isImageFile($attachmentType),
                    'uploaded_at' => $attachment['uploaded_at'] ?? $attachment['created_at'] ?? null,
                    'has_thumbnail' => $attachment['thumbnail_queued'] ?? $attachment['has_thumbnail'] ?? false,
                ];
            }
        }

        return $formattedAttachments;
    }

    /**
     * Format assignment attachments with full URLs
     */
    private function formatAssignmentAttachments(array $attachments): array
    {
        if (empty($attachments)) {
            return [];
        }

        $formattedAttachments = [];

        foreach ($attachments as $attachment) {
            // Handle both old and new attachment formats
            if (is_string($attachment)) {
                // Old format: just file path
                $formattedAttachments[] = [
                    'name' => basename($attachment),
                    'url' => $this->buildFileUrl($attachment),
                    'type' => pathinfo($attachment, PATHINFO_EXTENSION),
                ];
            } elseif (is_array($attachment)) {
                // New format: detailed file information
                $attachmentUrl = $attachment['url'] ?? '';
                $attachmentPath = $attachment['path'] ?? $this->extractPathFromUrl($attachmentUrl);
                $attachmentName = $attachment['name'] ?? $attachment['filename'] ?? basename($attachmentPath);
                $attachmentType = $attachment['type'] ?? pathinfo($attachmentName, PATHINFO_EXTENSION);

                $formattedAttachments[] = [
                    'name' => $attachmentName,
                    'url' => $attachmentUrl ? $this->buildFileUrl($attachmentUrl) : $this->buildFileUrl($attachmentPath),
                    'type' => $attachmentType,
                ];
            }
        }

        return $formattedAttachments;
    }

    /**
     * Get file information from storage path (fallback method)
     */
    private function getFileInfoFromPath(string $filePath): array
    {
        if (empty($filePath)) {
            return [];
        }

        try {
            $uploadDisk = config('filesystems.gallery_disk', 'public');
            $cleanPath = ltrim($filePath, '/');

            if ($uploadDisk === 's3') {
                // Try to get info from S3
                if (Storage::disk('s3')->exists($cleanPath)) {
                    $size = Storage::disk('s3')->size($cleanPath);
                    return [
                        'size' => $size,
                        'exists' => true,
                    ];
                }
            } else {
                // Try to get info from local storage
                if (Storage::disk('public')->exists($cleanPath)) {
                    $size = Storage::disk('public')->size($cleanPath);
                    return [
                        'size' => $size,
                        'exists' => true,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Could not get file info from path: ' . $filePath, ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Extract storage path from S3 or local URL
     */
    private function extractPathFromUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }

        // Handle S3 URLs
        if (str_contains($url, '.s3.') && str_contains($url, '.amazonaws.com/')) {
            // Extract path from S3 URL: https://bucket.s3.region.amazonaws.com/path/to/file
            $urlParts = parse_url($url);
            return ltrim($urlParts['path'] ?? '', '/');
        }

        // Handle local storage URLs
        if (str_contains($url, '/storage/')) {
            // Extract path from local URL: http://domain.com/storage/path/to/file
            $storagePart = explode('/storage/', $url);
            return $storagePart[1] ?? '';
        }

        // If it's already a relative path, return as is
        return ltrim($url, '/');
    }

    /**
     * Generate submission summary for parent view
     */
    private function generateSubmissionSummary($submission): array
    {
        $summary = [
            'type' => 'none',
            'description' => 'No submission yet',
            'details' => [],
        ];

        if (!$submission || $submission->status === 'pending') {
            return $summary;
        }

        $hasText = !empty($submission->content);
        $hasFiles = !empty($submission->attachments);
        $fileCount = count($submission->attachments ?? []);

        if ($hasText && $hasFiles) {
            $summary['type'] = 'text_and_files';
            $summary['description'] = "Text content with {$fileCount} file(s)";
            $summary['details'] = [
                'content_length' => strlen($submission->content),
                'content_preview' => substr($submission->content, 0, 100) . (strlen($submission->content) > 100 ? '...' : ''),
                'file_count' => $fileCount,
                'file_types' => $this->getFileTypesFromAttachments($submission->attachments ?? []),
            ];
        } elseif ($hasText) {
            $summary['type'] = 'text_only';
            $summary['description'] = 'Text submission';
            $summary['details'] = [
                'content_length' => strlen($submission->content),
                'content_preview' => substr($submission->content, 0, 150) . (strlen($submission->content) > 150 ? '...' : ''),
                'word_count' => str_word_count($submission->content),
            ];
        } elseif ($hasFiles) {
            $summary['type'] = 'files_only';
            $summary['description'] = "{$fileCount} file(s) uploaded";
            $summary['details'] = [
                'file_count' => $fileCount,
                'file_types' => $this->getFileTypesFromAttachments($submission->attachments ?? []),
                'total_size' => $this->calculateTotalFileSize($submission->attachments ?? []),
            ];
        }

        return $summary;
    }

    /**
     * Check if file is an image based on extension
     */
    private function isImageFile(string $extension): bool
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
        return in_array(strtolower($extension), $imageExtensions);
    }

    /**
     * Get MIME type from file extension
     */
    private function getMimeTypeFromExtension(string $extension): string
    {
        $mimeTypes = [
            // Images
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',

            // Documents
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',

            // Archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',

            // Others
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
        ];

        $extension = strtolower($extension);
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Extract file types from attachments array
     */
    private function getFileTypesFromAttachments(array $attachments): array
    {
        $types = [];

        foreach ($attachments as $attachment) {
            if (is_string($attachment)) {
                $types[] = pathinfo($attachment, PATHINFO_EXTENSION);
            } elseif (is_array($attachment)) {
                $types[] = $attachment['type'] ?? pathinfo($attachment['path'] ?? '', PATHINFO_EXTENSION);
            }
        }

        return array_unique(array_filter($types));
    }

    /**
     * Calculate total file size from attachments
     */
    private function calculateTotalFileSize(array $attachments): string
    {
        $totalBytes = 0;

        foreach ($attachments as $attachment) {
            if (is_array($attachment) && isset($attachment['size'])) {
                $totalBytes += (int) $attachment['size'];
            }
        }

        return $this->formatBytes($totalBytes);
    }

    /**
     * Get gallery albums for a student (with thumbnails and counts)
     * Returns paginated albums that the student has access to
     */
    public function getStudentGalleryAlbums($parentId, $studentId, $perPage = 15)
    {
        // Verify parent-student relationship
        if (!$this->verifyParentStudentRelationship($parentId, $studentId)) {
            throw new \Exception('Student does not belong to this parent.');
        }

        // Get student with class information for school isolation
        $student = Student::with(['currentClass', 'school'])
            ->findOrFail($studentId);

        $schoolId = $student->school_id;
        $currentClass = $student->currentClass()->first();

        // Build the query for albums student has access to
        $albumsQuery = GalleryAlbum::with([
            'class:id,name,section',
            'event:id,title,event_date',
            'creator:id,name'
        ])
            ->withCount([
                'media as total_media_count' => function ($query) {
                    $query->where('status', 'active');
                },
                'media as photos_count' => function ($query) {
                    $query->where('type', 'photo')->where('status', 'active');
                },
                'media as videos_count' => function ($query) {
                    $query->where('type', 'video')->where('status', 'active');
                },
                'media as documents_count' => function ($query) {
                    $query->where('type', 'document')->where('status', 'active');
                }
            ])
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('is_public', true);

        // Only include albums for student's class if they have one
        if ($currentClass) {
            $albumsQuery->where('class_id', $currentClass->id);
        } else {
            // If student has no class, return empty result
            $albumsQuery->whereRaw('1 = 0'); // This will return no results
        }

        $albumsQuery->orderBy('event_date', 'desc');

        // Get paginated albums
        $albums = $albumsQuery->paginate($perPage);

        // For each album, get up to 3 thumbnail images
        $albums->getCollection()->transform(function ($album) {
            // Get up to 3 featured or recent photos for thumbnails
            $thumbnails = $album->media()
                ->where('type', 'photo')
                ->where('status', 'active')
                ->orderByRaw('is_featured DESC, sort_order ASC, created_at DESC')
                ->limit(3)
                ->get(['id', 'file_path', 'thumbnail_path', 'title']);

            $album->thumbnails = $thumbnails->map(function ($media) {
                // Get proper thumbnail URLs with sizes
                $thumbnailUrls = $this->getGeneratedThumbnailUrls($media->file_path);

                return [
                    'id' => $media->id,
                    'title' => $media->title,
                    'url' => $this->buildFileUrl($media->file_path), // Full size URL
                    'thumbnail_url' => $thumbnailUrls['small'] ?? $this->buildFileUrl($media->file_path), // Small thumbnail (150px)
                    'thumbnail_urls' => $thumbnailUrls, // All available sizes
                ];
            });

            // Add formatted data
            $album->album_type = 'class'; // All albums are class-specific
            $album->class_name = $album->class ? $album->class->name . ' ' . $album->class->section : null;
            $album->event_title = $album->event ? $album->event->title : null;
            $album->creator_name = $album->creator ? $album->creator->name : null;

            // Clean up relations to avoid over-loading the response
            unset($album->class, $album->event, $album->creator);

            return $album;
        });

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->first_name . ' ' . $student->last_name,
                'admission_number' => $student->admission_number,
                'class' => $currentClass ? [
                    'id' => $currentClass->id,
                    'name' => $currentClass->name,
                    'section' => $currentClass->section,
                ] : null,
            ],
            'albums' => [
                'data' => $albums->items(),
                'pagination' => [
                    'current_page' => $albums->currentPage(),
                    'per_page' => $albums->perPage(),
                    'total' => $albums->total(),
                    'last_page' => $albums->lastPage(),
                    'from' => $albums->firstItem(),
                    'to' => $albums->lastItem(),
                    'has_more_pages' => $albums->hasMorePages(),
                ]
            ]
        ];
    }

    /**
     * Get media items from a specific gallery album
     * Returns paginated media items with filtering options
     */
    public function getStudentGalleryAlbumMedia($parentId, $studentId, $albumId, $mediaType = null, $perPage = 20)
    {
        // Verify parent-student relationship
        if (!$this->verifyParentStudentRelationship($parentId, $studentId)) {
            throw new \Exception('Student does not belong to this parent.');
        }

        // Get student for school isolation
        $student = Student::findOrFail($studentId);
        $schoolId = $student->school_id;

        // Get and verify album access
        $album = GalleryAlbum::with(['class:id,name,section', 'event:id,title'])
            ->where('id', $albumId)
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('is_public', true)
            ->first();

        if (!$album) {
            throw new \Exception('Album not found or not accessible.');
        }

        // Check if student has access to this album
        $currentClass = $student->currentClass()->first();
        if (!$currentClass || $album->class_id !== $currentClass->id) {
            throw new \Exception('Student does not have access to this album.');
        }

        // Build media query
        $mediaQuery = $album->media()
            ->where('status', 'active')
            ->orderByRaw('is_featured DESC, sort_order ASC, created_at DESC');

        // Apply media type filter if specified
        if ($mediaType) {
            $mediaQuery->where('type', $mediaType);
        }

        // Get paginated media
        $media = $mediaQuery->paginate($perPage);

        // Transform media items for response
        $media->getCollection()->transform(function ($mediaItem) {
            // Get proper thumbnail URLs with sizes  
            $thumbnailUrls = $this->getGeneratedThumbnailUrls($mediaItem->file_path);

            return [
                'id' => $mediaItem->id,
                'type' => $mediaItem->type,
                'title' => $mediaItem->title,
                'description' => $mediaItem->description,
                'file_url' => $this->buildFileUrl($mediaItem->file_path),
                'thumbnail_url' => $thumbnailUrls['small'] ?? $this->buildFileUrl($mediaItem->file_path), // Small thumbnail (150px)
                'thumbnail_urls' => $thumbnailUrls, // All available sizes
                'file_size' => $mediaItem->file_size,
                'file_size_formatted' => $mediaItem->file_size_formatted,
                'dimensions' => $mediaItem->dimensions,
                'duration' => $mediaItem->duration,
                'duration_formatted' => $mediaItem->duration_formatted,
                'views_count' => $mediaItem->views_count,
                'is_featured' => $mediaItem->is_featured,
                'created_at' => $mediaItem->created_at,
                'metadata' => $mediaItem->metadata,
            ];
        });

        // Transform album cover image with media URL
        $albumCoverImage = $album->cover_image ? $this->buildFileUrl($album->cover_image) : null;

        return [
            'album' => [
                'id' => $album->id,
                'title' => $album->title,
                'description' => $album->description,
                'event_date' => $album->event_date,
                'album_type' => 'class', // All albums are class-specific
                'class_name' => $album->class ? $album->class->name . ' ' . $album->class->section : null,
                'event_title' => $album->event ? $album->event->title : null,
                'total_media_count' => $album->media_count,
                'cover_image' => $albumCoverImage,
            ],
            'media' => [
                'data' => $media->items(),
                'pagination' => [
                    'current_page' => $media->currentPage(),
                    'per_page' => $media->perPage(),
                    'total' => $media->total(),
                    'last_page' => $media->lastPage(),
                    'from' => $media->firstItem(),
                    'to' => $media->lastItem(),
                    'has_more_pages' => $media->hasMorePages(),
                ]
            ],
            'summary' => [
                'total_items' => $media->total(),
                'photos_count' => $album->media()->where('type', 'photo')->where('status', 'active')->count(),
                'videos_count' => $album->media()->where('type', 'video')->where('status', 'active')->count(),
                'documents_count' => $album->media()->where('type', 'document')->where('status', 'active')->count(),
            ]
        ];
    }

    /**
     * Get gallery items from student's class albums
     */
    private function getClassGalleryItems($classId, $schoolId, $mediaType = null): \Illuminate\Support\Collection
    {
        $query = GalleryMedia::whereHas('album', function ($albumQuery) use ($classId, $schoolId) {
            $albumQuery->where('class_id', $classId)
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->where('is_public', true);
        })
            ->where('status', 'active')
            ->with(['album:id,title,description,event_date,class_id'])
            ->orderBy('created_at', 'desc');

        if ($mediaType) {
            $query->where('type', $mediaType);
        }

        return $query->get()->map(function ($media) {
            return [
                'id' => 'gallery_' . $media->id,
                'type' => 'class_gallery',
                'media_type' => $media->type,
                'title' => $media->title ?: $media->album->title,
                'description' => $media->description ?: $media->album->description,
                'file_url' => $this->getMediaFileUrl($media),
                'thumbnail_url' => $this->getMediaThumbnailUrl($media),
                'file_size' => $media->file_size,
                'file_size_human' => $this->formatBytes($media->file_size),
                'dimensions' => $media->metadata['dimensions'] ?? null,
                'duration' => $media->metadata['duration'] ?? null,
                'created_at' => $media->created_at,
                'event_date' => $media->album->event_date,
                'album' => [
                    'id' => $media->album->id,
                    'title' => $media->album->title,
                    'description' => $media->album->description,
                ],
                'source' => 'Class Gallery',
                'views_count' => $media->views_count,
                'is_featured' => $media->is_featured,
            ];
        });
    }

    /**
     * Get gallery items from school-wide albums (not class-specific)
     */
    private function getSchoolGalleryItems($schoolId, $mediaType = null): \Illuminate\Support\Collection
    {
        $query = GalleryMedia::whereHas('album', function ($albumQuery) use ($schoolId) {
            $albumQuery->whereNull('class_id') // School-wide albums
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->where('is_public', true);
        })
            ->where('status', 'active')
            ->with(['album:id,title,description,event_date,class_id'])
            ->orderBy('created_at', 'desc');

        if ($mediaType) {
            $query->where('type', $mediaType);
        }

        return $query->get()->map(function ($media) {
            return [
                'id' => 'school_gallery_' . $media->id,
                'type' => 'school_gallery',
                'media_type' => $media->type,
                'title' => $media->title ?: $media->album->title,
                'description' => $media->description ?: $media->album->description,
                'file_url' => $this->getMediaFileUrl($media),
                'thumbnail_url' => $this->getMediaThumbnailUrl($media),
                'file_size' => $media->file_size,
                'file_size_human' => $this->formatBytes($media->file_size),
                'dimensions' => $media->metadata['dimensions'] ?? null,
                'duration' => $media->metadata['duration'] ?? null,
                'created_at' => $media->created_at,
                'event_date' => $media->album->event_date,
                'album' => [
                    'id' => $media->album->id,
                    'title' => $media->album->title,
                    'description' => $media->album->description,
                ],
                'source' => 'School Gallery',
                'views_count' => $media->views_count,
                'is_featured' => $media->is_featured,
            ];
        });
    }

    /**
     * Get media from student's assignment submissions
     */
    private function getStudentAssignmentMedia($studentId, $schoolId, $mediaType = null): \Illuminate\Support\Collection
    {
        $submissions = AssignmentSubmission::where('student_id', $studentId)
            ->whereHas('assignment', function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
            })
            ->where('status', 'submitted')
            ->whereNotNull('attachments')
            ->with([
                'assignment:id,title,description,type,due_date',
                'assignment.subject:id,name',
                'assignment.class:id,name,section'
            ])
            ->orderBy('submitted_at', 'desc')
            ->get();

        $mediaItems = collect();

        foreach ($submissions as $submission) {
            if (!$submission->attachments || empty($submission->attachments)) {
                continue;
            }

            foreach ($submission->attachments as $index => $attachment) {
                $attachmentMediaType = $this->getAttachmentMediaType($attachment);

                // Filter by media type if specified
                if ($mediaType && $attachmentMediaType !== $mediaType) {
                    continue;
                }

                $mediaItems->push([
                    'id' => 'assignment_' . $submission->id . '_' . $index,
                    'type' => 'assignment_submission',
                    'media_type' => $attachmentMediaType,
                    'title' => $this->getAttachmentName($attachment),
                    'description' => $submission->assignment->title . ' - Assignment Submission',
                    'file_url' => $this->getAttachmentUrl($attachment, $submission),
                    'thumbnail_url' => $this->getAttachmentThumbnailUrl($attachment, $submission),
                    'file_size' => $attachment['size'] ?? null,
                    'file_size_human' => isset($attachment['size']) ? $this->formatBytes($attachment['size']) : 'Unknown',
                    'dimensions' => null, // Assignment attachments don't typically store dimensions
                    'duration' => null,
                    'created_at' => $submission->submitted_at,
                    'event_date' => $submission->submitted_at,
                    'assignment' => [
                        'id' => $submission->assignment->id,
                        'title' => $submission->assignment->title,
                        'type' => $submission->assignment->type,
                        'subject' => $submission->assignment->subject?->name,
                        'class' => $submission->assignment->class ? [
                            'name' => $submission->assignment->class->name,
                            'section' => $submission->assignment->class->section,
                        ] : null,
                    ],
                    'submission' => [
                        'id' => $submission->id,
                        'status' => $submission->status,
                        'marks_obtained' => $submission->marks_obtained,
                        'is_late' => $submission->is_late_submission,
                    ],
                    'source' => 'Assignment Submission',
                    'views_count' => 0,
                    'is_featured' => false,
                ]);
            }
        }

        return $mediaItems;
    }

    /**
     * Get file URL for gallery media
     */
    private function getMediaFileUrl(GalleryMedia $media): string
    {
        // Check if it's an external URL (from seeders)
        if (filter_var($media->file_path, FILTER_VALIDATE_URL)) {
            return $media->file_path;
        }

        // For local files, use Storage URL
        return Storage::url($media->file_path);
    }

    /**
     * Get thumbnail URL for gallery media
     */
    private function getMediaThumbnailUrl(GalleryMedia $media): ?string
    {
        if ($media->thumbnail_path) {
            // Check if it's an external URL
            if (filter_var($media->thumbnail_path, FILTER_VALIDATE_URL)) {
                return $media->thumbnail_path;
            }
            return Storage::url($media->thumbnail_path);
        }

        // For photos without explicit thumbnail, use the main image
        if ($media->type === 'photo') {
            return $this->getMediaFileUrl($media);
        }

        return null;
    }

    /**
     * Get media type from attachment data
     */
    private function getAttachmentMediaType($attachment): string
    {
        $mimeType = $attachment['mime_type'] ?? '';

        if (str_starts_with($mimeType, 'image/')) {
            return 'photo';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        // Check by file extension if mime type not available
        $filename = $attachment['name'] ?? $attachment['filename'] ?? '';
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'];

        if (in_array($extension, $imageExtensions)) {
            return 'photo';
        } elseif (in_array($extension, $videoExtensions)) {
            return 'video';
        }

        return 'document'; // Default for other file types
    }

    /**
     * Get attachment name from attachment data
     */
    private function getAttachmentName($attachment): string
    {
        return $attachment['name'] ?? $attachment['filename'] ?? $attachment['original_name'] ?? 'Attachment';
    }

    /**
     * Get attachment URL for assignment submissions
     */
    private function getAttachmentUrl($attachment, AssignmentSubmission $submission): string
    {
        // Use the assignment controller's download endpoint
        $baseUrl = url('/api');
        $filename = $attachment['filename'] ?? $attachment['name'] ?? '';
        return $baseUrl . '/assignment-submissions/' . $submission->id . '/download?filename=' . urlencode($filename) . '&action=view';
    }

    /**
     * Get attachment thumbnail URL
     */
    private function getAttachmentThumbnailUrl($attachment, AssignmentSubmission $submission): ?string
    {
        // For images, use the same URL as the main file
        if ($this->getAttachmentMediaType($attachment) === 'photo') {
            return $this->getAttachmentUrl($attachment, $submission);
        }

        return null;
    }

    /**
     * Get generated thumbnail URLs for a given file path
     */
    private function getGeneratedThumbnailUrls(string $filePath): array
    {
        $thumbnailUrls = [];
        $pathInfo = pathinfo($filePath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];

        // Define thumbnail sizes (same as in GalleryService)
        $sizes = ['small' => 150, 'medium' => 300, 'large' => 600];

        foreach ($sizes as $sizeName => $dimension) {
            $thumbnailPath = $directory . '/thumbnails/' . $sizeName . '/' . $filename . '.jpg';
            $thumbnailUrls[$sizeName] = $this->buildFileUrl($thumbnailPath);
        }

        return $thumbnailUrls;
    }
}
