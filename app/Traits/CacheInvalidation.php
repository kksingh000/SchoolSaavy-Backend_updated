<?php

namespace App\Traits;

use App\Services\CacheManagementService;

trait CacheInvalidation
{
    protected CacheManagementService $cacheManager;

    /**
     * Initialize cache manager
     */
    protected function initializeCacheInvalidation(): void
    {
        $this->cacheManager = app(CacheManagementService::class);
    }

    /**
     * Invalidate cache after data modification
     */
    protected function invalidateCache(string $action, string $resourceType, array $data = []): void
    {
        if (!isset($this->cacheManager)) {
            $this->initializeCacheInvalidation();
        }

        $this->cacheManager->autoInvalidate($action, $resourceType, $data);
    }

    /**
     * Invalidate specific resource cache
     */
    protected function invalidateResourceCache(string $resourceType, ?int $schoolId = null): void
    {
        if (!isset($this->cacheManager)) {
            $this->initializeCacheInvalidation();
        }

        $this->cacheManager->invalidateResourceCache($resourceType, $schoolId);
    }

    /**
     * Clear all cache for current school
     */
    protected function clearSchoolCache(): void
    {
        if (!isset($this->cacheManager)) {
            $this->initializeCacheInvalidation();
        }

        $schoolId = request()->get('school_id');
        if ($schoolId) {
            $this->cacheManager->invalidateSchoolCache($schoolId);
        }
    }
}
