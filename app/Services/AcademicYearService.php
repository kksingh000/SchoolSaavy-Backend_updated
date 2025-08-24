<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\PromotionCriteria;
use App\Models\StudentPromotion;
use App\Models\PromotionBatch;
use App\Models\Student;
use App\Models\ClassRoom;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AcademicYearService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = AcademicYear::class;
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
     * Create a new academic year
     */
    public function createAcademicYear(array $data)
    {
        $schoolId = $this->getSchoolId();

        DB::beginTransaction();
        try {
            // Validate year label uniqueness
            $exists = AcademicYear::where('school_id', $schoolId)
                ->where('year_label', $data['year_label'])
                ->exists();

            if ($exists) {
                throw new \Exception('Academic year ' . $data['year_label'] . ' already exists');
            }

            // Set only one academic year as current
            if ($data['is_current'] ?? false) {
                AcademicYear::where('school_id', $schoolId)
                    ->update(['is_current' => false]);
            }

            $academicYear = AcademicYear::create([
                'school_id' => $schoolId,
                'year_label' => $data['year_label'],
                'display_name' => $data['display_name'] ?? 'Academic Year ' . str_replace('-', '-20', $data['year_label']),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'promotion_start_date' => $data['promotion_start_date'] ?? null,
                'promotion_end_date' => $data['promotion_end_date'] ?? null,
                'is_current' => $data['is_current'] ?? false,
                'status' => $data['status'] ?? 'upcoming',
                'settings' => $data['settings'] ?? []
            ]);

            DB::commit();
            return $academicYear;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get current academic year for school
     */
    public function getCurrentAcademicYear()
    {
        $schoolId = $this->getSchoolId();

        return AcademicYear::forSchool($schoolId)
            ->current()
            ->first();
    }

    /**
     * Set academic year as current
     */
    public function setAsCurrentYear($academicYearId)
    {
        $schoolId = $this->getSchoolId();

        DB::beginTransaction();
        try {
            // Unset all current years
            AcademicYear::forSchool($schoolId)->update(['is_current' => false]);

            // Set the selected year as current
            $academicYear = AcademicYear::forSchool($schoolId)->findOrFail($academicYearId);
            $academicYear->update(['is_current' => true]);

            DB::commit();
            return $academicYear;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Start promotion period for academic year
     */
    public function startPromotionPeriod($academicYearId)
    {
        $schoolId = $this->getSchoolId();

        $academicYear = AcademicYear::forSchool($schoolId)->findOrFail($academicYearId);

        if (!$academicYear->canStartPromotion()) {
            throw new \Exception('Promotion period cannot be started for this academic year');
        }

        $academicYear->update(['status' => 'promotion_period']);

        return $academicYear;
    }

    /**
     * Complete academic year (mark as completed)
     */
    public function completeAcademicYear($academicYearId)
    {
        $schoolId = $this->getSchoolId();

        $academicYear = AcademicYear::forSchool($schoolId)->findOrFail($academicYearId);

        if (!$academicYear->canBeCompleted()) {
            throw new \Exception('Academic year cannot be completed. There are pending promotions.');
        }

        $academicYear->update(['status' => 'completed']);

        return $academicYear;
    }

    /**
     * Get academic years with statistics (with pagination and search filters)
     */
    public function getAcademicYearsWithStats($perPage = 10, $filters = [])
    {
        $schoolId = $this->getSchoolId();

        $query = AcademicYear::forSchool($schoolId)
            ->with(['promotionCriteria', 'studentPromotions']);

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('year_label', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Apply current year filter
        if (isset($filters['is_current']) && $filters['is_current'] !== '') {
            $query->where('is_current', (bool)$filters['is_current']);
        }

        // Apply date range filter
        if (!empty($filters['year_from'])) {
            $query->whereYear('start_date', '>=', $filters['year_from']);
        }

        if (!empty($filters['year_to'])) {
            $query->whereYear('end_date', '<=', $filters['year_to']);
        }

        // Apply promotion period filter
        if (!empty($filters['promotion_status'])) {
            switch ($filters['promotion_status']) {
                case 'active':
                    $query->where('status', 'promotion_period');
                    break;
                case 'upcoming':
                    $query->where('status', 'active')
                        ->whereDate('promotion_start_date', '>', now());
                    break;
                case 'completed':
                    $query->where('status', 'completed');
                    break;
            }
        }

        // Apply sorting
        $sortField = $filters['sort_by'] ?? 'start_date';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        $allowedSortFields = ['start_date', 'end_date', 'year_label', 'status', 'created_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('start_date', 'desc');
        }

        // Get paginated results
        $paginatedYears = $query->paginate($perPage);

        // Transform the data
        $paginatedYears->getCollection()->transform(function ($year) {
            $stats = $year->getPromotionStatistics();
            return [
                'id' => $year->id,
                'year_label' => $year->year_label,
                'display_name' => $year->display_name,
                'start_date' => $year->start_date,
                'end_date' => $year->end_date,
                'is_current' => $year->is_current,
                'status' => $year->status,
                'promotion_period' => [
                    'start_date' => $year->promotion_start_date,
                    'end_date' => $year->promotion_end_date,
                    'is_active' => $year->isPromotionPeriod(),
                    'days_remaining' => $year->getPromotionDaysRemaining()
                ],
                'statistics' => $stats,
                'criteria_count' => $year->promotionCriteria->count(),
                'created_at' => $year->created_at,
                'updated_at' => $year->updated_at
            ];
        });

        return $paginatedYears;
    }

    /**
     * Generate next academic year template
     */
    public function generateNextAcademicYear($currentAcademicYearId)
    {
        $schoolId = $this->getSchoolId();

        $currentYear = AcademicYear::forSchool($schoolId)->findOrFail($currentAcademicYearId);
        $nextYearLabel = $currentYear->getNextAcademicYearLabel();

        if (!$nextYearLabel) {
            throw new \Exception('Unable to generate next academic year label');
        }

        // Check if next year already exists
        $exists = AcademicYear::forSchool($schoolId)
            ->where('year_label', $nextYearLabel)
            ->exists();

        if ($exists) {
            throw new \Exception('Academic year ' . $nextYearLabel . ' already exists');
        }

        // Calculate next year dates
        $nextStartDate = Carbon::parse($currentYear->start_date)->addYear();
        $nextEndDate = Carbon::parse($currentYear->end_date)->addYear();

        return [
            'year_label' => $nextYearLabel,
            'display_name' => 'Academic Year ' . str_replace('-', '-20', $nextYearLabel),
            'start_date' => $nextStartDate->format('Y-m-d'),
            'end_date' => $nextEndDate->format('Y-m-d'),
            'promotion_start_date' => $nextEndDate->subDays(30)->format('Y-m-d'),
            'promotion_end_date' => $nextEndDate->addDays(15)->format('Y-m-d'),
            'is_current' => false,
            'status' => 'upcoming'
        ];
    }

    /**
     * Clone promotion criteria from previous year
     */
    public function clonePromotionCriteria($fromAcademicYearId, $toAcademicYearId)
    {
        $schoolId = $this->getSchoolId();

        DB::beginTransaction();
        try {
            $fromYear = AcademicYear::forSchool($schoolId)->findOrFail($fromAcademicYearId);
            $toYear = AcademicYear::forSchool($schoolId)->findOrFail($toAcademicYearId);

            $criteria = $fromYear->promotionCriteria;
            $clonedCount = 0;

            foreach ($criteria as $criterion) {
                // Check if criteria already exists for the target year and class
                $exists = PromotionCriteria::forSchool($schoolId)
                    ->forAcademicYear($toAcademicYearId)
                    ->where('from_class_id', $criterion->from_class_id)
                    ->exists();

                if (!$exists) {
                    PromotionCriteria::create([
                        'school_id' => $schoolId,
                        'from_class_id' => $criterion->from_class_id,
                        'to_class_id' => $criterion->to_class_id,
                        'academic_year_id' => $toAcademicYearId,
                        'minimum_attendance_percentage' => $criterion->minimum_attendance_percentage,
                        'minimum_assignment_average' => $criterion->minimum_assignment_average,
                        'minimum_assessment_average' => $criterion->minimum_assessment_average,
                        'minimum_overall_percentage' => $criterion->minimum_overall_percentage,
                        'promotion_weightages' => $criterion->promotion_weightages,
                        'minimum_attendance_days' => $criterion->minimum_attendance_days,
                        'maximum_disciplinary_actions' => $criterion->maximum_disciplinary_actions,
                        'require_parent_meeting' => $criterion->require_parent_meeting,
                        'grace_marks_allowed' => $criterion->grace_marks_allowed,
                        'allow_conditional_promotion' => $criterion->allow_conditional_promotion,
                        'has_remedial_option' => $criterion->has_remedial_option,
                        'remedial_subjects' => $criterion->remedial_subjects,
                        'is_active' => true
                    ]);
                    $clonedCount++;
                }
            }

            DB::commit();
            return $clonedCount;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
