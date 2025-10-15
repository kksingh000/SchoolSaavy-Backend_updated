<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Models\AcademicYear;
use Illuminate\Http\Request;

class AcademicYearController extends BaseController
{
    /**
     * Check if the school has any academic years configured
     */
    public function check(Request $request)
    {
        try {
            $schoolId = $request->school_id;
            
            if (!$schoolId) {
                return $this->errorResponse('School ID not found', null, 400);
            }
            
            $hasAcademicYears = AcademicYear::where('school_id', $schoolId)->exists();
            $currentAcademicYear = AcademicYear::where('school_id', $schoolId)
                ->where('is_current', true)
                ->first();
            
            return $this->successResponse([
                'has_academic_years' => $hasAcademicYears,
                'current_academic_year' => $currentAcademicYear ? [
                    'id' => $currentAcademicYear->id,
                    'year_label' => $currentAcademicYear->year_label,
                    'display_name' => $currentAcademicYear->display_name,
                    'status' => $currentAcademicYear->status
                ] : null,
                'notification' => !$hasAcademicYears ? [
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
                ] : null
            ], 'Academic year status retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to check academic year status: ' . $e->getMessage(), null, 500);
        }
    }
}