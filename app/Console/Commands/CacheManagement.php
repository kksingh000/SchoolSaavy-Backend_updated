<?php

namespace App\Console\Commands;

use App\Services\CacheManagementService;
use Illuminate\Console\Command;

class CacheManagement extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:api 
                           {action : Action to perform (clear, stats, invalidate)}
                           {--resource= : Resource type to invalidate (students, classes, etc.)}
                           {--school= : School ID to target}
                           {--user= : User ID to target}';

    /**
     * The console command description.
     */
    protected $description = 'Manage API cache (clear, stats, selective invalidation)';

    protected CacheManagementService $cacheManager;

    public function __construct(CacheManagementService $cacheManager)
    {
        parent::__construct();
        $this->cacheManager = $cacheManager;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'clear':
                return $this->clearCache();
            case 'stats':
                return $this->showStats();
            case 'invalidate':
                return $this->invalidateCache();
            default:
                $this->error("Unknown action: {$action}");
                $this->info("Available actions: clear, stats, invalidate");
                return 1;
        }
    }

    private function clearCache(): int
    {
        $this->info('Clearing all API cache...');
        $this->cacheManager->clearAllApiCache();
        $this->info('✅ All API cache cleared successfully!');
        return 0;
    }

    private function showStats(): int
    {
        $this->info('📊 API Cache Statistics:');
        $this->newLine();

        $stats = $this->cacheManager->getCacheStats();

        $this->table(
            ['Resource Type', 'Total Keys', 'Active Keys'],
            collect($stats['by_resource'])->map(function ($data, $resource) {
                return [
                    $resource,
                    $data['total_keys'],
                    $data['active_keys']
                ];
            })->toArray()
        );

        $this->newLine();
        $this->info("Total Active Cached Keys: {$stats['total_cached_keys']}");

        return 0;
    }

    private function invalidateCache(): int
    {
        $resource = $this->option('resource');
        $schoolId = $this->option('school');
        $userId = $this->option('user');

        if (!$resource) {
            $this->error('Resource type is required for invalidation');
            $this->info('Use --resource=students, --resource=classes, etc.');
            return 1;
        }

        $this->info("Invalidating cache for resource: {$resource}");

        if ($schoolId) {
            $this->info("Targeting school ID: {$schoolId}");
        }

        if ($userId) {
            $this->info("Targeting user ID: {$userId}");
        }

        $this->cacheManager->invalidateResourceCache(
            $resource,
            $schoolId ? (int) $schoolId : null,
            $userId ? (int) $userId : null
        );

        $this->info('✅ Cache invalidated successfully!');
        return 0;
    }
}
