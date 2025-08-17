<?php

namespace App\Services;

use App\Models\PromotionCriteria;
use App\Models\StudentPromotion;
use App\Models\PromotionBatch;
use App\Models\Student;
use App\Models\ClassRoom;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AssignmentSubmission;
use App\Models\AssessmentResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PromotionService extends BaseService
{
    protected $studentPerformanceController;

    public function __construct()
    {
        parent::__construct();
        $this->studentPerformanceController = new \App\Http\Controllers\StudentPerformanceController();
    }

    protected function initializeModel()
    {
        $this->model = StudentPromotion::class;
    }

    /**
     * Create or update promotion criteria for a class
     */
    public function createPromotionCriteria(array $data)
    {
        $schoolId = Auth::user()->getSchool()->id;

        return PromotionCriteria::updateOrCreate(
            [
                'school_id' => $schoolId,
                'from_class_id' => $data['from_class_id'],
                'academic_year_id' => $data['academic_year_id']
            ],
            [
                'to_class_id' => $data['to_class_id'] ?? null,
                'minimum_attendance_percentage' => $data['minimum_attendance_percentage'] ?? 75.00,
                'minimum_assignment_average' => $data['minimum_assignment_average'] ?? 50.00,
                'minimum_assessment_average' => $data['minimum_assessment_average'] ?? 50.00,
                'minimum_overall_percentage' => $data['minimum_overall_percentage'] ?? 50.00,
                'promotion_weightages' => $data['promotion_weightages'] ?? [
                    'attendance' => 20,
                    'assignments' => 40,
                    'assessments' => 40
                ],
                'minimum_attendance_days' => $data['minimum_attendance_days'] ?? null,
                'maximum_disciplinary_actions' => $data['maximum_disciplinary_actions'] ?? 5,
                'require_parent_meeting' => $data['require_parent_meeting'] ?? false,
                'grace_marks_allowed' => $data['grace_marks_allowed'] ?? 5.00,
                'allow_conditional_promotion' => $data['allow_conditional_promotion'] ?? true,
                'has_remedial_option' => $data['has_remedial_option'] ?? true,
                'remedial_subjects' => $data['remedial_subjects'] ?? null,
                'is_active' => true
            ]
        );
    }

    /**
     * Get promotion criteria for all classes in an academic year
     */
    public function getPromotionCriteria($academicYearId)
    {
        $schoolId = Auth::user()->getSchool()->id;

        return PromotionCriteria::forSchool($schoolId)
            ->forAcademicYear($academicYearId)
            ->with(['fromClass', 'toClass'])
            ->get()
            ->map(function ($criteria) {
                return [
                    'id' => $criteria->id,
                    'from_class' => [
                        'id' => $criteria->fromClass->id,
                        'name' => $criteria->fromClass->name,
                        'grade_level' => $criteria->fromClass->grade_level
                    ],
                    'to_class' => $criteria->to_class_id ? [
                        'id' => $criteria->toClass->id,
                        'name' => $criteria->toClass->name,
                        'grade_level' => $criteria->toClass->grade_level
                    ] : null,
                    'minimum_attendance_percentage' => $criteria->minimum_attendance_percentage,
                    'minimum_assignment_average' => $criteria->minimum_assignment_average,
                    'minimum_assessment_average' => $criteria->minimum_assessment_average,
                    'minimum_overall_percentage' => $criteria->minimum_overall_percentage,
                    'promotion_weightages' => $criteria->getDefaultWeightages(),
                    'grace_marks_allowed' => $criteria->grace_marks_allowed,
                    'allow_conditional_promotion' => $criteria->allow_conditional_promotion,
                    'has_remedial_option' => $criteria->has_remedial_option,
                    'remedial_subjects' => $criteria->getRemedialSubjects()
                ];
            });
    }

    /**
     * Evaluate a single student for promotion
     */
    public function evaluateStudent($studentId, $academicYearId, $userId = null)
    {
        $schoolId = Auth::user()->getSchool()->id;
        $userId = $userId ?? Auth::id();

        DB::beginTransaction();
        try {
            $student = Student::forSchool($schoolId)->findOrFail($studentId);
            $currentClass = $student->getCurrentClassForYear($academicYearId);

            if (!$currentClass) {
                throw new \Exception('Student is not enrolled in any class for this academic year');
            }

            $criteria = PromotionCriteria::forSchool($schoolId)
                ->forAcademicYear($academicYearId)
                ->forClass($currentClass->id)
                ->active()
                ->first();

            if (!$criteria) {
                throw new \Exception('No promotion criteria found for class ' . $currentClass->name);
            }

            // Get student performance data
            $performanceData = $this->getStudentPerformanceData($studentId, $academicYearId);

            // Evaluate using criteria
            $evaluation = $criteria->evaluateStudent($performanceData);

            // Create or update promotion record
            $promotion = StudentPromotion::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'academic_year_id' => $academicYearId
                ],
                [
                    'school_id' => $schoolId,
                    'from_class_id' => $currentClass->id,
                    'to_class_id' => $criteria->to_class_id,
                    'attendance_percentage' => $performanceData['attendance_percentage'],
                    'assignment_average' => $performanceData['assignment_average'],
                    'assessment_average' => $performanceData['assessment_average'],
                    'overall_score' => $evaluation['criteria_details']['weighted_score'],
                    'final_percentage' => $evaluation['criteria_details']['weighted_score'],
                    'criteria_details' => $evaluation['criteria_details'],
                    'attendance_criteria_met' => $evaluation['criteria_details']['attendance_met'],
                    'assignment_criteria_met' => $evaluation['criteria_details']['assignment_met'],
                    'assessment_criteria_met' => $evaluation['criteria_details']['assessment_met'],
                    'overall_criteria_met' => $evaluation['criteria_details']['overall_met'],
                    'promotion_status' => $evaluation['eligible_for_promotion'] ? 'promoted' : 'failed',
                    'promotion_reason' => $this->generatePromotionReason($evaluation),
                    'requires_remedial' => $evaluation['remedial_available'] && !$evaluation['eligible_for_promotion'],
                    'parent_meeting_required' => $evaluation['requires_parent_meeting'],
                    'evaluated_by' => $userId,
                    'evaluation_date' => now()
                ]
            );

            // Handle conditional promotion
            if (!$evaluation['eligible_for_promotion'] && $evaluation['can_have_conditional_promotion']) {
                $promotion->update([
                    'promotion_status' => 'conditionally_promoted',
                    'promotion_reason' => 'Conditionally promoted - requires improvement'
                ]);
            }

            DB::commit();
            return $promotion->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Student promotion evaluation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Bulk evaluate students for promotion
     */
    public function bulkEvaluateStudents($academicYearId, $classIds = null, $userId = null)
    {
        $schoolId = Auth::user()->getSchool()->id;
        $userId = $userId ?? Auth::id();

        DB::beginTransaction();
        try {
            // Create promotion batch
            $batch = PromotionBatch::create([
                'school_id' => $schoolId,
                'academic_year_id' => $academicYearId,
                'batch_name' => 'Bulk Evaluation - ' . now()->format('Y-m-d H:i'),
                'description' => 'Automated bulk evaluation of students',
                'class_filters' => $classIds,
                'created_by' => $userId,
                'status' => 'processing'
            ]);

            $batch->markAsStarted($userId);

            // Get students to evaluate
            $studentsQuery = Student::forSchool($schoolId)->forAcademicYear($academicYearId);

            if ($classIds) {
                $studentsQuery->whereHas('classes', function ($query) use ($classIds, $academicYearId) {
                    $query->whereIn('classes.id', $classIds)
                        ->where('class_student.academic_year_id', $academicYearId)
                        ->where('class_student.is_active', true);
                });
            }

            $students = $studentsQuery->get();
            $totalStudents = $students->count();

            $batch->update(['total_students' => $totalStudents]);

            $processed = 0;
            $promoted = 0;
            $failed = 0;
            $pending = 0;

            foreach ($students as $student) {
                try {
                    $promotion = $this->evaluateStudent($student->id, $academicYearId, $userId);

                    $processed++;

                    if ($promotion->isPromoted()) {
                        $promoted++;
                    } elseif ($promotion->isFailed()) {
                        $failed++;
                    } else {
                        $pending++;
                    }

                    // Update batch progress every 10 students
                    if ($processed % 10 === 0) {
                        $batch->updateProgress($processed, $promoted, $failed, $pending);
                        $batch->addToProcessingLog("Processed {$processed}/{$totalStudents} students");
                    }
                } catch (\Exception $e) {
                    $batch->addError("Failed to evaluate student {$student->name}: " . $e->getMessage());
                    $pending++;
                    Log::error('Student evaluation failed in batch', [
                        'student_id' => $student->id,
                        'batch_id' => $batch->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Final batch update
            $batch->updateProgress($processed, $promoted, $failed, $pending);
            $batch->markAsCompleted();

            DB::commit();
            return $batch->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($batch)) {
                $batch->markAsFailed($e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Apply promotion decisions (move students to new classes)
     */
    public function applyPromotions($academicYearId, $promotionIds = null)
    {
        $schoolId = Auth::user()->getSchool()->id;

        DB::beginTransaction();
        try {
            $query = StudentPromotion::forSchool($schoolId)
                ->forAcademicYear($academicYearId)
                ->whereIn('promotion_status', ['promoted', 'conditionally_promoted']);

            if ($promotionIds) {
                $query->whereIn('id', $promotionIds);
            }

            $promotions = $query->get();
            $appliedCount = 0;

            foreach ($promotions as $promotion) {
                if ($promotion->to_class_id) {
                    // Process the promotion (move student to new class)
                    $promotion->applyPromotionDecision(
                        $promotion->promotion_status,
                        $promotion->promotion_reason,
                        Auth::id()
                    );
                    $appliedCount++;
                }
            }

            DB::commit();
            return $appliedCount;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get student performance data for promotion evaluation
     */
    private function getStudentPerformanceData($studentId, $academicYearId)
    {
        // Get the academic year to determine evaluation period
        $academicYear = AcademicYear::find($academicYearId);

        if (!$academicYear) {
            throw new \Exception('Academic year not found');
        }

        $startDate = $academicYear->start_date;
        $endDate = $academicYear->end_date;

        // Calculate actual attendance percentage for the academic year
        $attendancePercentage = $this->calculateAttendancePercentage($studentId, $academicYearId, $startDate, $endDate);

        // Calculate assignment average for the academic year
        $assignmentAverage = $this->calculateAssignmentAverage($studentId, $academicYearId);

        // Calculate assessment average for the academic year
        $assessmentAverage = $this->calculateAssessmentAverage($studentId, $academicYearId);

        return [
            'attendance_percentage' => $attendancePercentage,
            'assignment_average' => $assignmentAverage,
            'assessment_average' => $assessmentAverage,
        ];
    }

    /**
     * Calculate attendance percentage for academic year
     */
    private function calculateAttendancePercentage($studentId, $academicYearId, $startDate, $endDate)
    {
        $attendanceRecords = Attendance::forStudent($studentId)
            ->forAcademicYear($academicYearId)
            ->inDateRange($startDate, $endDate)
            ->get();

        $totalDays = $attendanceRecords->count();
        if ($totalDays === 0) {
            return 0;
        }

        $presentDays = $attendanceRecords->where('status', 'present')->count();
        $lateDays = $attendanceRecords->where('status', 'late')->count();

        // Count late as half present for attendance calculation
        $effectivePresentDays = $presentDays + ($lateDays * 0.5);

        return round(($effectivePresentDays / $totalDays) * 100, 2);
    }

    /**
     * Calculate assignment average for academic year
     */
    private function calculateAssignmentAverage($studentId, $academicYearId)
    {
        $submissions = AssignmentSubmission::whereHas('assignment', function ($query) use ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        })
            ->where('student_id', $studentId)
            ->where('status', 'graded')
            ->whereNotNull('marks_obtained')
            ->get();

        if ($submissions->isEmpty()) {
            return 0;
        }

        $totalPercentage = 0;
        foreach ($submissions as $submission) {
            if ($submission->assignment->max_marks > 0) {
                $percentage = ($submission->marks_obtained / $submission->assignment->max_marks) * 100;
                $totalPercentage += $percentage;
            }
        }

        return round($totalPercentage / $submissions->count(), 2);
    }

    /**
     * Calculate assessment average for academic year
     */
    private function calculateAssessmentAverage($studentId, $academicYearId)
    {
        $results = AssessmentResult::whereHas('assessment', function ($query) use ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        })
            ->where('student_id', $studentId)
            ->whereNotNull('percentage')
            ->get();

        if ($results->isEmpty()) {
            return 0;
        }

        return round($results->avg('percentage'), 2);
    }

    /**
     * Generate promotion reason based on evaluation
     */
    private function generatePromotionReason($evaluation)
    {
        if ($evaluation['eligible_for_promotion']) {
            return 'Meets all promotion criteria';
        }

        $failedCriteria = [];
        $details = $evaluation['criteria_details'];

        if (!$details['attendance_met']) {
            $failedCriteria[] = 'insufficient attendance';
        }
        if (!$details['assignment_met']) {
            $failedCriteria[] = 'low assignment performance';
        }
        if (!$details['assessment_met']) {
            $failedCriteria[] = 'poor assessment results';
        }

        return 'Failed due to: ' . implode(', ', $failedCriteria);
    }

    /**
     * Get promotion statistics for academic year
     */
    public function getPromotionStatistics($academicYearId)
    {
        $schoolId = Auth::user()->getSchool()->id;

        $promotions = StudentPromotion::forSchool($schoolId)
            ->forAcademicYear($academicYearId)
            ->get();

        $statistics = [
            'total_students' => $promotions->count(),
            'promoted' => $promotions->where('promotion_status', 'promoted')->count(),
            'conditionally_promoted' => $promotions->where('promotion_status', 'conditionally_promoted')->count(),
            'failed' => $promotions->where('promotion_status', 'failed')->count(),
            'pending' => $promotions->where('promotion_status', 'pending')->count(),
            'requiring_remedial' => $promotions->where('requires_remedial', true)->count(),
            'parent_meetings_required' => $promotions->where('parent_meeting_required', true)
                ->where('parent_meeting_completed', false)->count()
        ];

        $statistics['promotion_rate'] = $statistics['total_students'] > 0
            ? round((($statistics['promoted'] + $statistics['conditionally_promoted']) / $statistics['total_students']) * 100, 2)
            : 0;

        return $statistics;
    }

    /**
     * Override promotion decision manually
     */
    public function overridePromotionDecision($promotionId, $newStatus, $reason)
    {
        $schoolId = Auth::user()->getSchool()->id;
        $userId = Auth::id();

        $promotion = StudentPromotion::forSchool($schoolId)->findOrFail($promotionId);

        if (!$promotion->canBeOverridden()) {
            throw new \Exception('This promotion decision cannot be overridden');
        }

        $promotion->overrideDecision($newStatus, $reason, $userId);

        return $promotion->fresh();
    }
}
