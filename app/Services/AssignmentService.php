<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Events\AssignmentManagement\AssignmentCreated;
use App\Events\AssignmentManagement\AssignmentSubmitted;
use App\Events\AssignmentManagement\AssignmentGraded;
use App\Events\AssignmentManagement\AssignmentResubmissionRequested;
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
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        // Get school ID based on user type
        switch ($user->user_type) {
            case 'admin':
            case 'school_admin':
                return $user->schoolAdmin?->school_id;
            case 'teacher':
                return $user->teacher?->school_id;
            case 'student':
                return $user->student?->school_id;
            case 'parent':
                return $user->parent?->students?->first()?->school_id;
            default:
                return null;
        }
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

            // Load relationships before firing event
            $assignment->load(['teacher.user', 'class', 'subject']);

            // Fire event AFTER transaction commits (will auto-queue)
            DB::afterCommit(function () use ($assignment) {
                // Only fire event for published assignments
                if ($assignment->status === 'published') {
                    event(new AssignmentCreated(
                        assignmentId: $assignment->id,
                        classId: $assignment->class_id,
                        title: $assignment->title,
                        dueDate: $assignment->due_date->format('Y-m-d'),
                        dueTime: $assignment->due_time ? $assignment->due_time->format('H:i') : null,
                        subjectName: $assignment->subject->name,
                        teacherName: $assignment->teacher->user->name,
                        type: $assignment->type,
                        maxMarks: $assignment->max_marks
                    ));
                }
            });

            return $assignment;
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

            // Load relationships before firing event
            $assignment->load(['teacher.user', 'class', 'subject']);

            // Fire event AFTER transaction commits if status changed to published
            DB::afterCommit(function () use ($assignment, $oldStatus) {
                // Fire event if assignment was just published
                if ($oldStatus !== 'published' && $assignment->status === 'published') {
                    event(new AssignmentCreated(
                        assignmentId: $assignment->id,
                        classId: $assignment->class_id,
                        title: $assignment->title,
                        dueDate: $assignment->due_date->format('Y-m-d'),
                        dueTime: $assignment->due_time ? $assignment->due_time->format('H:i') : null,
                        subjectName: $assignment->subject->name,
                        teacherName: $assignment->teacher->user->name,
                        type: $assignment->type,
                        maxMarks: $assignment->max_marks
                    ));
                }
            });

            return $assignment;
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
     * Get assignment with optimized submission data (lightweight)
     */
    public function getAssignmentWithOptimizedSubmissions($assignmentId)
    {
        $schoolId = $this->getSchoolId();

        // Get assignment with basic relations
        $assignment = Assignment::where('school_id', $schoolId)
            ->with([
                'teacher.user:id,name,email',
                'class:id,name,section,grade_level',
                'subject:id,name,code'
            ])
            ->findOrFail($assignmentId);

        // Get total students count in the class
        $totalStudents = DB::table('class_student')
            ->where('class_id', $assignment->class_id)
            ->where('is_active', true)
            ->count();

        // Get submission statistics with single query
        $submissionStats = DB::table('assignment_submissions')
            ->where('assignment_id', $assignmentId)
            ->selectRaw('
                COUNT(*) as total_submissions,
                SUM(CASE WHEN status != "pending" THEN 1 ELSE 0 END) as submitted_count,
                SUM(CASE WHEN status = "graded" THEN 1 ELSE 0 END) as graded_count,
                AVG(CASE WHEN marks_obtained IS NOT NULL THEN marks_obtained ELSE NULL END) as avg_marks
            ')
            ->first();

        $submittedCount = $submissionStats->submitted_count ?? 0;
        $pendingCount = $totalStudents - $submittedCount;

        // Get lightweight submission data using Eloquent for computed attributes
        $submissions = AssignmentSubmission::where('assignment_id', $assignmentId)
            ->with([
                'student' => function ($query) use ($assignment) {
                    $query->select('id', 'first_name', 'last_name', 'admission_number')
                        ->with(['classes' => function ($q) use ($assignment) {
                            $q->where('class_id', $assignment->class_id)
                                ->wherePivot('is_active', true)
                                ->select('classes.id');
                        }]);
                }
            ])
            ->select([
                'id',
                'student_id',
                'status',
                'submitted_at',
                'marks_obtained',
                'graded_at',
                'is_late_submission',
                'assignment_id' // Need this for computed attributes
            ])
            ->orderBy('submitted_at', 'desc')
            ->get()
            ->map(function ($submission) {
                // Get roll number from pivot table
                $rollNumber = $submission->student->classes->first()?->pivot->roll_number ?? null;
                
                return [
                    'id' => $submission->id,
                    'student_id' => $submission->student_id,
                    'status' => $submission->status,
                    'submission_status' => $submission->submission_status, // Computed attribute
                    'submitted_at' => $submission->submitted_at?->format('Y-m-d H:i:s'),
                    'marks_obtained' => $submission->marks_obtained,
                    'grade_percentage' => $submission->grade_percentage, // Computed attribute
                    'grade_letter' => $submission->grade_letter, // Computed attribute
                    'graded_at' => $submission->graded_at?->format('Y-m-d H:i:s'),
                    'is_late_submission' => $submission->is_late_submission,
                    'is_late' => $submission->is_late, // Computed attribute
                    'student' => [
                        'id' => $submission->student->id,
                        'name' => $submission->student->first_name . ' ' . $submission->student->last_name,
                        'admission_number' => $submission->student->admission_number,
                        'roll_number' => $rollNumber,
                    ]
                ];
            });

        // Add computed attributes to assignment
        $assignment->submission_statistics = [
            'total_students' => $totalStudents,
            'submitted_count' => $submittedCount,
            'pending_count' => $pendingCount,
            'graded_count' => $submissionStats->graded_count ?? 0,
            'submission_rate' => $totalStudents > 0 ? round(($submittedCount / $totalStudents) * 100, 2) : 0,
            'grading_progress' => $submittedCount > 0 ? round((($submissionStats->graded_count ?? 0) / $submittedCount) * 100, 2) : 0,
            'average_marks' => $submissionStats->avg_marks ? round($submissionStats->avg_marks, 2) : null,
        ];

        $assignment->lightweight_submissions = $submissions;

        return $assignment;
    }

    /**
     * Submit assignment by student
     */
    public function submitAssignment($assignmentId, $studentId, $data)
    {
        $submission = DB::transaction(function () use ($assignmentId, $studentId, $data) {
            // Get student first to get their school_id
            $student = Student::findOrFail($studentId);
            
            // Find assignment using student's school_id for proper isolation
            $assignment = Assignment::where('id', $assignmentId)
                ->where('school_id', $student->school_id)
                ->first();

            if (!$assignment) {
                throw new \Exception('Assignment not found or access denied.');
            }

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

            return $submission->load(['assignment', 'student', 'assignment.subject', 'assignment.teacher']);
        });

        // Fire event after transaction commits
        DB::afterCommit(function () use ($submission) {
            $assignment = $submission->assignment;
            $student = $submission->student;
            
            // Check if submission is late
            $isLate = false;
            if ($assignment->due_date && $assignment->due_time) {
                // Combine date and time properly
                $dueDateTime = Carbon::parse($assignment->due_date->format('Y-m-d') . ' ' . $assignment->due_time->format('H:i:s'));
                $isLate = $submission->submitted_at > $dueDateTime;
            }

            event(new AssignmentSubmitted(
                submissionId: $submission->id,
                assignmentId: $assignment->id,
                studentId: $student->id,
                teacherId: $assignment->teacher_id,
                assignmentTitle: $assignment->title,
                subjectName: $assignment->subject->name ?? 'Unknown Subject',
                studentName: $student->name,
                submittedAt: $submission->submitted_at->toDateTimeString(),
                isLateSubmission: $isLate
            ));
        });

        return $submission;
    }

    /**
     * Grade assignment submission
     */
    public function gradeSubmission($submissionId, $data)
    {
        $submission = DB::transaction(function () use ($submissionId, $data) {
            $submission = AssignmentSubmission::whereHas('assignment', function ($query) {
                $query->where('school_id', $this->getSchoolId());
            })->with('assignment')->findOrFail($submissionId);

            if (!$submission->canBeGraded()) {
                throw new \Exception('Submission cannot be graded.');
            }

            $assignment = $submission->assignment;
            $marksObtained = $data['marks_obtained'] ?? null;
            $feedback = $data['teacher_feedback'] ?? null;
            $gradingDetails = $data['grading_details'] ?? null;

            // Validate based on assignment requirements
            if ($assignment->requiresNumericalMarks()) {
                // This assignment type requires numerical marks
                if (is_null($marksObtained)) {
                    throw new \Exception("This {$assignment->type} assignment requires numerical marks for grading.");
                }

                // Validate marks against assignment max_marks
                if ($assignment->max_marks && $marksObtained > $assignment->max_marks) {
                    throw new \Exception("Marks obtained ({$marksObtained}) cannot exceed maximum marks ({$assignment->max_marks}).");
                }

                if ($marksObtained < 0) {
                    throw new \Exception("Marks obtained cannot be negative.");
                }
            } else {
                // For assignments that allow feedback-only grading
                if (is_null($marksObtained) && empty($feedback)) {
                    throw new \Exception("Either marks or teacher feedback must be provided for grading.");
                }

                // If marks are provided, validate them
                if (!is_null($marksObtained)) {
                    if ($assignment->max_marks && $marksObtained > $assignment->max_marks) {
                        throw new \Exception("Marks obtained ({$marksObtained}) cannot exceed maximum marks ({$assignment->max_marks}).");
                    }

                    if ($marksObtained < 0) {
                        throw new \Exception("Marks obtained cannot be negative.");
                    }
                }
            }

            // Grade the submission
            if (!is_null($marksObtained)) {
                // Numerical grading
                $submission->grade(
                    $marksObtained,
                    $feedback,
                    $gradingDetails,
                    $this->getTeacherId()
                );
            } else {
                // Feedback-only grading
                $submission->gradeWithFeedbackOnly(
                    $feedback,
                    $gradingDetails,
                    $this->getTeacherId()
                );
            }

            return $submission->load(['assignment', 'assignment.subject', 'student', 'gradedBy.user']);
        });

        // Fire event after transaction commits
        DB::afterCommit(function () use ($submission) {
            $assignment = $submission->assignment;
            $student = $submission->student;
            
            // Calculate percentage and grade if marks are present
            $percentage = null;
            $gradeLetter = null;
            
            if ($submission->marks_obtained !== null && $assignment->max_marks) {
                $percentage = round(($submission->marks_obtained / $assignment->max_marks) * 100, 2);
                $gradeLetter = $submission->grade_letter; // Uses accessor from model
            }

            event(new AssignmentGraded(
                submissionId: $submission->id,
                assignmentId: $assignment->id,
                studentId: $student->id,
                assignmentTitle: $assignment->title,
                subjectName: $assignment->subject->name ?? 'Unknown Subject',
                studentName: $student->name,
                marksObtained: $submission->marks_obtained,
                maxMarks: $assignment->max_marks,
                percentage: $percentage,
                gradeLetter: $gradeLetter,
                teacherFeedback: $submission->teacher_feedback,
                gradedAt: $submission->graded_at->toDateTimeString(),
                hasNumericalGrade: $submission->marks_obtained !== null,
            ));
        });

        return $submission;
    }

    /**
     * Return assignment submission for revision
     */
    public function returnSubmissionForRevision($submissionId, $feedback)
    {
        $submission = DB::transaction(function () use ($submissionId, $feedback) {
            $submission = AssignmentSubmission::whereHas('assignment', function ($query) {
                $query->where('school_id', $this->getSchoolId());
            })->findOrFail($submissionId);

            if (!$submission->canBeGraded()) {
                throw new \Exception('Submission cannot be returned for revision.');
            }

            $submission->returnForRevision($feedback);

            return $submission->load(['assignment', 'assignment.subject', 'student']);
        });

        // Fire event after transaction commits
        DB::afterCommit(function () use ($submission, $feedback) {
            $assignment = $submission->assignment;
            $student = $submission->student;
            
            // Get new due date if assignment allows late submission
            $newDueDate = null;
            $newDueTime = null;
            
            if ($assignment->allow_late_submission && $assignment->due_date) {
                // Use existing due date or extend it (you can modify this logic)
                $newDueDate = $assignment->due_date->format('Y-m-d');
                $newDueTime = $assignment->due_time ? $assignment->due_time->format('H:i') : null;
            }

            event(new AssignmentResubmissionRequested(
                submissionId: $submission->id,
                assignmentId: $assignment->id,
                studentId: $student->id,
                assignmentTitle: $assignment->title,
                subjectName: $assignment->subject->name ?? 'Unknown Subject',
                studentName: $student->name,
                teacherFeedback: $feedback,
                newDueDate: $newDueDate,
                newDueTime: $newDueTime,
                returnedAt: now()->toDateTimeString(),
            ));
        });

        return $submission;
    }

    /**
     * Get student submission for specific assignment
     */
    public function getStudentSubmission($assignmentId, $studentId)
    {
        return AssignmentSubmission::whereHas('assignment', function ($query) {
            $query->where('school_id', $this->getSchoolId());
        })
            ->where('assignment_id', $assignmentId)
            ->where('student_id', $studentId)
            ->with(['assignment', 'student'])
            ->first();
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

    /**
     * Get assignments by class ID with submission statistics - OPTIMIZED
     */
    public function getAssignmentsByClassOptimized($classId, array $filters = [], $schoolId = null)
    {
        $status = $filters['status'] ?? null; // Changed: no default status filter, show all by default
        $type = $filters['type'] ?? null;
        $search = $filters['search'] ?? null;
        $perPage = $filters['per_page'] ?? 15;

        // Single optimized query to get total active students in class
        $totalStudents = DB::table('class_student')
            ->where('class_id', $classId)
            ->where('is_active', true)
            ->count();

        // Build optimized query with all data in one go using joins
        $query = Assignment::select([
            'assignments.id',
            'assignments.title',
            'assignments.description',
            'assignments.type',
            'assignments.status',
            'assignments.due_date',
            'assignments.due_time',
            'assignments.max_marks',
            'assignments.allow_late_submission',
            'assignments.created_at',
            'subjects.id as subject_id',
            'subjects.name as subject_name',
            'teachers.id as teacher_id',
            'users.name as teacher_name',
            // Get submission counts using efficient aggregation
            DB::raw('COALESCE(submission_stats.submitted_count, 0) as submitted_count'),
            DB::raw('COALESCE(submission_stats.graded_count, 0) as graded_count')
        ])
            ->leftJoin('subjects', 'assignments.subject_id', '=', 'subjects.id')
            ->leftJoin('teachers', 'assignments.teacher_id', '=', 'teachers.id')
            ->leftJoin('users', 'teachers.user_id', '=', 'users.id')
            ->leftJoin(DB::raw('(
            SELECT 
                assignment_id,
                COUNT(*) as submitted_count,
                COUNT(CASE WHEN status = "graded" THEN 1 END) as graded_count
            FROM assignment_submissions 
            WHERE status IN ("submitted", "graded")
            GROUP BY assignment_id
        ) as submission_stats'), 'assignments.id', '=', 'submission_stats.assignment_id')
            ->where('assignments.class_id', $classId);

        // Add school filter if provided
        if ($schoolId) {
            $query->where('assignments.school_id', $schoolId);
        }

        // Apply additional filters
        if ($status) {
            $query->where('assignments.status', $status);
        }

        if ($type) {
            $query->where('assignments.type', $type);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('assignments.title', 'like', "%{$search}%")
                    ->orWhere('assignments.description', 'like', "%{$search}%");
            });
        }

        // Order by status (published first), then by due date (upcoming first)
        $query->orderByRaw("CASE WHEN assignments.status = 'published' THEN 1 ELSE 2 END")
            ->orderBy('assignments.due_date', 'asc')
            ->orderBy('assignments.created_at', 'desc');

        $assignments = $query->paginate($perPage);

        // Transform the data efficiently without additional queries
        $transformedAssignments = $assignments->getCollection()->map(function ($assignment) use ($totalStudents) {
            $submittedCount = (int) $assignment->submitted_count;
            $gradedCount = (int) $assignment->graded_count;

            $submissionRate = $totalStudents > 0 ? round(($submittedCount / $totalStudents) * 100, 1) : 0;
            $gradingRate = $submittedCount > 0 ? round(($gradedCount / $submittedCount) * 100, 1) : 0;

            $dueDate = Carbon::parse($assignment->due_date);
            $now = now()->startOfDay();

            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'type' => $assignment->type,
                'status' => $assignment->status,
                'due_date' => $dueDate->format('Y-m-d'),
                'due_time' => $assignment->due_time ? $assignment->due_time->format('H:i') : null,
                'max_marks' => $assignment->max_marks,
                'allow_late_submission' => (bool) $assignment->allow_late_submission,
                'created_at' => Carbon::parse($assignment->created_at)->format('Y-m-d H:i:s'),
                'subject' => [
                    'id' => $assignment->subject_id,
                    'name' => $assignment->subject_name,
                ],
                'teacher' => [
                    'id' => $assignment->teacher_id,
                    'name' => $assignment->teacher_name,
                ],
                'submission_stats' => [
                    'total_students' => $totalStudents,
                    'submitted_count' => $submittedCount,
                    'graded_count' => $gradedCount,
                    'pending_count' => $totalStudents - $submittedCount,
                    'submission_rate' => $submissionRate,
                    'grading_rate' => $gradingRate,
                ],
                'is_overdue' => $dueDate->lt($now),
                'days_until_due' => $now->diffInDays($dueDate, false),
            ];
        });

        $assignments->setCollection($transformedAssignments);

        return [
            'assignments' => $assignments,
            'meta' => [
                'total_students_in_class' => $totalStudents,
            ]
        ];
    }
}
