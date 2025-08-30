<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledNotifications;
use Illuminate\Console\Command;

class ProcessNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled notifications and send them';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting scheduled notifications processing...');

        try {
            // Dispatch the job to handle scheduled notifications
            ProcessScheduledNotifications::dispatch();

            $this->info('Scheduled notifications processing job dispatched successfully.');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to process scheduled notifications: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
