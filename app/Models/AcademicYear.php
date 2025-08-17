<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class AcademicYear extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'year_label',
        'display_name',
        'start_date',
        'end_date',
        'is_current',
        'status',
        'promotion_start_date',
        'promotion_end_date',
        'settings'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'promotion_start_date' => 'date',
        'promotion_end_date' => 'date',
        'is_current' => 'boolean',
        'settings' => 'array'
    ];

    /**
     * Relationships
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function promotionCriteria()
    {
        return $this->hasMany(PromotionCriteria::class);
    }

    public function studentPromotions()
    {
        return $this->hasMany(StudentPromotion::class);
    }

    public function promotionBatches()
    {
        return $this->hasMany(PromotionBatch::class);
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class, 'academic_year', 'year_label');
    }

    /**
     * Scopes
     */
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInPromotionPeriod($query)
    {
        return $query->where('status', 'promotion_period');
    }

    /**
     * Helper Methods
     */
    public function isPromotionPeriod()
    {
        $today = Carbon::today();

        return $this->status === 'promotion_period' ||
            ($this->promotion_start_date && $this->promotion_end_date &&
                $today->between($this->promotion_start_date, $this->promotion_end_date));
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function canStartPromotion()
    {
        $today = Carbon::today();

        return $this->promotion_start_date &&
            $today->greaterThanOrEqualTo($this->promotion_start_date) &&
            in_array($this->status, ['active', 'promotion_period']);
    }

    public function getPromotionDaysRemaining()
    {
        if (!$this->promotion_end_date) {
            return null;
        }

        $today = Carbon::today();
        $endDate = Carbon::parse($this->promotion_end_date);

        return $today->lessThan($endDate) ? $today->diffInDays($endDate) : 0;
    }

    /**
     * Generate next academic year label
     */
    public function getNextAcademicYearLabel()
    {
        // Extract years from current label (e.g., "2024-25" -> ["2024", "25"])
        $parts = explode('-', $this->year_label);
        if (count($parts) === 2) {
            $startYear = (int)$parts[0];
            $endYear = (int)('20' . $parts[1]); // Convert "25" to "2025"

            $nextStartYear = $startYear + 1;
            $nextEndYear = $endYear + 1;

            return $nextStartYear . '-' . substr($nextEndYear, 2);
        }

        return null;
    }

    /**
     * Get promotion statistics
     */
    public function getPromotionStatistics()
    {
        $promotions = $this->studentPromotions();

        return [
            'total_students' => $promotions->count(),
            'promoted' => $promotions->where('promotion_status', 'promoted')->count(),
            'conditionally_promoted' => $promotions->where('promotion_status', 'conditionally_promoted')->count(),
            'failed' => $promotions->where('promotion_status', 'failed')->count(),
            'pending' => $promotions->where('promotion_status', 'pending')->count(),
            'graduated' => $promotions->where('promotion_status', 'graduated')->count(),
            'transferred' => $promotions->where('promotion_status', 'transferred')->count(),
            'withdrawn' => $promotions->where('promotion_status', 'withdrawn')->count(),
        ];
    }

    /**
     * Check if academic year can be marked as completed
     */
    public function canBeCompleted()
    {
        $pendingPromotions = $this->studentPromotions()
            ->where('promotion_status', 'pending')
            ->count();

        return $pendingPromotions === 0;
    }
}
