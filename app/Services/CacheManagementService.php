<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class CacheManagementService
{
    /**
     * Invalidate cache for a specific resource type
     */
    public function invalidateResourceCache(string $resourceType, ?int $schoolId = null, ?int $userId = null): void
    {
        try {
            // Get all cache keys for this resource type
            $invalidationKey = "cache_keys:{$resourceType}";
            $cacheKeys = Cache::get($invalidationKey, []);

            $invalidatedCount = 0;

            foreach ($cacheKeys as $cacheKey) {
                // If school/user specific invalidation is needed, check the cache key
                if ($schoolId && !str_contains($cacheKey, "school_{$schoolId}")) {
                    continue;
                }

                if ($userId && !str_contains($cacheKey, "user_{$userId}")) {
                    continue;
                }

                if (Cache::forget($cacheKey)) {
                    $invalidatedCount++;
                }
            }

            // Clean up the invalidation key list
            if (!$schoolId && !$userId) {
                Cache::forget($invalidationKey);
            }

            Log::info("Cache invalidated for resource: {$resourceType}", [
                'invalidated_keys' => $invalidatedCount,
                'school_id' => $schoolId,
                'user_id' => $userId
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to invalidate cache for resource: {$resourceType}", [
                'error' => $e->getMessage(),
                'school_id' => $schoolId,
                'user_id' => $userId
            ]);
        }
    }

    /**
     * Invalidate all cache for a specific school
     */
    public function invalidateSchoolCache(int $schoolId): void
    {
        try {
            // Get all cache keys and filter by school
            $pattern = "*school_{$schoolId}*";
            $this->invalidateCacheByPattern($pattern);

            Log::info("All cache invalidated for school: {$schoolId}");
        } catch (\Exception $e) {
            Log::error("Failed to invalidate school cache: {$schoolId}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Invalidate cache for a specific user
     */
    public function invalidateUserCache(int $userId): void
    {
        try {
            $pattern = "*user_{$userId}*";
            $this->invalidateCacheByPattern($pattern);

            Log::info("All cache invalidated for user: {$userId}");
        } catch (\Exception $e) {
            Log::error("Failed to invalidate user cache: {$userId}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear all API cache
     */
    public function clearAllApiCache(): void
    {
        try {
            $pattern = "api_cache:*";
            $invalidatedCount = $this->invalidateCacheByPattern($pattern);

            // Clear all invalidation keys
            $resourceTypes = [
                'students',
                'classes',
                'teachers',
                'assessments',
                'assignments',
                'attendance',
                'events',
                'gallery',
                'modules',
                'academic_years'
            ];

            foreach ($resourceTypes as $resourceType) {
                Cache::forget("cache_keys:{$resourceType}");
            }

            Log::info("All API cache cleared", ['invalidated_keys' => $invalidatedCount]);
        } catch (\Exception $e) {
            Log::error("Failed to clear all API cache", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        $resourceTypes = [
            'students',
            'classes',
            'teachers',
            'assessments',
            'assignments',
            'attendance',
            'events',
            'gallery',
            'modules',
            'academic_years'
        ];

        $stats = [
            'total_cached_keys' => 0,
            'by_resource' => []
        ];

        foreach ($resourceTypes as $resourceType) {
            $invalidationKey = "cache_keys:{$resourceType}";
            $keys = Cache::get($invalidationKey, []);
            $activeKeys = 0;

            // Count active cache keys
            foreach ($keys as $key) {
                if (Cache::has($key)) {
                    $activeKeys++;
                }
            }

            $stats['by_resource'][$resourceType] = [
                'total_keys' => count($keys),
                'active_keys' => $activeKeys
            ];

            $stats['total_cached_keys'] += $activeKeys;
        }

        return $stats;
    }

    /**
     * Invalidate cache by pattern (Redis-specific)
     */
    private function invalidateCacheByPattern(string $pattern): int
    {
        $invalidatedCount = 0;

        try {
            // For Redis cache store
            if (config('cache.default') === 'redis') {
                $redis = \Illuminate\Support\Facades\Redis::connection();
                $keys = $redis->keys($pattern);

                if (!empty($keys)) {
                    $redis->del($keys);
                    $invalidatedCount = count($keys);
                }
            } else {
                // For other cache stores, we'll need to track keys manually
                Log::warning('Pattern-based cache invalidation only supports Redis');
            }
        } catch (\Exception $e) {
            Log::error('Failed to invalidate cache by pattern', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
        }

        return $invalidatedCount;
    }

    /**
     * Auto-invalidate related caches when data changes
     */
    public function autoInvalidate(string $action, string $resourceType, array $data = []): void
    {
        $schoolId = $data['school_id'] ?? request()->get('school_id');
        $userId = Auth::check() ? Auth::user()->id : null;

        // Always invalidate the primary resource
        $this->invalidateResourceCache($resourceType, $schoolId, $userId);

        // Invalidate related resources based on the action
        $this->invalidateRelatedResources($action, $resourceType, $data);
    }

    /**
     * Invalidate related resources based on data relationships
     */
    private function invalidateRelatedResources(string $action, string $resourceType, array $data): void
    {
        $schoolId = $data['school_id'] ?? request()->get('school_id');

        // Define resource relationships for cache invalidation
        $relationships = [
            'students' => ['classes', 'attendance', 'assessments', 'assignments'],
            'classes' => ['students', 'teachers', 'assignments', 'assessments', 'attendance'],
            'teachers' => ['classes', 'assignments', 'assessments'],
            'assignments' => ['students', 'classes', 'teachers'],
            'assessments' => ['students', 'classes', 'teachers'],
            'attendance' => ['students', 'classes'],
            'events' => ['students', 'classes', 'teachers'],
            'academic_years' => ['students', 'classes', 'assessments', 'assignments'],
        ];

        // Invalidate related resources
        $relatedResources = $relationships[$resourceType] ?? [];

        foreach ($relatedResources as $relatedResource) {
            $this->invalidateResourceCache($relatedResource, $schoolId);
        }
    }
}
