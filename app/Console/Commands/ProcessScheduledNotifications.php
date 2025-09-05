<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class ProcessScheduledNotifications extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notifications:process-scheduled';

    /**
     * The console command description.
     */
    protected $description = 'Process scheduled notifications that are due to be sent';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $this->info('Processing scheduled notifications...');

        try {
            $results = $notificationService->processScheduledNotifications();

            $totalProcessed = count($results);
            $successCount = count(array_filter($results, fn($r) => $r['success']));
            $failureCount = $totalProcessed - $successCount;

            $this->info("Processed {$totalProcessed} scheduled notifications");
            $this->info("✓ Success: {$successCount}");

            if ($failureCount > 0) {
                $this->warn("✗ Failed: {$failureCount}");

                // Show details of failures
                foreach ($results as $result) {
                    if (!$result['success']) {
                        $this->error("  - Notification ID {$result['notification_id']}: {$result['error']}");
                    }
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to process scheduled notifications: ' . $e->getMessage());
            Log::error('Process scheduled notifications command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}
