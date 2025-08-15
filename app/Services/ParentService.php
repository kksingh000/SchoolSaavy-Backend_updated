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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
                'attachments' => $assignment->attachments ?? [],
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
                    'url' => $this->generateFileUrl($attachment),
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
                    'url' => $attachmentUrl ?: $this->generateFileUrl($attachmentPath),
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
     * Generate file URL based on storage configuration
     */
    private function generateFileUrl(string $filePath): string
    {
        if (empty($filePath)) {
            return '';
        }

        // Check if it's already a full URL
        if (str_starts_with($filePath, 'http')) {
            return $filePath;
        }

        $uploadDisk = config('filesystems.gallery_disk', 'public');

        if ($uploadDisk === 's3') {
            // Generate S3 URL
            $bucket = config('filesystems.disks.s3.bucket');
            $region = config('filesystems.disks.s3.region');
            return "https://{$bucket}.s3.{$region}.amazonaws.com/{$filePath}";
        } else {
            // Generate local storage URL
            return asset('storage/' . $filePath);
        }
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
}
