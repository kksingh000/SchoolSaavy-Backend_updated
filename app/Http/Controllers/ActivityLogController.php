<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class ActivityLogController extends BaseController
{
    /**
     * Get recent activities for the school
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $schoolId = $user->getSchool()->id;
            
            $perPage = $request->input('per_page', 50);
            $module = $request->input('module');
            $action = $request->input('action');
            $userId = $request->input('user_id');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $search = $request->input('search');
            
            $query = ActivityLog::query()
                ->where('school_id', $schoolId)
                ->with('user:id,name,email,user_type');
            
            // Apply filters
            if ($module) {
                $query->where('module', $module);
            }
            
            if ($action) {
                $query->where('action', $action);
            }
            
            if ($userId) {
                $query->where('user_id', $userId);
            }
            
            if ($dateFrom) {
                $query->where('created_at', '>=', $dateFrom);
            }
            
            if ($dateTo) {
                $query->where('created_at', '<=', $dateTo . ' 23:59:59');
            }
            
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('user_name', 'like', "%{$search}%")
                      ->orWhere('module', 'like', "%{$search}%");
                });
            }
            
            $activities = $query->latest()->paginate($perPage);
            
            return $this->successResponse($activities, 'Activity logs retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve activity logs: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get activity statistics
     */
    public function statistics(Request $request)
    {
        try {
            $user = $request->user();
            $schoolId = $user->getSchool()->id;
            
            $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->input('date_to', now()->format('Y-m-d'));
            
            // Total activities
            $totalActivities = ActivityLog::where('school_id', $schoolId)
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->count();
            
            // Activities by action
            $byAction = ActivityLog::where('school_id', $schoolId)
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->get()
                ->pluck('count', 'action');
            
            // Activities by module
            $byModule = ActivityLog::where('school_id', $schoolId)
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->selectRaw('module, COUNT(*) as count')
                ->groupBy('module')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->pluck('count', 'module');
            
            // Most active users
            $topUsers = ActivityLog::where('school_id', $schoolId)
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->whereNotNull('user_id')
                ->selectRaw('user_id, user_name, COUNT(*) as activity_count')
                ->groupBy('user_id', 'user_name')
                ->orderByDesc('activity_count')
                ->limit(10)
                ->get();
            
            // Daily activity trend
            $dailyTrend = ActivityLog::where('school_id', $schoolId)
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            
            return $this->successResponse([
                'total_activities' => $totalActivities,
                'by_action' => $byAction,
                'by_module' => $byModule,
                'top_users' => $topUsers,
                'daily_trend' => $dailyTrend,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                ],
            ], 'Statistics retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve statistics: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get user's own activity history
     */
    public function myActivity(Request $request)
    {
        try {
            $user = $request->user();
            $perPage = $request->input('per_page', 50);
            
            $activities = ActivityLog::where('user_id', $user->id)
                ->latest()
                ->paginate($perPage);
            
            return $this->successResponse($activities, 'Your activity history retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve activity history: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get activity for a specific entity
     */
    public function entityActivity(Request $request)
    {
        try {
            $subjectType = $request->input('subject_type');
            $subjectId = $request->input('subject_id');
            
            if (!$subjectType || !$subjectId) {
                return $this->errorResponse('subject_type and subject_id are required', [], 400);
            }
            
            $user = $request->user();
            $schoolId = $user->getSchool()->id;
            
            $activities = ActivityLog::where('school_id', $schoolId)
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->with('user:id,name,email,user_type')
                ->latest()
                ->paginate(20);
            
            return $this->successResponse($activities, 'Entity activity history retrieved successfully');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve entity activity: ' . $e->getMessage(), [], 500);
        }
    }
}
