<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AssignmentSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'student_id',
        'content',
        'attachments',
        'status',
        'submitted_at',
        'marks_obtained',
        'teacher_feedback',
        'grading_details',
        'graded_at',
        'graded_by',
        'is_late_submission',
    ];

    protected $casts = [
        'attachments' => 'array',
        'grading_details' => 'array',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'marks_obtained' => 'decimal:2',
        'is_late_submission' => 'boolean',
    ];

    // Relationships
    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function gradedBy()
    {
        return $this->belongsTo(Teacher::class, 'graded_by');
    }

    // Scopes
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeGraded($query)
    {
        return $query->where('status', 'graded');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeLateSubmissions($query)
    {
        return $query->where('is_late_submission', true);
    }

    // Accessors & Mutators
    public function getGradePercentageAttribute()
    {
        if (!$this->marks_obtained || !$this->assignment->max_marks) {
            return null;
        }

        return round(($this->marks_obtained / $this->assignment->max_marks) * 100, 2);
    }

    public function getGradeLetterAttribute()
    {
        $percentage = $this->grade_percentage;

        if ($percentage === null) return null;

        if ($percentage >= 90) return 'A+';
        if ($percentage >= 80) return 'A';
        if ($percentage >= 70) return 'B';
        if ($percentage >= 60) return 'C';
        if ($percentage >= 50) return 'D';
        return 'F';
    }

    public function getIsLateAttribute()
    {
        if (!$this->submitted_at) return false;

        $dueDateTime = Carbon::parse($this->assignment->due_date);
        if ($this->assignment->due_time) {
            $dueDateTime = $dueDateTime->setTimeFromTimeString($this->assignment->due_time);
        } else {
            $dueDateTime = $dueDateTime->endOfDay();
        }

        return $this->submitted_at->greaterThan($dueDateTime);
    }

    public function getSubmissionStatusAttribute()
    {
        switch ($this->status) {
            case 'pending':
                if ($this->assignment->is_overdue && !$this->assignment->allow_late_submission) {
                    return 'overdue';
                }
                return 'not_submitted';
            case 'submitted':
                return $this->is_late_submission ? 'submitted_late' : 'submitted_on_time';
            case 'graded':
                return 'graded';
            case 'returned':
                return 'returned_for_revision';
            default:
                return $this->status;
        }
    }

    // Helper Methods
    public function submit($content = null, $attachments = null)
    {
        $this->update([
            'content' => $content,
            'attachments' => $attachments,
            'status' => 'submitted',
            'submitted_at' => now(),
            'is_late_submission' => $this->is_late,
        ]);
    }

    public function grade($marks = null, $feedback = null, $gradingDetails = null, $gradedBy = null)
    {
        // For assignments that only need feedback (like homework), marks can be null
        $this->update([
            'marks_obtained' => $marks,
            'teacher_feedback' => $feedback,
            'grading_details' => $gradingDetails,
            'status' => 'graded',
            'graded_at' => now(),
            'graded_by' => $gradedBy,
        ]);
    }

    /**
     * Grade submission with feedback only (no numerical marks)
     */
    public function gradeWithFeedbackOnly($feedback, $gradingDetails = null, $gradedBy = null)
    {
        $this->update([
            'marks_obtained' => null,
            'teacher_feedback' => $feedback,
            'grading_details' => $gradingDetails,
            'status' => 'graded',
            'graded_at' => now(),
            'graded_by' => $gradedBy,
        ]);
    }

    public function returnForRevision($feedback)
    {
        $this->update([
            'status' => 'returned',
            'teacher_feedback' => $feedback,
            'marks_obtained' => null,
            'graded_at' => null,
        ]);
    }

    public function canBeEdited()
    {
        return in_array($this->status, ['pending', 'returned']) &&
            ($this->assignment->allow_late_submission || !$this->assignment->is_overdue);
    }

    public function canBeGraded()
    {
        return in_array($this->status, ['submitted', 'returned']);
    }
}
