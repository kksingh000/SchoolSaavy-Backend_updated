<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackActivity
{
    /**
     * Handle an incoming request and log the activity.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        $response = $next($request);
        
        // Only log for authenticated users
        if ($request->user()) {
            $this->logActivity($request, $response, $startTime);
        }
        
        return $response;
    }

    /**
     * Log the activity based on request details
     */
    protected function logActivity(Request $request, Response $response, float $startTime): void
    {
        $method = $request->method();
        $path = $request->path();
        $statusCode = $response->getStatusCode();
        
        // Skip certain routes
        if ($this->shouldSkip($path)) {
            return;
        }
        
        // Only log successful state-changing operations
        if ($statusCode >= 200 && $statusCode < 300 && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $module = $this->extractModule($path);
            $action = $this->extractAction($method, $path);
            $description = $this->generateDescription($method, $module, $path);
            
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
            
            $properties = [
                'method' => $method,
                'path' => $path,
                'status_code' => $statusCode,
                'response_time_ms' => $responseTime,
            ];
            
            ActivityLogger::log(
                $action,
                $module,
                $description,
                null,
                $properties
            );
        }
    }

    /**
     * Determine if the request should be skipped
     */
    protected function shouldSkip(string $path): bool
    {
        $skipPaths = [
            'api/activity-logs',
            'sanctum/',
            'api/csrf-cookie',
            'api/dashboard',
            'api/statistics',
        ];
        
        foreach ($skipPaths as $skip) {
            if (str_contains($path, $skip)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Extract module from path
     */
    protected function extractModule(string $path): string
    {
        $parts = explode('/', $path);
        
        // API routes typically: api/module/...
        if (isset($parts[1])) {
            $module = $parts[1];
            
            // Clean up module name
            $module = str_replace(['-', '_'], ' ', $module);
            $module = ucwords($module);
            
            return $module;
        }
        
        return 'System';
    }

    /**
     * Extract action from method and path
     */
    protected function extractAction(string $method, string $path): string
    {
        $actions = [
            'POST' => 'create',
            'PUT' => 'update',
            'PATCH' => 'update',
            'DELETE' => 'delete',
        ];
        
        return $actions[$method] ?? 'action';
    }

    /**
     * Generate human-readable description
     */
    protected function generateDescription(string $method, string $module, string $path): string
    {
        $action = match($method) {
            'POST' => 'Created',
            'PUT', 'PATCH' => 'Updated',
            'DELETE' => 'Deleted',
            default => 'Modified',
        };
        
        return "{$action} {$module}";
    }
}
