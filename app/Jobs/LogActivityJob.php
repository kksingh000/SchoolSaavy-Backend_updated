<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LogActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = [10, 30, 60]; // Retry after 10s, 30s, 60s

    /**
     * Activity data to be logged
     */
    protected array $activityData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $activityData)
    {
        $this->activityData = $activityData;
        $this->onQueue('activity-logs'); // Use dedicated queue
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            ActivityLog::create($this->activityData);
        } catch (\Exception $e) {
            Log::error('Failed to log activity', [
                'error' => $e->getMessage(),
                'data' => $this->activityData,
            ]);
            
            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Activity log job failed permanently', [
            'error' => $exception->getMessage(),
            'data' => $this->activityData,
        ]);
    }
}
