<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseController;
use App\Services\SuperAdmin\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AnalyticsController extends BaseController
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get platform overview statistics
     */
    public function platformOverview()
    {
        $data = $this->analyticsService->getPlatformOverview();
        return $this->successResponse($data, 'Platform overview retrieved successfully');
    }

    /**
     * Get school-wise analytics
     */
    public function schoolAnalytics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'nullable|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $schoolId = $request->get('school_id');
        $data = $this->analyticsService->getSchoolAnalytics($schoolId);

        return $this->successResponse($data, 'School analytics retrieved successfully');
    }

    /**
     * Get module usage analytics
     */
    public function moduleUsage()
    {
        $data = $this->analyticsService->getModuleUsageAnalytics();
        return $this->successResponse($data, 'Module usage analytics retrieved successfully');
    }

    /**
     * Get media statistics
     */
    public function mediaStatistics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'nullable|exists:schools,id',
            'period' => 'nullable|in:today,week,month,year'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $schoolId = $request->get('school_id');
        $period = $request->get('period', 'month');

        $data = $this->analyticsService->getMediaStatistics($schoolId, $period);

        return $this->successResponse($data, 'Media statistics retrieved successfully');
    }

    /**
     * Get user growth analytics
     */
    public function userGrowth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'period' => 'nullable|in:week,month,year'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $period = $request->get('period', 'month');
        $data = $this->analyticsService->getUserGrowthAnalytics($period);

        return $this->successResponse($data, 'User growth analytics retrieved successfully');
    }

    /**
     * Get top performing schools
     */
    public function topSchools(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $limit = $request->get('limit', 10);
        $data = $this->analyticsService->getTopPerformingSchools($limit);

        return $this->successResponse($data, 'Top performing schools retrieved successfully');
    }

    /**
     * Get detailed analytics for a specific school
     */
    public function schoolDetailedAnalytics($schoolId)
    {
        $validator = Validator::make(['school_id' => $schoolId], [
            'school_id' => 'required|exists:schools,id'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('School not found', null, 404);
        }

        $analytics = $this->analyticsService->getSchoolAnalytics($schoolId);
        $mediaStats = $this->analyticsService->getMediaStatistics($schoolId, 'month');

        $data = [
            'school_analytics' => $analytics->first(),
            'media_statistics' => $mediaStats,
        ];

        return $this->successResponse($data, 'Detailed school analytics retrieved successfully');
    }
}
