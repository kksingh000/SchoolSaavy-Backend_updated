<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromotionCriteria extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'promotion_criteria';

    protected $fillable = [
        'school_id',
        'from_class_id',
        'to_class_id',
        'academic_year_id',
        'minimum_attendance_percentage',
        'minimum_assignment_average',
        'minimum_assessment_average',
        'minimum_overall_percentage',
        'promotion_weightages',
        'minimum_attendance_days',
        'maximum_disciplinary_actions',
        'require_parent_meeting',
        'grace_marks_allowed',
        'allow_conditional_promotion',
        'has_remedial_option',
        'remedial_subjects',
        'is_active'
    ];

    protected $casts = [
        'minimum_attendance_percentage' => 'decimal:2',
        'minimum_assignment_average' => 'decimal:2',
        'minimum_assessment_average' => 'decimal:2',
        'minimum_overall_percentage' => 'decimal:2',
        'grace_marks_allowed' => 'decimal:2',
        'promotion_weightages' => 'array',
        'remedial_subjects' => 'array',
        'require_parent_meeting' => 'boolean',
        'allow_conditional_promotion' => 'boolean',
        'has_remedial_option' => 'boolean',
        'is_active' => 'boolean'
    ];

    /**
     * Relationships
     */
    public function school()
    {
        return $this->belongsTo(School::class);
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

    /**
     * Scopes
     */
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForAcademicYear($query, $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeForClass($query, $classId)
    {
        return $query->where('from_class_id', $classId);
    }

    /**
     * Helper Methods
     */
    public function getDefaultWeightages()
    {
        return $this->promotion_weightages ?? [
            'attendance' => 20,
            'assignments' => 40,
            'assessments' => 40
        ];
    }

    public function evaluateStudent($studentPerformance)
    {
        $weightages = $this->getDefaultWeightages();

        // Extract performance data
        $attendancePercentage = $studentPerformance['attendance_percentage'] ?? 0;
        $assignmentAverage = $studentPerformance['assignment_average'] ?? 0;
        $assessmentAverage = $studentPerformance['assessment_average'] ?? 0;

        // Calculate weighted score
        $weightedScore =
            ($attendancePercentage * ($weightages['attendance'] / 100)) +
            ($assignmentAverage * ($weightages['assignments'] / 100)) +
            ($assessmentAverage * ($weightages['assessments'] / 100));

        // Check individual criteria
        $criteriaResults = [
            'attendance_met' => $attendancePercentage >= $this->minimum_attendance_percentage,
            'assignment_met' => $assignmentAverage >= $this->minimum_assignment_average,
            'assessment_met' => $assessmentAverage >= $this->minimum_assessment_average,
            'overall_met' => $weightedScore >= $this->minimum_overall_percentage,
            'weighted_score' => round($weightedScore, 2),
            'grace_applicable' => false
        ];

        // Check if grace marks can be applied
        if (!$criteriaResults['overall_met'] && $this->grace_marks_allowed > 0) {
            $scoreWithGrace = $weightedScore + $this->grace_marks_allowed;
            $criteriaResults['grace_applicable'] = $scoreWithGrace >= $this->minimum_overall_percentage;
            $criteriaResults['score_with_grace'] = round($scoreWithGrace, 2);
        }

        // Determine promotion eligibility
        $isEligible = $criteriaResults['attendance_met'] &&
            $criteriaResults['assignment_met'] &&
            $criteriaResults['assessment_met'] &&
            ($criteriaResults['overall_met'] || $criteriaResults['grace_applicable']);

        return [
            'eligible_for_promotion' => $isEligible,
            'criteria_details' => $criteriaResults,
            'requires_parent_meeting' => $this->require_parent_meeting && !$isEligible,
            'can_have_conditional_promotion' => $this->allow_conditional_promotion && !$isEligible,
            'remedial_available' => $this->has_remedial_option && !$isEligible
        ];
    }

    /**
     * Get subjects that allow remedial work
     */
    public function getRemedialSubjects()
    {
        if (!$this->has_remedial_option || !$this->remedial_subjects) {
            return [];
        }

        return $this->remedial_subjects;
    }

    /**
     * Validate promotion weightages (should add up to 100)
     */
    public function validateWeightages($weightages = null)
    {
        $weights = $weightages ?? $this->promotion_weightages ?? [];

        if (empty($weights)) {
            return false;
        }

        $total = array_sum($weights);
        return $total === 100;
    }
}
