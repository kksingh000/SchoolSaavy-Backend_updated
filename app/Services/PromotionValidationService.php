<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\PromotionCriteria;
use App\Models\StudentPromotion;
use App\Models\Student;
use App\Models\ClassRoom;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PromotionValidationService
{
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
            case 'parent':
                return $user->parent?->students?->first()?->school_id;
            case 'student':
                return $user->student?->school_id;
            default:
                return null;
        }
    }

    /**
     * Comprehensive promotion readiness validation
     *
     * @param int $academicYearId
     * @return array
     * @throws \Exception
     */
    public function validatePromotionReadiness($academicYearId)
    {
        $schoolId = $this->getSchoolId();

        $validationResult = [
            'is_ready' => false,
            'checks' => [
                'current_year_status' => false,
                'promotion_period_active' => false,
                'next_year_exists' => false,
                'criteria_defined' => false,
                'no_pending_evaluations' => false,
                'classes_have_targets' => false,
                'students_enrolled' => false
            ],
            'errors' => [],
            'warnings' => [],
            'suggestions' => [],
            'statistics' => []
        ];

        try {
            // 1. Validate Current Academic Year Status
            $this->validateCurrentAcademicYearStatus($academicYearId, $schoolId, $validationResult);

            // 2. Validate Promotion Period Status
            $this->validatePromotionPeriodStatus($academicYearId, $schoolId, $validationResult);

            // 3. Validate Next Academic Year Exists
            $this->validateNextAcademicYearExists($academicYearId, $schoolId, $validationResult);

            // 4. Validate Promotion Criteria
            $this->validatePromotionCriteria($academicYearId, $schoolId, $validationResult);

            // 5. Validate Class Targets
            $this->validateClassTargets($academicYearId, $schoolId, $validationResult);

            // 6. Validate Student Enrollment
            $this->validateStudentEnrollment($academicYearId, $schoolId, $validationResult);

            // 7. Check for Pending Evaluations
            $this->validatePendingEvaluations($academicYearId, $schoolId, $validationResult);

            // 8. Generate Statistics
            $this->generateValidationStatistics($academicYearId, $schoolId, $validationResult);

            // Determine overall readiness
            $validationResult['is_ready'] = $this->determineOverallReadiness($validationResult);
        } catch (\Exception $e) {
            Log::error('Promotion validation failed: ' . $e->getMessage(), [
                'academic_year_id' => $academicYearId,
                'school_id' => $schoolId
            ]);

            $validationResult['errors'][] = 'Validation process failed: ' . $e->getMessage();
        }

        return $validationResult;
    }

    /**
     * Validate current academic year status
     */
    private function validateCurrentAcademicYearStatus($academicYearId, $schoolId, &$result)
    {
        $academicYear = AcademicYear::forSchool($schoolId)->find($academicYearId);

        if (!$academicYear) {
            $result['errors'][] = 'Academic year not found';
            return;
        }

        if (!$academicYear->is_current) {
            $result['errors'][] = 'Selected academic year is not the current active year';
            $result['suggestions'][] = 'Set this academic year as current before starting promotions';
            return;
        }

        if (!in_array($academicYear->status, ['active', 'promotion_period'])) {
            $result['errors'][] = "Academic year status is '{$academicYear->status}'. Expected 'active' or 'promotion_period'";
            $result['suggestions'][] = 'Academic year must be active to start promotions';
            return;
        }

        $result['checks']['current_year_status'] = true;
    }

    /**
     * Validate promotion period status
     */
    private function validatePromotionPeriodStatus($academicYearId, $schoolId, &$result)
    {
        $academicYear = AcademicYear::forSchool($schoolId)->find($academicYearId);

        if (!$academicYear) {
            return; // Already handled in previous validation
        }

        if ($academicYear->status === 'active') {
            $result['warnings'][] = 'Promotion period has not been started yet';
            $result['suggestions'][] = 'Start promotion period before evaluating students';
            return;
        }

        if ($academicYear->status === 'promotion_period') {
            $result['checks']['promotion_period_active'] = true;
        }
    }

    /**
     * Validate next academic year exists
     */
    private function validateNextAcademicYearExists($academicYearId, $schoolId, &$result)
    {
        $currentYear = AcademicYear::forSchool($schoolId)->find($academicYearId);

        if (!$currentYear) {
            return; // Already handled
        }

        // Check if next year exists
        $nextYearLabel = $this->generateNextYearLabel($currentYear->year_label);
        $nextYear = AcademicYear::forSchool($schoolId)
            ->where('year_label', $nextYearLabel)
            ->first();

        if (!$nextYear) {
            $result['errors'][] = "Next academic year ({$nextYearLabel}) does not exist";
            $result['suggestions'][] = "Create next academic year ({$nextYearLabel}) before promoting students";
            return;
        }

        if ($nextYear->status !== 'upcoming') {
            $result['warnings'][] = "Next academic year status is '{$nextYear->status}'. Expected 'upcoming'";
        }

        $result['checks']['next_year_exists'] = true;
    }

    /**
     * Validate promotion criteria are defined
     */
    private function validatePromotionCriteria($academicYearId, $schoolId, &$result)
    {
        $criteriaCount = PromotionCriteria::forSchool($schoolId)
            ->forAcademicYear($academicYearId)
            ->active()
            ->count();

        if ($criteriaCount === 0) {
            $result['errors'][] = 'No promotion criteria defined for this academic year';
            $result['suggestions'][] = 'Define promotion criteria for classes before starting evaluations';
            return;
        }

        // Check if all active classes have criteria
        $classesWithoutCriteria = $this->getClassesWithoutCriteria($academicYearId, $schoolId);

        if ($classesWithoutCriteria->count() > 0) {
            $classNames = $classesWithoutCriteria->pluck('name')->join(', ');
            $result['warnings'][] = "Classes without promotion criteria: {$classNames}";
            $result['suggestions'][] = 'Define promotion criteria for all classes to ensure complete evaluation';
        }

        $result['checks']['criteria_defined'] = true;
        $result['statistics']['criteria_count'] = $criteriaCount;
        $result['statistics']['classes_without_criteria'] = $classesWithoutCriteria->count();
    }

    /**
     * Validate class promotion targets
     */
    private function validateClassTargets($academicYearId, $schoolId, &$result)
    {
        $criteriaWithoutTargets = PromotionCriteria::forSchool($schoolId)
            ->forAcademicYear($academicYearId)
            ->active()
            ->whereNull('to_class_id')
            ->with('fromClass')
            ->get();

        if ($criteriaWithoutTargets->count() > 0) {
            $classNames = $criteriaWithoutTargets->pluck('fromClass.name')->join(', ');
            $result['warnings'][] = "Classes without promotion targets: {$classNames}";
            $result['suggestions'][] = 'Define target classes for promotion or provide them during bulk evaluation';
        }

        $result['checks']['classes_have_targets'] = $criteriaWithoutTargets->count() === 0;
        $result['statistics']['classes_without_targets'] = $criteriaWithoutTargets->count();
    }

    /**
     * Validate student enrollment
     */
    private function validateStudentEnrollment($academicYearId, $schoolId, &$result)
    {
        $enrolledStudents = Student::forSchool($schoolId)
            ->whereHas('classes', function ($query) use ($academicYearId) {
                $query->where('class_student.academic_year_id', $academicYearId)
                    ->where('class_student.is_active', true);
            })
            ->count();

        if ($enrolledStudents === 0) {
            $result['errors'][] = 'No students enrolled in classes for this academic year';
            $result['suggestions'][] = 'Ensure students are properly enrolled in classes before starting promotions';
            return;
        }

        $result['checks']['students_enrolled'] = true;
        $result['statistics']['total_enrolled_students'] = $enrolledStudents;
    }

    /**
     * Check for pending evaluations
     */
    private function validatePendingEvaluations($academicYearId, $schoolId, &$result)
    {
        $pendingEvaluations = StudentPromotion::forSchool($schoolId)
            ->forAcademicYear($academicYearId)
            ->whereIn('promotion_status', ['pending', 'under_review'])
            ->count();

        if ($pendingEvaluations > 0) {
            $result['warnings'][] = "{$pendingEvaluations} students have pending evaluations";
            $result['suggestions'][] = 'Complete or resolve pending evaluations before applying promotions';
        } else {
            $result['checks']['no_pending_evaluations'] = true;
        }

        $result['statistics']['pending_evaluations'] = $pendingEvaluations;
    }

    /**
     * Generate validation statistics
     */
    private function generateValidationStatistics($academicYearId, $schoolId, &$result)
    {
        // Students by promotion status
        $promotionStats = StudentPromotion::forSchool($schoolId)
            ->forAcademicYear($academicYearId)
            ->selectRaw('promotion_status, COUNT(*) as count')
            ->groupBy('promotion_status')
            ->pluck('count', 'promotion_status')
            ->toArray();

        $result['statistics']['promotion_status_breakdown'] = $promotionStats;

        // Classes ready for promotion
        $classesReady = PromotionCriteria::forSchool($schoolId)
            ->forAcademicYear($academicYearId)
            ->active()
            ->whereNotNull('to_class_id')
            ->count();

        $result['statistics']['classes_ready_for_promotion'] = $classesReady;

        // Performance summary
        $result['statistics']['readiness_percentage'] = $this->calculateReadinessPercentage($result['checks']);
    }

    /**
     * Determine overall promotion readiness
     */
    private function determineOverallReadiness($result)
    {
        $criticalChecks = [
            'current_year_status',
            'next_year_exists',
            'criteria_defined',
            'students_enrolled'
        ];

        // All critical checks must pass
        foreach ($criticalChecks as $check) {
            if (!$result['checks'][$check]) {
                return false;
            }
        }

        // If no critical errors, system is ready
        return empty($result['errors']);
    }

    /**
     * Get classes without promotion criteria
     */
    private function getClassesWithoutCriteria($academicYearId, $schoolId)
    {
        $classesWithCriteria = PromotionCriteria::forSchool($schoolId)
            ->forAcademicYear($academicYearId)
            ->pluck('from_class_id')
            ->unique();

        return ClassRoom::forSchool($schoolId)
            ->whereNotIn('id', $classesWithCriteria)
            ->whereHas('students', function ($query) use ($academicYearId) {
                $query->where('class_student.academic_year_id', $academicYearId)
                    ->where('class_student.is_active', true);
            })
            ->get();
    }

    /**
     * Generate next academic year label
     */
    private function generateNextYearLabel($currentLabel)
    {
        // Example: 2024-25 -> 2025-26
        if (preg_match('/(\d{4})-(\d{2})/', $currentLabel, $matches)) {
            $startYear = (int)$matches[1] + 1;
            $endYear = (int)$matches[2] + 1;
            return $startYear . '-' . str_pad($endYear, 2, '0', STR_PAD_LEFT);
        }

        return null;
    }

    /**
     * Calculate readiness percentage
     */
    private function calculateReadinessPercentage($checks)
    {
        $totalChecks = count($checks);
        $passedChecks = count(array_filter($checks));

        return $totalChecks > 0 ? round(($passedChecks / $totalChecks) * 100, 1) : 0;
    }

    /**
     * Quick validation for single operations
     */
    public function quickValidation($academicYearId, $operation = 'evaluate')
    {
        $schoolId = $this->getSchoolId();
        $academicYear = AcademicYear::forSchool($schoolId)->find($academicYearId);

        if (!$academicYear) {
            throw new \Exception('Academic year not found');
        }

        if (!$academicYear->is_current) {
            throw new \Exception('Only current academic year can be used for promotions');
        }

        if ($operation === 'evaluate' && !in_array($academicYear->status, ['active', 'promotion_period'])) {
            throw new \Exception("Cannot evaluate students. Academic year status is '{$academicYear->status}'. Start promotion period first.");
        }

        if ($operation === 'apply' && $academicYear->status !== 'promotion_period') {
            throw new \Exception("Cannot apply promotions. Academic year must be in promotion period. Current status: '{$academicYear->status}'");
        }

        // Check if next year exists for apply operations
        if ($operation === 'apply') {
            $nextYearLabel = $this->generateNextYearLabel($academicYear->year_label);
            $nextYear = AcademicYear::forSchool($schoolId)->where('year_label', $nextYearLabel)->first();

            if (!$nextYear) {
                throw new \Exception("Cannot apply promotions. Next academic year ({$nextYearLabel}) does not exist. Create it first.");
            }
        }

        return true;
    }

    /**
     * Validate data consistency for promotion recovery
     */
    public function validateDataConsistency($academicYearId)
    {
        $schoolId = $this->getSchoolId();

        $issues = [
            'same_year_promotions' => [],
            'missing_target_years' => [],
            'orphaned_promotions' => [],
            'duplicate_enrollments' => []
        ];

        // Check for students promoted within the same academic year
        $sameYearPromotions = StudentPromotion::forSchool($schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereIn('promotion_status', ['promoted', 'conditionally_promoted'])
            ->whereHas('student', function ($query) use ($academicYearId) {
                $query->whereHas('classes', function ($subQuery) use ($academicYearId) {
                    $subQuery->where('class_student.academic_year_id', $academicYearId)
                        ->where('class_student.is_active', true);
                });
            })
            ->with(['student', 'fromClass', 'toClass'])
            ->get();

        if ($sameYearPromotions->count() > 0) {
            $issues['same_year_promotions'] = $sameYearPromotions->map(function ($promotion) {
                return [
                    'student_id' => $promotion->student_id,
                    'student_name' => $promotion->student->name,
                    'from_class' => $promotion->fromClass->name,
                    'to_class' => $promotion->toClass ? $promotion->toClass->name : 'No target',
                    'promotion_id' => $promotion->id
                ];
            })->toArray();
        }

        return $issues;
    }
}
