<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

trait OctaneCache
{
    /**
     * Get from Octane cache with fallback to default cache
     */
    protected function octaneGet(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::store('octane')->get($key, $default);
        } catch (\Exception $e) {
            Log::warning('Octane cache get failed, falling back to default cache', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return Cache::get($key, $default);
        }
    }

    /**
     * Put data in Octane cache with fallback to default cache
     */
    protected function octanePut(string $key, mixed $value, int $ttl = 300): bool
    {
        try {
            return Cache::store('octane')->put($key, $value, $ttl);
        } catch (\Exception $e) {
            Log::warning('Octane cache put failed, falling back to default cache', [
                'key' => $key,
                'ttl' => $ttl,
                'error' => $e->getMessage()
            ]);
            return Cache::put($key, $value, $ttl);
        }
    }

    /**
     * Remember with Octane cache (get or execute callback and cache result)
     */
    protected function octaneRemember(string $key, int $ttl, callable $callback): mixed
    {
        try {
            return Cache::store('octane')->remember($key, $ttl, $callback);
        } catch (\Exception $e) {
            Log::warning('Octane cache remember failed, falling back to default cache', [
                'key' => $key,
                'ttl' => $ttl,
                'error' => $e->getMessage()
            ]);
            return Cache::remember($key, $ttl, $callback);
        }
    }

    /**
     * Forget from Octane cache and default cache
     */
    protected function octaneForget(string $key): bool
    {
        $octaneResult = true;
        $defaultResult = true;

        try {
            $octaneResult = Cache::store('octane')->forget($key);
        } catch (\Exception $e) {
            Log::warning('Octane cache forget failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            $octaneResult = false;
        }

        try {
            $defaultResult = Cache::forget($key);
        } catch (\Exception $e) {
            Log::warning('Default cache forget failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            $defaultResult = false;
        }

        return $octaneResult && $defaultResult;
    }

    /**
     * Generate school-specific cache key
     */
    protected function schoolCacheKey(string $baseKey, ?int $schoolId = null): string
    {
        $schoolId = $schoolId ?? $this->getCurrentSchoolId();
        return "school_{$schoolId}:{$baseKey}";
    }

    /**
     * Generate user-specific cache key
     */
    protected function userCacheKey(string $baseKey, ?int $userId = null): string
    {
        $userId = $userId ?? Auth::id();
        $schoolId = $this->getCurrentSchoolId();
        return "school_{$schoolId}:user_{$userId}:{$baseKey}";
    }

    /**
     * Generate academic year specific cache key
     */
    protected function academicYearCacheKey(string $baseKey, ?int $academicYearId = null): string
    {
        $academicYearId = $academicYearId ?? request('academic_year_id');
        $schoolId = $this->getCurrentSchoolId();
        return "school_{$schoolId}:year_{$academicYearId}:{$baseKey}";
    }

    /**
     * Cache dashboard data with auto-expiration
     */
    protected function cacheDashboardData(string $type, callable $callback, int $ttl = 300): mixed
    {
        $cacheKey = $this->userCacheKey("dashboard_{$type}");

        return $this->octaneRemember($cacheKey, $ttl, $callback);
    }

    /**
     * Cache expensive queries with school context
     */
    protected function cacheSchoolQuery(string $queryName, callable $callback, int $ttl = 600): mixed
    {
        $cacheKey = $this->schoolCacheKey($queryName);

        return $this->octaneRemember($cacheKey, $ttl, $callback);
    }

    /**
     * Clear all school-related cache
     */
    protected function clearSchoolCache(?int $schoolId = null): void
    {
        $schoolId = $schoolId ?? $this->getCurrentSchoolId();

        // Common cache patterns to clear
        $cachePatterns = [
            "dashboard_*",
            "attendance_*",
            "students_*",
            "teachers_*",
            "classes_*",
            "assignments_*",
            "events_*",
            "statistics_*"
        ];

        foreach ($cachePatterns as $pattern) {
            $this->octaneForget($this->schoolCacheKey($pattern, $schoolId));
        }

        Log::info("Cleared school cache", ['school_id' => $schoolId]);
    }

    /**
     * Get current school ID for cache key generation
     */
    private function getCurrentSchoolId(): ?int
    {
        if (method_exists($this, 'getSchoolId')) {
            return $this->getSchoolId();
        }

        if (Auth::check()) {
            $user = Auth::user();
            if (method_exists($user, 'getSchoolId')) {
                return $user->getSchoolId();
            }
        }

        return request('school_id');
    }

    /**
     * Get cache TTL based on data type
     */
    protected function getCacheTTL(string $dataType): int
    {
        return match ($dataType) {
            'dashboard' => 300,      // 5 minutes
            'statistics' => 600,     // 10 minutes  
            'attendance' => 900,     // 15 minutes
            'students' => 1800,      // 30 minutes
            'teachers' => 1800,      // 30 minutes
            'classes' => 3600,       // 1 hour
            'settings' => 7200,      // 2 hours
            'modules' => 14400,      // 4 hours
            default => 300           // 5 minutes default
        };
    }

    /**
     * Batch cache operations for better performance
     */
    protected function octanePutMany(array $values, int $ttl = 300): bool
    {
        try {
            foreach ($values as $key => $value) {
                Cache::store('octane')->put($key, $value, $ttl);
            }
            return true;
        } catch (\Exception $e) {
            Log::warning('Octane batch cache failed', [
                'keys' => array_keys($values),
                'error' => $e->getMessage()
            ]);

            // Fallback to default cache
            foreach ($values as $key => $value) {
                Cache::put($key, $value, $ttl);
            }
            return false;
        }
    }
}
