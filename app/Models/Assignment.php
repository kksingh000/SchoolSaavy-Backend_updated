<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'teacher_id',
        'class_id',
        'subject_id',
        'title',
        'description',
        'instructions',
        'type',
        'status',
        'assigned_date',
        'due_date',
        'due_time',
        'max_marks',
        'attachments',
        'allow_late_submission',
        'grading_criteria',
        'is_active',
    ];

    protected $casts = [
        'assigned_date' => 'date',
        'due_date' => 'date',
        'due_time' => 'datetime:H:i',
        'attachments' => 'array',
        'allow_late_submission' => 'boolean',
        'is_active' => 'boolean',
        'max_marks' => 'integer',
    ];

    // Relationships
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function class()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function submittedSubmissions()
    {
        return $this->hasMany(AssignmentSubmission::class)->where('status', 'submitted');
    }

    public function gradedSubmissions()
    {
        return $this->hasMany(AssignmentSubmission::class)->where('status', 'graded');
    }


    public function scopeForAcademicYear($query, $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeCurrentYear($query)
    {
        return $query->whereHas('academicYear', function ($q) {
            $q->where('is_current', true);
        });
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeDueToday($query)
    {
        return $query->whereDate('due_date', Carbon::today());
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', Carbon::today())
            ->whereIn('status', ['published']);
    }

    public function scopeUpcoming($query, $days = 7)
    {
        return $query->whereBetween('due_date', [
            Carbon::today(),
            Carbon::today()->addDays($days)
        ]);
    }

    // Accessors & Mutators
    public function getIsOverdueAttribute()
    {
        return $this->due_date < Carbon::today() && in_array($this->status, ['published']);
    }

    public function getDaysUntilDueAttribute()
    {
        return Carbon::today()->diffInDays($this->due_date, false);
    }

    public function getSubmissionStatsAttribute()
    {
        $totalStudents = $this->class->activeStudents()->count();
        $submittedCount = $this->submissions()->where('status', '!=', 'pending')->count();
        $gradedCount = $this->submissions()->where('status', 'graded')->count();

        return [
            'total_students' => $totalStudents,
            'submitted_count' => $submittedCount,
            'pending_count' => $totalStudents - $submittedCount,
            'graded_count' => $gradedCount,
            'submission_rate' => $totalStudents > 0 ? round(($submittedCount / $totalStudents) * 100, 2) : 0,
            'grading_progress' => $submittedCount > 0 ? round(($gradedCount / $submittedCount) * 100, 2) : 0,
        ];
    }

    // Helper Methods
    public function canBeEdited()
    {
        return in_array($this->status, ['draft']) && $this->assigned_date >= Carbon::today();
    }

    public function canBeDeleted()
    {
        return $this->status === 'draft' || $this->submissions()->count() === 0;
    }

    public function canAcceptSubmissions()
    {
        return $this->status === 'published' &&
            ($this->allow_late_submission || $this->due_date >= Carbon::today());
    }

    /**
     * Check if this assignment requires numerical marks for grading
     * Some assignment types like homework might only need feedback
     */
    public function requiresNumericalMarks()
    {
        // Define which assignment types require numerical marks
        $typesRequiringMarks = ['quiz', 'assessment', 'project'];

        // Also check if max_marks is set - if set, marks are expected
        return in_array($this->type, $typesRequiringMarks) || !is_null($this->max_marks);
    }

    /**
     * Check if this assignment allows feedback-only grading
     */
    public function allowsFeedbackOnlyGrading()
    {
        // Homework and classwork can be graded with feedback only
        $feedbackOnlyTypes = ['homework', 'classwork'];

        return in_array($this->type, $feedbackOnlyTypes) && is_null($this->max_marks);
    }

    public function createSubmissionsForClass()
    {
        $students = $this->class->activeStudents;

        foreach ($students as $student) {
            AssignmentSubmission::firstOrCreate([
                'assignment_id' => $this->id,
                'student_id' => $student->id,
            ]);
        }
    }

    public function getAverageMarks()
    {
        return $this->submissions()
            ->where('status', 'graded')
            ->whereNotNull('marks_obtained')
            ->avg('marks_obtained') ?? 0;
    }

    public function getClassPerformance()
    {
        $gradedSubmissions = $this->submissions()
            ->where('status', 'graded')
            ->whereNotNull('marks_obtained')
            ->get();

        if ($gradedSubmissions->isEmpty()) {
            return null;
        }

        $marks = $gradedSubmissions->pluck('marks_obtained');
        $average = $marks->avg();
        $highest = $marks->max();
        $lowest = $marks->min();

        return [
            'average_marks' => round($average, 2),
            'highest_marks' => $highest,
            'lowest_marks' => $lowest,
            'total_graded' => $gradedSubmissions->count(),
            'pass_rate' => round($marks->filter(fn($mark) => $mark >= ($this->max_marks * 0.6))->count() / $gradedSubmissions->count() * 100, 2),
        ];
    }
}
