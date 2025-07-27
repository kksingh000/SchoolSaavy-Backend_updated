<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassRoom;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssignmentService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = Assignment::class;
    }

    /**
     * Get school ID from authenticated user
     */
    private function getSchoolId()
    {
        return Auth::user()->getSchoolId();
    }

    /**
     * Get teacher ID from authenticated user
     */
    private function getTeacherId()
    {
        $user = Auth::user();

        if ($user->user_type === 'teacher' && $user->teacher) {
            return $user->teacher->id;
        }

        throw new \Exception('User is not a teacher or teacher record not found.');
    }

    /**
     * Get user ID from authenticated user (for backward compatibility)
     */
    private function getUserId()
    {
        return Auth::id();
    }

    /**
     * Get all assignments with filters
     */
    public function getAll($filters = [], $relations = [])
    {
        $query = Assignment::with($relations)
            ->where('school_id', $this->getSchoolId());

        // Apply filters
        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['due_date_from'])) {
            $query->whereDate('due_date', '>=', $filters['due_date_from']);
        }

        if (isset($filters['due_date_to'])) {
            $query->whereDate('due_date', '<=', $filters['due_date_to']);
        }

        return $query->orderBy('due_date', 'desc')->paginate(15);
    }

    /**
     * Create a new assignment
     */
    public function createAssignment($data)
    {
        return DB::transaction(function () use ($data) {
            $data['school_id'] = $this->getSchoolId();
            $data['teacher_id'] = $this->getTeacherId();

            $assignment = Assignment::create($data);

            // If assignment is published, create submissions for all students
            if ($assignment->status === 'published') {
                $assignment->createSubmissionsForClass();
            }

            return $assignment->load(['teacher.user', 'class', 'subject']);
        });
    }

    /**
     * Update assignment
     */
    public function updateAssignment($assignmentId, $data)
    {
        return DB::transaction(function () use ($assignmentId, $data) {
            $assignment = Assignment::where('school_id', $this->getSchoolId())
                ->findOrFail($assignmentId);

            // Check if assignment can be edited
            if (!$assignment->canBeEdited() && !in_array($data['status'] ?? '', ['published', 'completed'])) {
                throw new \Exception('Assignment cannot be edited at this stage.');
            }

            $oldStatus = $assignment->status;
            $assignment->update($data);

            // If status changed to published, create submissions
            if ($oldStatus !== 'published' && $assignment->status === 'published') {
                $assignment->createSubmissionsForClass();
            }

            return $assignment->load(['teacher.user', 'class', 'subject']);
        });
    }

    /**
     * Delete assignment
     */
    public function deleteAssignment($assignmentId)
    {
        return DB::transaction(function () use ($assignmentId) {
            $assignment = Assignment::where('school_id', $this->getSchoolId())
                ->findOrFail($assignmentId);

            if (!$assignment->canBeDeleted()) {
                throw new \Exception('Assignment cannot be deleted as it has submissions.');
            }

            $assignment->delete();
            return true;
        });
    }

    /**
     * Get assignment with submissions
     */
    public function getAssignmentWithSubmissions($assignmentId)
    {
        return Assignment::where('school_id', $this->getSchoolId())
            ->with([
                'teacher.user',
                'class',
                'subject',
                'submissions' => function ($query) {
                    $query->with(['student'])
                        ->orderBy('submitted_at', 'desc');
                }
            ])
            ->findOrFail($assignmentId);
    }

    /**
     * Submit assignment by student
     */
    public function submitAssignment($assignmentId, $studentId, $data)
    {
        return DB::transaction(function () use ($assignmentId, $studentId, $data) {
            $assignment = Assignment::where('school_id', $this->getSchoolId())
                ->findOrFail($assignmentId);

            if (!$assignment->canAcceptSubmissions()) {
                throw new \Exception('Assignment is not accepting submissions.');
            }

            $submission = AssignmentSubmission::where('assignment_id', $assignmentId)
                ->where('student_id', $studentId)
                ->first();

            if (!$submission) {
                throw new \Exception('Submission record not found.');
            }

            if (!$submission->canBeEdited()) {
                throw new \Exception('Submission cannot be edited.');
            }

            $submission->submit(
                $data['content'] ?? null,
                $data['attachments'] ?? null
            );

            return $submission->load(['assignment', 'student']);
        });
    }

    /**
     * Grade assignment submission
     */
    public function gradeSubmission($submissionId, $data)
    {
        return DB::transaction(function () use ($submissionId, $data) {
            $submission = AssignmentSubmission::whereHas('assignment', function ($query) {
                $query->where('school_id', $this->getSchoolId());
            })->findOrFail($submissionId);

            if (!$submission->canBeGraded()) {
                throw new \Exception('Submission cannot be graded.');
            }

            $submission->grade(
                $data['marks_obtained'],
                $data['teacher_feedback'] ?? null,
                $data['grading_details'] ?? null,
                $this->getTeacherId()
            );

            return $submission->load(['assignment', 'student', 'gradedBy.user']);
        });
    }

    /**
     * Get teacher's assignments dashboard
     */
    public function getTeacherDashboard($teacherId = null)
    {
        $teacherId = $teacherId ?? $this->getTeacherId();

        $assignments = Assignment::where('school_id', $this->getSchoolId())
            ->where('teacher_id', $teacherId)
            ->with(['class', 'subject']);

        return [
            'total_assignments' => $assignments->count(),
            'published_assignments' => $assignments->where('status', 'published')->count(),
            'draft_assignments' => $assignments->where('status', 'draft')->count(),
            'due_today' => $assignments->dueToday()->count(),
            'overdue' => $assignments->overdue()->count(),
            'pending_grading' => AssignmentSubmission::whereHas('assignment', function ($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId)
                    ->where('school_id', $this->getSchoolId());
            })->where('status', 'submitted')->count(),
            'recent_assignments' => $assignments->orderBy('created_at', 'desc')->take(5)->get(),
        ];
    }

    /**
     * Get upcoming assignments for a class
     */
    public function getUpcomingAssignments($classId, $days = 7)
    {
        return Assignment::where('school_id', $this->getSchoolId())
            ->where('class_id', $classId)
            ->where('status', 'published')
            ->upcoming($days)
            ->with(['teacher.user', 'subject'])
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Get assignments for a student
     */
    public function getStudentAssignments($studentId, $filters = [])
    {
        // Get student's current class
        $student = Student::findOrFail($studentId);
        $currentClass = $student->currentClass()->first();
        $classId = $currentClass?->id;

        if (!$classId) {
            return collect();
        }

        $query = Assignment::where('school_id', $this->getSchoolId())
            ->where('class_id', $classId)
            ->where('status', 'published')
            ->with([
                'subject',
                'teacher.user',
                'submissions' => function ($q) use ($studentId) {
                    $q->where('student_id', $studentId);
                }
            ]);

        // Apply filters
        if (isset($filters['status'])) {
            if ($filters['status'] === 'pending') {
                $query->whereHas('submissions', function ($q) use ($studentId) {
                    $q->where('student_id', $studentId)
                        ->where('status', 'pending');
                });
            } elseif ($filters['status'] === 'submitted') {
                $query->whereHas('submissions', function ($q) use ($studentId) {
                    $q->where('student_id', $studentId)
                        ->whereIn('status', ['submitted', 'graded']);
                });
            }
        }

        return $query->orderBy('due_date', 'desc')->get();
    }

    /**
     * Get assignment statistics
     */
    public function getAssignmentStatistics($filters = [])
    {
        $query = Assignment::where('school_id', $this->getSchoolId());

        // Apply date filter
        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('assigned_date', [$filters['date_from'], $filters['date_to']]);
        }

        $assignments = $query->with(['submissions'])->get();

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

        return [
            'total_assignments' => $totalAssignments,
            'total_submissions_expected' => $totalSubmissions,
            'total_submitted' => $submittedCount,
            'total_graded' => $gradedCount,
            'submission_rate' => $totalSubmissions > 0 ? round(($submittedCount / $totalSubmissions) * 100, 2) : 0,
            'grading_rate' => $submittedCount > 0 ? round(($gradedCount / $submittedCount) * 100, 2) : 0,
            'by_type' => $assignments->groupBy('type')->map->count(),
            'by_status' => $assignments->groupBy('status')->map->count(),
        ];
    }

    /**
     * Get assignment submission overview for all students in class
     */
    public function getAssignmentSubmissionOverview($assignmentId)
    {
        $assignment = Assignment::where('school_id', $this->getSchoolId())
            ->with(['class', 'subject', 'teacher.user'])
            ->findOrFail($assignmentId);

        // Get all students in the assignment's class
        $students = $assignment->class->students()
            ->where('class_student.is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        // Get all submissions for this assignment
        $submissions = AssignmentSubmission::where('assignment_id', $assignmentId)
            ->with(['student'])
            ->get()
            ->keyBy('student_id');

        // Build the overview data
        $studentOverview = $students->map(function ($student) use ($submissions) {
            $submission = $submissions->get($student->id);

            return [
                'student_id' => $student->id,
                'student_name' => $student->first_name . ' ' . $student->last_name,
                'admission_number' => $student->admission_number,
                'roll_number' => $student->roll_number,
                'submission_status' => $submission ? $submission->status : 'not_submitted',
                'submitted_at' => $submission?->submitted_at?->format('Y-m-d H:i:s'),
                'is_late_submission' => $submission?->is_late_submission ?? false,
                'marks_obtained' => $submission?->marks_obtained,
                'grade_percentage' => $submission?->grade_percentage,
                'grade_letter' => $submission?->grade_letter,
                'teacher_feedback' => $submission?->teacher_feedback,
                'graded_at' => $submission?->graded_at?->format('Y-m-d H:i:s'),
                'can_be_graded' => $submission?->canBeGraded() ?? false,
            ];
        });

        // Calculate statistics
        $totalStudents = $students->count();
        $submittedCount = $submissions->where('status', '!=', 'pending')->count();
        $gradedCount = $submissions->where('status', 'graded')->count();
        $pendingCount = $submissions->where('status', 'pending')->count();
        $notSubmittedCount = $totalStudents - $submissions->count();

        return [
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'type' => $assignment->type,
                'status' => $assignment->status,
                'max_marks' => $assignment->max_marks,
                'due_date' => $assignment->due_date->format('Y-m-d'),
                'due_time' => $assignment->due_time?->format('H:i'),
                'is_overdue' => $assignment->is_overdue,
                'class' => [
                    'id' => $assignment->class->id,
                    'name' => $assignment->class->name,
                    'section' => $assignment->class->section,
                ],
                'subject' => [
                    'id' => $assignment->subject->id,
                    'name' => $assignment->subject->name,
                ],
                'teacher' => [
                    'id' => $assignment->teacher->id,
                    'name' => $assignment->teacher->user->name,
                ],
            ],
            'statistics' => [
                'total_students' => $totalStudents,
                'submitted' => $submittedCount,
                'graded' => $gradedCount,
                'pending_review' => $pendingCount,
                'not_submitted' => $notSubmittedCount,
                'submission_rate' => $totalStudents > 0 ? round(($submittedCount / $totalStudents) * 100, 2) : 0,
                'grading_rate' => $submittedCount > 0 ? round(($gradedCount / $submittedCount) * 100, 2) : 0,
            ],
            'students' => $studentOverview,
        ];
    }
}
