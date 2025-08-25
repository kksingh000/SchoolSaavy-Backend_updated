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
use App\Services\PromotionValidationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PromotionService extends BaseService
{
    protected $studentPerformanceController;
    protected $validationService;

    public function __construct()
    {
        parent::__construct();
        $this->studentPerformanceController = new \App\Http\Controllers\StudentPerformanceController();
        $this->validationService = new PromotionValidationService();
    }

    protected function initializeModel()
    {
        $this->model = StudentPromotion::class;
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
            case 'parent':
                return $user->parent?->students?->first()?->school_id;
            case 'student':
                return $user->student?->school_id;
            default:
                return null;
        }
    }

    /**
     * Create or update promotion criteria for a class
     */
    public function createPromotionCriteria(array $data)
    {
        $schoolId = $this->getSchoolId();

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
        $schoolId = $this->getSchoolId();

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
     * Get paginated promotion criteria with filters
     */
    public function getCriteriaPaginated($academicYearId, $perPage = 15, $filters = [])
    {
        $schoolId = $this->getSchoolId();

        $query = PromotionCriteria::forSchool($schoolId)
            ->forAcademicYear($academicYearId)
            ->with(['fromClass', 'toClass'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (!empty($filters['from_class_id'])) {
            $query->where('from_class_id', $filters['from_class_id']);
        }

        if (!empty($filters['to_class_id'])) {
            $query->where('to_class_id', $filters['to_class_id']);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
            // 'all' means no filter
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('fromClass', function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%");
                })->orWhereHas('toClass', function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%");
                });
            });
        }

        return $query->paginate($perPage)->through(function ($criteria) {
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
                'remedial_subjects' => $criteria->getRemedialSubjects(),
                'is_active' => $criteria->is_active,
                'created_at' => $criteria->created_at,
                'updated_at' => $criteria->updated_at
            ];
        });
    }

    /**
     * Evaluate a single student for promotion
     */
    public function evaluateStudent($studentId, $academicYearId, $userId = null)
    {
        // STRICT VALIDATION - Prevent promotion errors
        $this->validatePromotionOperation($academicYearId, 'evaluate');

        $schoolId = $this->getSchoolId();
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
     * Evaluate a single student for promotion (batch processing version)
     * This method is optimized for queue processing without DB transactions
     */
    public function evaluateStudentForBatch($studentId, $academicYearId, $userId = null, $targetClassIds = null)
    {
        $schoolId = $this->getSchoolId();
        $userId = $userId ?? Auth::id();

        try {
            $student = Student::where('school_id', $schoolId)->findOrFail($studentId);
            $currentClass = $student->getCurrentClassForYear($academicYearId);

            if (!$currentClass) {
                throw new \Exception('Student is not enrolled in any class for this academic year');
            }

            // Determine the target class
            $targetClassId = null;

            if ($targetClassIds && is_array($targetClassIds)) {
                // Map current class to target class based on grade levels or sequence
                $targetClass = $this->getTargetClassForStudent($currentClass, $targetClassIds, $schoolId);
                $targetClassId = $targetClass ? $targetClass->id : null;
            }

            // First try to find criteria with specific target class
            $criteria = PromotionCriteria::where('school_id', $schoolId)
                ->where('academic_year_id', $academicYearId)
                ->where('from_class_id', $currentClass->id)
                ->where('is_active', true);

            if ($targetClassId) {
                $criteria->where('to_class_id', $targetClassId);
            }

            $criteria = $criteria->first();

            // If no specific criteria found, try without target class restriction
            if (!$criteria) {
                $criteria = PromotionCriteria::where('school_id', $schoolId)
                    ->where('academic_year_id', $academicYearId)
                    ->where('from_class_id', $currentClass->id)
                    ->where('is_active', true)
                    ->first();
            }

            if (!$criteria) {
                throw new \Exception('No promotion criteria found for class ' . $currentClass->name);
            }

            // Use target class from bulk operation if available, otherwise use criteria default
            $finalTargetClassId = $targetClassId ?: $criteria->to_class_id;

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
                    'to_class_id' => $finalTargetClassId,
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

            return $promotion->fresh();
        } catch (\Exception $e) {
            Log::error('Student promotion evaluation failed in batch: ' . $e->getMessage(), [
                'student_id' => $studentId,
                'academic_year_id' => $academicYearId
            ]);
            throw $e;
        }
    }

    /**
     * Bulk evaluate students for promotion (Queue-based)
     */
    public function bulkEvaluateStudents($academicYearId, $classIds = null, $userId = null, $targetClassIds = null)
    {
        // STRICT VALIDATION - Comprehensive readiness check
        $validation = $this->getPromotionReadiness($academicYearId);

        if (!$validation['is_ready']) {
            $errorMessage = 'Promotion system not ready: ' . implode(', ', $validation['errors']);

            // Log validation failure with detailed information
            Log::warning('Bulk evaluation blocked by validation', [
                'academic_year_id' => $academicYearId,
                'validation_result' => $validation,
                'user_id' => Auth::id()
            ]);

            throw new \Exception($errorMessage);
        }

        // Log any warnings but allow to continue
        if (!empty($validation['warnings'])) {
            Log::info('Bulk evaluation proceeding with warnings', [
                'academic_year_id' => $academicYearId,
                'warnings' => $validation['warnings']
            ]);
        }

        $schoolId = $this->getSchoolId();
        $userId = $userId ?? Auth::id();

        // Create promotion batch record
        $batch = PromotionBatch::create([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'batch_name' => 'Bulk Evaluation - ' . now()->format('Y-m-d H:i'),
            'description' => 'Automated bulk evaluation of students',
            'class_filters' => $classIds,
            'target_class_ids' => $targetClassIds,
            'created_by' => $userId,
            'status' => 'queued' // Initially queued
        ]);

        // Dispatch the job to queue for background processing
        \App\Jobs\ProcessBulkPromotionEvaluation::dispatch(
            $batch->id,
            $academicYearId,
            $classIds,
            $userId,
            $schoolId,
            $targetClassIds
        )->onQueue('promotion-evaluation');

        return $batch;
    }

    /**
     * Apply promotion decisions (move students to new classes) - Queue-based
     */
    public function applyPromotions($academicYearId, $promotionIds = null)
    {
        // CRITICAL VALIDATION - Prevent data corruption
        $this->validatePromotionOperation($academicYearId, 'apply');

        // Additional consistency check for existing data
        $consistency = $this->checkDataConsistency($academicYearId);

        if (!empty($consistency['same_year_promotions'])) {
            $count = count($consistency['same_year_promotions']);
            Log::error('Data consistency issue detected before applying promotions', [
                'academic_year_id' => $academicYearId,
                'same_year_promotions_count' => $count,
                'details' => $consistency['same_year_promotions']
            ]);

            throw new \Exception("Data consistency error: {$count} students were promoted within the same academic year. Fix data integrity before proceeding.");
        }

        $schoolId = $this->getSchoolId();
        $userId = Auth::id();

        // Dispatch the job to queue for background processing
        \App\Jobs\ProcessPromotionApplication::dispatch(
            $academicYearId,
            $promotionIds,
            $schoolId,
            $userId
        )->onQueue('promotion-application');

        // Count how many promotions will be processed for immediate response
        $query = StudentPromotion::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereIn('promotion_status', ['promoted', 'conditionally_promoted']);

        if ($promotionIds) {
            $query->whereIn('id', $promotionIds);
        }

        return $query->count();
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
        $schoolId = $this->getSchoolId();

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
     * Get paginated student promotions with filters
     */
    public function getStudentPromotionsPaginated($academicYearId, $perPage = 15, $filters = [])
    {
        $schoolId = $this->getSchoolId();

        $query = StudentPromotion::forSchool($schoolId)
            ->forAcademicYear($academicYearId)
            ->with([
                'student' => function ($query) {
                    $query->with(['classes' => function ($classQuery) {
                        $classQuery->wherePivot('is_active', true);
                    }]);
                },
                'fromClass',
                'toClass',
                'evaluatedBy'
            ])
            ->orderBy('updated_at', 'desc');

        // Apply filters
        if (!empty($filters['class_id'])) {
            $query->where('from_class_id', $filters['class_id']);
        }

        if (!empty($filters['promotion_status'])) {
            $query->where('promotion_status', $filters['promotion_status']);
        }

        if (!empty($filters['requires_remedial'])) {
            $query->where('requires_remedial', $filters['requires_remedial'] === 'true');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($mainQuery) use ($search) {
                $mainQuery->whereHas('student', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('admission_number', 'like', "%{$search}%");
                })->orWhereHas('student.classes', function ($classQuery) use ($search) {
                    $classQuery->where('class_student.is_active', true)
                        ->where('class_student.roll_number', 'like', "%{$search}%");
                });
            });
        }

        return $query->paginate($perPage)->through(function ($promotion) {
            // Get the student's roll number from current class
            $rollNumber = null;
            if ($promotion->student && $promotion->student->classes->isNotEmpty()) {
                $currentClass = $promotion->student->classes->first();
                $rollNumber = $currentClass->pivot->roll_number ?? null;
            }

            return [
                'id' => $promotion->id,
                'student' => [
                    'id' => $promotion->student->id,
                    'name' => $promotion->student->name,
                    'first_name' => $promotion->student->first_name,
                    'last_name' => $promotion->student->last_name,
                    'admission_number' => $promotion->student->admission_number,
                    'roll_number' => $rollNumber,
                ],
                'from_class' => [
                    'id' => $promotion->fromClass->id,
                    'name' => $promotion->fromClass->name,
                    'grade_level' => $promotion->fromClass->grade_level,
                ],
                'to_class' => $promotion->to_class_id ? [
                    'id' => $promotion->toClass->id,
                    'name' => $promotion->toClass->name,
                    'grade_level' => $promotion->toClass->grade_level,
                ] : null,
                'promotion_status' => $promotion->promotion_status,
                'attendance_percentage' => $promotion->attendance_percentage,
                'assignment_average' => $promotion->assignment_average,
                'assessment_average' => $promotion->assessment_average,
                'overall_score' => $promotion->overall_score,
                'final_percentage' => $promotion->final_percentage,
                'promotion_reason' => $promotion->promotion_reason,
                'requires_remedial' => $promotion->requires_remedial,
                'parent_meeting_required' => $promotion->parent_meeting_required,
                'evaluation_date' => $promotion->evaluation_date,
                'evaluated_by' => $promotion->evaluatedBy ? [
                    'id' => $promotion->evaluatedBy->id,
                    'name' => $promotion->evaluatedBy->name,
                ] : null,
                'created_at' => $promotion->created_at,
                'updated_at' => $promotion->updated_at,
            ];
        });
    }

    /**
     * Get paginated promotion batches with filters
     */
    public function getBatchesPaginated($academicYearId, $perPage = 10, $filters = [])
    {
        $schoolId = $this->getSchoolId();

        $query = PromotionBatch::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->with(['createdBy'])
            ->withCount(['studentPromotions'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['created_date_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_date_from']);
        }

        if (!empty($filters['created_date_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_date_to']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('batch_name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        }

        return $query->paginate($perPage);
    }

    /**
     * Override promotion decision manually
     */
    public function overridePromotionDecision($promotionId, $newStatus, $reason)
    {
        $schoolId = $this->getSchoolId();
        $userId = Auth::id();

        $promotion = StudentPromotion::forSchool($schoolId)->findOrFail($promotionId);

        if (!$promotion->canBeOverridden()) {
            throw new \Exception('This promotion decision cannot be overridden');
        }

        $promotion->overrideDecision($newStatus, $reason, $userId);

        return $promotion->fresh();
    }

    /**
     * Get detailed batch progress with metrics
     */
    public function getBatchProgress($batchId)
    {
        $schoolId = $this->getSchoolId();

        $batch = PromotionBatch::where('school_id', $schoolId)
            ->with(['createdBy', 'processedBy', 'academicYear'])
            ->findOrFail($batchId);

        // Calculate estimated completion time if still processing
        $estimatedCompletion = null;
        if ($batch->isProcessing() && $batch->processed_students > 0) {
            $processingStarted = $batch->processing_started_at;
            $currentTime = now();
            $timeElapsed = $processingStarted->diffInSeconds($currentTime);

            $averageTimePerStudent = $timeElapsed / $batch->processed_students;
            $remainingStudents = $batch->total_students - $batch->processed_students;
            $estimatedSecondsLeft = $averageTimePerStudent * $remainingStudents;

            $estimatedCompletion = $currentTime->addSeconds($estimatedSecondsLeft);
        }

        return [
            'batch' => $batch,
            'estimated_completion' => $estimatedCompletion,
            'processing_rate' => $batch->processed_students > 0
                ? round($batch->processed_students / max(1, $batch->processing_started_at?->diffInMinutes(now())), 2)
                : 0 // students per minute
        ];
    }

    /**
     * Get the appropriate target class for a student based on current class and available target classes
     */
    private function getTargetClassForStudent($currentClass, $targetClassIds, $schoolId)
    {
        // If no specific target classes provided, check if class has a predefined promotion path
        if (empty($targetClassIds)) {
            if ($currentClass->promotes_to_class_id) {
                return \App\Models\ClassRoom::find($currentClass->promotes_to_class_id);
            }
            return null;
        }

        // Get all available target classes
        $targetClasses = \App\Models\ClassRoom::whereIn('id', $targetClassIds)
            ->where('school_id', $schoolId)
            ->orderBy('grade_level')
            ->get();

        if ($targetClasses->isEmpty()) {
            return null;
        }

        // Simple logic: find the target class with the next grade level
        $nextGradeLevel = $currentClass->grade_level + 1;

        $targetClass = $targetClasses->where('grade_level', $nextGradeLevel)->first();

        // If no exact next grade level found, return the first available target class
        if (!$targetClass) {
            $targetClass = $targetClasses->first();
        }

        return $targetClass;
    }

    /**
     * Get comprehensive promotion readiness validation
     */
    public function getPromotionReadiness($academicYearId)
    {
        return $this->validationService->validatePromotionReadiness($academicYearId);
    }

    /**
     * Validate promotion operation before execution
     */
    public function validatePromotionOperation($academicYearId, $operation = 'evaluate')
    {
        return $this->validationService->quickValidation($academicYearId, $operation);
    }

    /**
     * Check and validate data consistency
     */
    public function checkDataConsistency($academicYearId)
    {
        return $this->validationService->validateDataConsistency($academicYearId);
    }
}
