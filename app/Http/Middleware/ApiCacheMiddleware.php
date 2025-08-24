<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class ApiCacheMiddleware
{
    /**
     * Handle an incoming request and implement smart caching
     */
    public function handle(Request $request, Closure $next, ...$options)
    {
        // Only cache GET requests
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        // Parse cache options
        $cacheConfig = $this->parseCacheOptions($options);

        // Skip caching if explicitly disabled
        if ($cacheConfig['disabled']) {
            return $next($request);
        }

        // Generate cache key
        $cacheKey = $this->generateCacheKey($request, $cacheConfig);

        // Check if we have cached response
        if (Cache::has($cacheKey)) {
            $cachedData = Cache::get($cacheKey);

            // Return cached response with cache headers
            return response()->json($cachedData['data'], $cachedData['status'])
                ->header('X-Cache-Status', 'HIT')
                ->header('X-Cache-Key', $cacheKey)
                ->header('X-Cached-At', $cachedData['cached_at']);
        }

        // Execute the request
        $response = $next($request);

        // Only cache successful JSON responses
        if ($this->shouldCacheResponse($response, $cacheConfig)) {
            $this->cacheResponse($cacheKey, $response, $cacheConfig);
        }

        // Add cache headers to response
        return $response
            ->header('X-Cache-Status', 'MISS')
            ->header('X-Cache-Key', $cacheKey);
    }

    /**
     * Parse caching options from middleware parameters
     */
    private function parseCacheOptions(array $options): array
    {
        $config = [
            'ttl' => 300, // 5 minutes default
            'disabled' => false,
            'vary_by_user' => true,
            'vary_by_school' => true,
            'vary_by_role' => false,
            'exclude_params' => [],
            'only_params' => [],
        ];

        foreach ($options as $option) {
            if (str_contains($option, ':')) {
                [$key, $value] = explode(':', $option, 2);

                switch ($key) {
                    case 'ttl':
                        $config['ttl'] = (int) $value;
                        break;
                    case 'disabled':
                        $config['disabled'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'vary_by_user':
                        $config['vary_by_user'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'vary_by_school':
                        $config['vary_by_school'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'vary_by_role':
                        $config['vary_by_role'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'exclude_params':
                        $config['exclude_params'] = explode(',', $value);
                        break;
                    case 'only_params':
                        $config['only_params'] = explode(',', $value);
                        break;
                }
            }
        }

        return $config;
    }

    /**
     * Generate a unique cache key for the request
     */
    private function generateCacheKey(Request $request, array $config): string
    {
        $keyParts = [
            'api_cache',
            md5($request->url()),
            $request->method()
        ];

        // Add user-specific caching
        if ($config['vary_by_user'] && Auth::check()) {
            $keyParts[] = 'user_' . Auth::id();
        }

        // Add school-specific caching
        if ($config['vary_by_school'] && $request->has('school_id')) {
            $keyParts[] = 'school_' . $request->get('school_id');
        }

        // Add role-specific caching
        if ($config['vary_by_role'] && Auth::check()) {
            $keyParts[] = 'role_' . Auth::user()->user_type;
        }

        // Add query parameters to cache key
        $queryParams = $this->getRelevantQueryParams($request, $config);
        if (!empty($queryParams)) {
            $keyParts[] = 'params_' . md5(serialize($queryParams));
        }

        return implode(':', $keyParts);
    }

    /**
     * Get relevant query parameters for cache key generation
     */
    private function getRelevantQueryParams(Request $request, array $config): array
    {
        $params = $request->query();

        // Remove excluded parameters
        foreach ($config['exclude_params'] as $excludeParam) {
            unset($params[$excludeParam]);
        }

        // If only_params is specified, include only those parameters
        if (!empty($config['only_params'])) {
            $params = array_intersect_key($params, array_flip($config['only_params']));
        }

        // Remove common parameters that shouldn't affect cache
        $commonExcludes = ['_', 'timestamp', 'cache_bust', 'v'];
        foreach ($commonExcludes as $exclude) {
            unset($params[$exclude]);
        }

        ksort($params); // Sort for consistent cache keys
        return $params;
    }

    /**
     * Determine if response should be cached
     */
    private function shouldCacheResponse($response, array $config): bool
    {
        // Only cache successful responses
        if (!$response instanceof JsonResponse || $response->getStatusCode() >= 400) {
            return false;
        }

        $data = $response->getData(true);

        // Don't cache error responses
        if (isset($data['status']) && $data['status'] === 'error') {
            return false;
        }

        // Don't cache empty responses
        if (empty($data) || (isset($data['data']) && empty($data['data']))) {
            return false;
        }

        return true;
    }

    /**
     * Cache the response
     */
    private function cacheResponse(string $cacheKey, JsonResponse $response, array $config): void
    {
        $cacheData = [
            'data' => $response->getData(true),
            'status' => $response->getStatusCode(),
            'cached_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $cacheData, $config['ttl']);

        // Store cache key for potential invalidation
        $this->storeCacheKeyForInvalidation($cacheKey, $response->getData(true));
    }

    /**
     * Store cache keys for potential invalidation based on data types
     */
    private function storeCacheKeyForInvalidation(string $cacheKey, array $responseData): void
    {
        // Determine resource type from URL or response data
        $resourceType = $this->detectResourceType($responseData);

        if ($resourceType) {
            $invalidationKey = "cache_keys:{$resourceType}";
            $existingKeys = Cache::get($invalidationKey, []);
            $existingKeys[] = $cacheKey;
            $existingKeys = array_unique($existingKeys);

            // Store cache keys list for 24 hours
            Cache::put($invalidationKey, $existingKeys, 1440);
        }
    }

    /**
     * Detect resource type from response data for cache invalidation
     */
    private function detectResourceType(array $responseData): ?string
    {
        // Check URL patterns
        $url = request()->url();

        if (str_contains($url, '/students')) return 'students';
        if (str_contains($url, '/classes')) return 'classes';
        if (str_contains($url, '/teachers')) return 'teachers';
        if (str_contains($url, '/assessments')) return 'assessments';
        if (str_contains($url, '/assignments')) return 'assignments';
        if (str_contains($url, '/attendance')) return 'attendance';
        if (str_contains($url, '/events')) return 'events';
        if (str_contains($url, '/gallery')) return 'gallery';
        if (str_contains($url, '/modules')) return 'modules';
        if (str_contains($url, '/academic-years')) return 'academic_years';

        return null;
    }
}
