<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StudentPromotion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'student_id',
        'academic_year_id',
        'from_class_id',
        'to_class_id',
        'promotion_status',
        'attendance_percentage',
        'assignment_average',
        'assessment_average',
        'overall_score',
        'final_percentage',
        'criteria_details',
        'attendance_criteria_met',
        'assignment_criteria_met',
        'assessment_criteria_met',
        'overall_criteria_met',
        'promotion_reason',
        'admin_comments',
        'is_manual_override',
        'override_reason',
        'evaluated_by',
        'approved_by',
        'evaluation_date',
        'approval_date',
        'requires_remedial',
        'remedial_subjects',
        'remedial_deadline',
        'remedial_status',
        'parent_notified',
        'parent_notification_date',
        'parent_meeting_required',
        'parent_meeting_completed',
        'parent_meeting_date'
    ];

    protected $casts = [
        'attendance_percentage' => 'decimal:2',
        'assignment_average' => 'decimal:2',
        'assessment_average' => 'decimal:2',
        'overall_score' => 'decimal:2',
        'final_percentage' => 'decimal:2',
        'criteria_details' => 'array',
        'remedial_subjects' => 'array',
        'attendance_criteria_met' => 'boolean',
        'assignment_criteria_met' => 'boolean',
        'assessment_criteria_met' => 'boolean',
        'overall_criteria_met' => 'boolean',
        'is_manual_override' => 'boolean',
        'requires_remedial' => 'boolean',
        'parent_notified' => 'boolean',
        'parent_meeting_required' => 'boolean',
        'parent_meeting_completed' => 'boolean',
        'evaluation_date' => 'datetime',
        'approval_date' => 'datetime',
        'parent_notification_date' => 'datetime',
        'parent_meeting_date' => 'datetime',
        'remedial_deadline' => 'date'
    ];

    /**
     * Relationships
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function fromClass()
    {
        return $this->belongsTo(ClassRoom::class, 'from_class_id');
    }

    public function toClass()
    {
        return $this->belongsTo(ClassRoom::class, 'to_class_id');
    }

    public function evaluatedBy()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scopes
     */
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeForAcademicYear($query, $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('promotion_status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('promotion_status', 'pending');
    }

    public function scopePromoted($query)
    {
        return $query->where('promotion_status', 'promoted');
    }

    public function scopeFailed($query)
    {
        return $query->where('promotion_status', 'failed');
    }

    public function scopeRequiringRemedial($query)
    {
        return $query->where('requires_remedial', true);
    }

    public function scopeParentMeetingRequired($query)
    {
        return $query->where('parent_meeting_required', true)
            ->where('parent_meeting_completed', false);
    }

    public function scopeNotNotified($query)
    {
        return $query->where('parent_notified', false);
    }

    /**
     * Helper Methods
     */
    public function isPromoted()
    {
        return in_array($this->promotion_status, ['promoted', 'conditionally_promoted', 'graduated']);
    }

    public function isFailed()
    {
        return $this->promotion_status === 'failed';
    }

    public function isPending()
    {
        return $this->promotion_status === 'pending';
    }

    public function canBeOverridden()
    {
        return in_array($this->promotion_status, ['pending', 'failed']);
    }

    public function getPromotionStatusDisplayAttribute()
    {
        $statusMap = [
            'pending' => 'Pending Evaluation',
            'promoted' => 'Promoted',
            'conditionally_promoted' => 'Conditionally Promoted',
            'failed' => 'Not Promoted',
            'transferred' => 'Transferred',
            'graduated' => 'Graduated',
            'withdrawn' => 'Withdrawn'
        ];

        return $statusMap[$this->promotion_status] ?? 'Unknown';
    }

    public function getRemedialStatusDisplayAttribute()
    {
        $statusMap = [
            'not_required' => 'Not Required',
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'failed' => 'Failed'
        ];

        return $statusMap[$this->remedial_status] ?? 'Unknown';
    }

    /**
     * Mark as evaluated
     */
    public function markAsEvaluated($evaluatedBy, $evaluationData)
    {
        $this->update([
            'evaluation_date' => now(),
            'evaluated_by' => $evaluatedBy,
            'attendance_percentage' => $evaluationData['attendance_percentage'] ?? null,
            'assignment_average' => $evaluationData['assignment_average'] ?? null,
            'assessment_average' => $evaluationData['assessment_average'] ?? null,
            'overall_score' => $evaluationData['overall_score'] ?? null,
            'final_percentage' => $evaluationData['final_percentage'] ?? null,
            'criteria_details' => $evaluationData['criteria_details'] ?? null,
            'attendance_criteria_met' => $evaluationData['attendance_criteria_met'] ?? false,
            'assignment_criteria_met' => $evaluationData['assignment_criteria_met'] ?? false,
            'assessment_criteria_met' => $evaluationData['assessment_criteria_met'] ?? false,
            'overall_criteria_met' => $evaluationData['overall_criteria_met'] ?? false
        ]);
    }

    /**
     * Apply promotion decision
     */
    public function applyPromotionDecision($status, $reason = null, $approvedBy = null)
    {
        $this->update([
            'promotion_status' => $status,
            'promotion_reason' => $reason,
            'approved_by' => $approvedBy,
            'approval_date' => now()
        ]);

        // Handle specific status actions
        switch ($status) {
            case 'promoted':
            case 'conditionally_promoted':
                $this->processPromotion();
                break;
            case 'failed':
                $this->processFailure();
                break;
        }
    }

    /**
     * Process successful promotion
     */
    private function processPromotion()
    {
        if ($this->to_class_id) {
            // Move student to new class
            $this->student->classes()->detach(); // Remove from all current classes

            // Generate next available roll number for the target class
            $nextRollNumber = $this->generateNextRollNumber($this->to_class_id);

            $this->student->classes()->attach($this->to_class_id, [
                'academic_year_id' => $this->academic_year_id,
                'roll_number' => $nextRollNumber,
                'enrolled_date' => now(),
                'enrollment_type' => 'promoted',
                'is_active' => true,
                'enrollment_notes' => "Promoted from {$this->fromClass->name}"
            ]);
        }
    }

    /**
     * Generate next available roll number for a class
     */
    private function generateNextRollNumber($classId)
    {
        // Get the highest current roll number for this class and academic year
        $maxRollNumber = DB::table('class_student')
            ->where('class_id', $classId)
            ->where('academic_year_id', $this->academic_year_id)
            ->where('is_active', true)
            ->max('roll_number');

        // If no roll numbers exist, start from 1, otherwise increment
        if ($maxRollNumber === null) {
            return '001';
        }

        // Convert to integer, increment, and format back to padded string
        $nextNumber = intval($maxRollNumber) + 1;
        return str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Process failure (student remains in same class)
     */
    private function processFailure()
    {
        // Keep student in current class with repeated status
        $this->student->classes()->updateExistingPivot($this->from_class_id, [
            'enrollment_type' => 'repeated',
            'enrollment_notes' => 'Repeating due to promotion failure'
        ]);
    }

    /**
     * Manual override promotion decision
     */
    public function overrideDecision($newStatus, $reason, $overriddenBy)
    {
        $this->update([
            'promotion_status' => $newStatus,
            'is_manual_override' => true,
            'override_reason' => $reason,
            'approved_by' => $overriddenBy,
            'approval_date' => now()
        ]);

        // Process the new decision
        $this->applyPromotionDecision($newStatus, $reason, $overriddenBy);
    }

    /**
     * Check if remedial deadline is approaching or passed
     */
    public function getRemedialDeadlineStatus()
    {
        if (!$this->remedial_deadline) {
            return null;
        }

        $today = Carbon::today();
        $deadline = Carbon::parse($this->remedial_deadline);
        $daysRemaining = $today->diffInDays($deadline, false);

        if ($daysRemaining < 0) {
            return 'overdue';
        } elseif ($daysRemaining <= 7) {
            return 'approaching';
        } else {
            return 'on_track';
        }
    }
}
