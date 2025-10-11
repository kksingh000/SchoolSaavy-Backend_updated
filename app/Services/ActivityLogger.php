<?php

namespace App\Services;

use App\Jobs\LogActivityJob;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;

class ActivityLogger
{
    /**
     * Log an activity (queued for non-blocking)
     */
    public static function log(
        string $action,
        string $module,
        string $description,
        $subject = null,
        array $properties = [],
        string $severity = 'info',
        bool $async = true
    ): void {
        $user = Auth::user();
        
        $activityData = [
            'school_id' => $user?->getSchool()?->id ?? null,
            'user_id' => $user?->id ?? null,
            'user_type' => $user?->user_type ?? null,
            'user_name' => $user?->name ?? 'System',
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id ?? null,
            'properties' => !empty($properties) ? $properties : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'severity' => $severity,
        ];

        if ($async) {
            // Dispatch to queue (non-blocking)
            LogActivityJob::dispatch($activityData);
        } else {
            // Immediate logging (use sparingly)
            try {
                ActivityLog::create($activityData);
            } catch (\Exception $e) {
                Log::error('Failed to log activity immediately', [
                    'error' => $e->getMessage(),
                    'data' => $activityData,
                ]);
            }
        }
    }

    /**
     * Log a create action
     */
    public static function created(string $module, $subject, ?string $description = null): void
    {
        self::log(
            'create',
            $module,
            $description ?? "Created {$module}",
            $subject,
            ['created' => true]
        );
    }

    /**
     * Log an update action
     */
    public static function updated(string $module, $subject, array $changes = [], ?string $description = null): void
    {
        self::log(
            'update',
            $module,
            $description ?? "Updated {$module}",
            $subject,
            ['changes' => $changes]
        );
    }

    /**
     * Log a delete action
     */
    public static function deleted(string $module, $subject, ?string $description = null): void
    {
        self::log(
            'delete',
            $module,
            $description ?? "Deleted {$module}",
            $subject,
            ['deleted' => true],
            'warning'
        );
    }

    /**
     * Log a view action
     */
    public static function viewed(string $module, $subject, ?string $description = null): void
    {
        self::log(
            'view',
            $module,
            $description ?? "Viewed {$module}",
            $subject
        );
    }

    /**
     * Log authentication
     */
    public static function login(string $email, bool $success = true): void
    {
        self::log(
            'login',
            'auth',
            $success ? "User logged in: {$email}" : "Failed login attempt: {$email}",
            null,
            ['email' => $email, 'success' => $success],
            $success ? 'info' : 'warning'
        );
    }

    /**
     * Log logout
     */
    public static function logout(): void
    {
        self::log(
            'logout',
            'auth',
            'User logged out'
        );
    }

    /**
     * Log export action
     */
    public static function exported(string $module, string $format, int $recordCount = 0): void
    {
        self::log(
            'export',
            $module,
            "Exported {$recordCount} {$module} records as {$format}",
            null,
            ['format' => $format, 'count' => $recordCount]
        );
    }

    /**
     * Log import action
     */
    public static function imported(string $module, int $successCount, int $failedCount = 0): void
    {
        self::log(
            'import',
            $module,
            "Imported {$successCount} {$module} records" . ($failedCount > 0 ? " ({$failedCount} failed)" : ""),
            null,
            ['success' => $successCount, 'failed' => $failedCount],
            $failedCount > 0 ? 'warning' : 'info'
        );
    }

    /**
     * Log error
     */
    public static function error(string $module, string $description, array $context = []): void
    {
        self::log(
            'error',
            $module,
            $description,
            null,
            $context,
            'error'
        );
    }

    /**
     * Get recent activities for school
     */
    public static function getRecentActivities(int $schoolId, int $limit = 50)
    {
        return ActivityLog::forSchool($schoolId)
            ->with('user:id,name,email,user_type')
            ->recent($limit)
            ->get();
    }

    /**
     * Get user activities
     */
    public static function getUserActivities(int $userId, int $limit = 50)
    {
        return ActivityLog::forUser($userId)
            ->recent($limit)
            ->get();
    }

    /**
     * Get module activities
     */
    public static function getModuleActivities(string $module, ?int $schoolId = null, int $limit = 50)
    {
        $query = ActivityLog::forModule($module);
        
        if ($schoolId) {
            $query->forSchool($schoolId);
        }
        
        return $query->recent($limit)->get();
    }

    /**
     * Clean old logs (for maintenance)
     */
    public static function cleanOldLogs(int $daysToKeep = 90): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        return ActivityLog::where('created_at', '<', $cutoffDate)->delete();
    }
}
