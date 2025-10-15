<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckAcademicYearController extends BaseController
{
    /**
     * Check if the school has any academic years configured
     * This endpoint is safe to call even before the user is fully redirected to dashboard
     */
    public function check(Request $request)
    {
        try {
            $schoolId = $request->school_id;
            
            if (!$schoolId) {
                return $this->errorResponse('School ID not found', null, 400);
            }
            
            // Check if any academic years exist
            $hasAcademicYears = AcademicYear::where('school_id', $schoolId)->exists();
            
            // Get current academic year if it exists
            $currentAcademicYear = AcademicYear::where('school_id', $schoolId)
                ->where('is_current', true)
                ->first();
            
            // Build response
            $response = [
                'has_academic_years' => $hasAcademicYears,
                'current_academic_year' => $currentAcademicYear ? [
                    'id' => $currentAcademicYear->id,
                    'year_label' => $currentAcademicYear->year_label,
                    'display_name' => $currentAcademicYear->display_name,
                    'status' => $currentAcademicYear->status,
                    'promotion_start_date' => $currentAcademicYear->promotion_start_date,
                    'promotion_end_date' => $currentAcademicYear->promotion_end_date,
                    'is_promotion_period' => $currentAcademicYear->isPromotionPeriod(),
                ] : null,
            ];
            
            // Add notification if no academic years
            if (!$hasAcademicYears) {
                $response['notification'] = [
                    'type' => 'warning',
                    'title' => 'Academic Year Required',
                    'message' => 'No academic year has been set up for your school. Please create an academic year to enable full functionality of the system.',
                    'action' => [
                        'text' => 'Create Academic Year',
                        'url' => '/admin/academic-years/create'
                    ],
                    'priority' => 'high',
                    'dismissible' => false,
                    'persistent' => true
                ];
            }
            
            return $this->successResponse($response, 'Academic year status retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to check academic year status: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Force clear client-side academic year cache
     * Use this when academic year status doesn't match between frontend and backend
     */
    public function clearCache(Request $request)
    {
        return $this->successResponse([
            'cache_cleared' => true,
            'timestamp' => now()->timestamp,
        ], 'Academic year cache cleared successfully. Please refresh your browser.');
    }
}