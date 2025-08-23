<?php

namespace App\Services\SuperAdmin;

use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Parents;
use App\Models\GalleryMedia;
use App\Models\Module;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = School::class;
    }

    /**
     * Get platform overview stats
     */
    public function getPlatformOverview()
    {
        return [
            'total_schools' => School::count(),
            'active_schools' => School::where('is_active', true)->count(),
            'inactive_schools' => School::where('is_active', false)->count(),
            'total_users' => Student::count() + Teacher::count() + Parents::count(),
            'schools_created_this_month' => School::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'schools_created_today' => School::whereDate('created_at', today())->count(),
        ];
    }

    /**
     * Get school-wise analytics
     */
    public function getSchoolAnalytics($schoolId = null)
    {
        $query = School::query();

        if ($schoolId) {
            $query->where('id', $schoolId);
        }

        return $query->withCount([
            'students',
            'teachers',
            'parents' => function ($q) {
                $q->whereHas('students');
            }
        ])->get()->map(function ($school) {
            return [
                'school_id' => $school->id,
                'school_name' => $school->name,
                'total_students' => $school->students_count,
                'total_teachers' => $school->teachers_count,
                'total_parents' => $school->parents_count,
                'total_users' => $school->students_count + $school->teachers_count + $school->parents_count,
                'active_modules' => $this->getSchoolActiveModules($school->id),
                'media_stats' => $this->getSchoolMediaStats($school->id),
            ];
        });
    }

    /**
     * Get module usage analytics across all schools
     */
    public function getModuleUsageAnalytics()
    {
        return Module::withCount(['schools' => function ($query) {
            $query->where('is_active', true);
        }])->get()->map(function ($module) {
            return [
                'module_id' => $module->id,
                'module_name' => $module->name,
                'display_name' => $module->display_name,
                'schools_using' => $module->schools_count,
                'usage_percentage' => School::count() > 0
                    ? round(($module->schools_count / School::count()) * 100, 2)
                    : 0
            ];
        });
    }

    /**
     * Get media upload statistics
     */
    public function getMediaStatistics($schoolId = null, $period = 'month')
    {
        $query = GalleryMedia::query();

        if ($schoolId) {
            $query->whereHas('album', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            });
        }

        // Apply period filter
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereDate('created_at', '>=', now()->subDays(7));
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                break;
        }

        $stats = $query->selectRaw("
            COUNT(*) as total_files,
            COUNT(CASE WHEN type = 'photo' THEN 1 END) as total_images,
            COUNT(CASE WHEN type = 'video' THEN 1 END) as total_videos,
            COALESCE(SUM(file_size), 0) as total_size_bytes
        ")->first();

        return [
            'total_files' => $stats->total_files ?? 0,
            'total_images' => $stats->total_images ?? 0,
            'total_videos' => $stats->total_videos ?? 0,
            'total_size_mb' => round(($stats->total_size_bytes ?? 0) / (1024 * 1024), 2),
            'period' => $period
        ];
    }

    /**
     * Get user growth analytics
     */
    public function getUserGrowthAnalytics($period = 'month')
    {
        $dateFormat = match ($period) {
            'week' => '%Y-%m-%d',
            'month' => '%Y-%m-%d',
            'year' => '%Y-%m',
            default => '%Y-%m-%d'
        };

        $startDate = match ($period) {
            'week' => now()->subDays(7),
            'month' => now()->subDays(30),
            'year' => now()->subYear(),
            default => now()->subDays(30)
        };

        $students = Student::selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as date, COUNT(*) as count")
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $teachers = Teacher::selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as date, COUNT(*) as count")
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $schools = School::selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as date, COUNT(*) as count")
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'students' => $students,
            'teachers' => $teachers,
            'schools' => $schools,
            'period' => $period
        ];
    }

    /**
     * Get top performing schools
     */
    public function getTopPerformingSchools($limit = 10)
    {
        return School::withCount(['students', 'teachers'])
            ->where('is_active', true)
            ->get()
            ->map(function ($school) {
                $school->performance_score = $this->calculateSchoolPerformanceScore($school);
                return $school;
            })
            ->sortByDesc('performance_score')
            ->take($limit)
            ->values();
    }

    /**
     * Get school active modules
     */
    private function getSchoolActiveModules($schoolId)
    {
        $school = School::find($schoolId);
        if (!$school) {
            return [];
        }

        return $school->modules()
            ->wherePivot('status', 'active')
            ->get()
            ->map(function ($module) {
                return [
                    'module_name' => $module->name,
                    'display_name' => $module->slug,
                    'activated_at' => $module->pivot->activated_at,
                ];
            });
    }

    /**
     * Get school media statistics
     */
    private function getSchoolMediaStats($schoolId)
    {
        $stats = GalleryMedia::whereHas('album', function ($q) use ($schoolId) {
            $q->where('school_id', $schoolId);
        })
            ->selectRaw("
            COUNT(*) as total_files,
            COUNT(CASE WHEN type = 'photo' THEN 1 END) as total_images,
            COUNT(CASE WHEN type = 'video' THEN 1 END) as total_videos,
            COALESCE(SUM(file_size), 0) as total_size_bytes
        ")
            ->first();

        return [
            'total_files' => $stats->total_files ?? 0,
            'total_images' => $stats->total_images ?? 0,
            'total_videos' => $stats->total_videos ?? 0,
            'total_size_mb' => round(($stats->total_size_bytes ?? 0) / (1024 * 1024), 2),
        ];
    }

    /**
     * Calculate school performance score
     */
    private function calculateSchoolPerformanceScore($school)
    {
        $studentCount = $school->students_count ?? 0;
        $teacherCount = $school->teachers_count ?? 0;

        // Basic scoring algorithm - can be enhanced based on requirements
        $score = 0;

        // Points for user base
        $score += min($studentCount * 2, 200); // Max 200 points for students
        $score += min($teacherCount * 10, 100); // Max 100 points for teachers

        // Points for being active
        if ($school->is_active) {
            $score += 50;
        }

        // Points for recent activity (schools created in last 6 months)
        if ($school->created_at->gt(now()->subMonths(6))) {
            $score += 25;
        }

        return $score;
    }
}
