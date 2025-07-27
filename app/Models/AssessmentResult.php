<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssessmentResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'student_id',
        'marks_obtained',
        'percentage',
        'grade',
        'result_status',
        'remarks',
        'section_wise_marks',
        'is_absent',
        'absence_reason',
        'result_published_at',
        'published_by',
        'entered_by',
    ];

    protected $casts = [
        'marks_obtained' => 'decimal:2',
        'percentage' => 'decimal:2',
        'section_wise_marks' => 'json',
        'is_absent' => 'boolean',
        'result_published_at' => 'datetime',
    ];

    // Relationships
    public function assessment()
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    // Scopes
    public function scopePassed($query)
    {
        return $query->where('result_status', 'pass');
    }

    public function scopeFailed($query)
    {
        return $query->where('result_status', 'fail');
    }

    public function scopeAbsent($query)
    {
        return $query->where('is_absent', true);
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('result_published_at');
    }

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    // Accessors & Mutators
    public function getGradeColorAttribute()
    {
        return match ($this->grade) {
            'A+', 'A' => 'success',
            'B+', 'B' => 'primary',
            'C+', 'C' => 'warning',
            'D', 'F' => 'danger',
            default => 'secondary'
        };
    }

    public function getIsPublishedAttribute()
    {
        return !is_null($this->result_published_at);
    }

    public function getStatusDisplayAttribute()
    {
        if ($this->is_absent) {
            return 'Absent';
        }

        return match ($this->result_status) {
            'pass' => 'Pass',
            'fail' => 'Fail',
            'exempted' => 'Exempted',
            default => ucfirst($this->result_status)
        };
    }

    // Helper methods
    public function calculateGrade()
    {
        if ($this->is_absent) {
            $this->grade = 'AB'; // Absent
            return;
        }

        $percentage = $this->percentage;

        // Standard grading scale (schools can customize this)
        if ($percentage >= 90) {
            $this->grade = 'A+';
        } elseif ($percentage >= 80) {
            $this->grade = 'A';
        } elseif ($percentage >= 70) {
            $this->grade = 'B+';
        } elseif ($percentage >= 60) {
            $this->grade = 'B';
        } elseif ($percentage >= 50) {
            $this->grade = 'C+';
        } elseif ($percentage >= 40) {
            $this->grade = 'C';
        } elseif ($percentage >= 33) {
            $this->grade = 'D';
        } else {
            $this->grade = 'F';
        }

        // Determine pass/fail status
        $this->result_status = $percentage >= $this->assessment->passing_percentage ? 'pass' : 'fail';
    }

    public function publish($publishedBy = null)
    {
        $this->result_published_at = now();
        if ($publishedBy) {
            $this->published_by = $publishedBy;
        }
        $this->save();
    }

    public function canBeEdited()
    {
        return is_null($this->result_published_at);
    }

    public function getPerformanceLevel()
    {
        if ($this->is_absent) {
            return 'absent';
        }

        $percentage = $this->percentage;

        if ($percentage >= 85) return 'excellent';
        if ($percentage >= 70) return 'good';
        if ($percentage >= 55) return 'satisfactory';
        if ($percentage >= 40) return 'needs_improvement';
        return 'poor';
    }
}
