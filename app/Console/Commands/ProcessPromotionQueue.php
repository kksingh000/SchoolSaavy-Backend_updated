<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessPromotionQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:work-promotions 
                            {--timeout=3600 : Maximum execution time}
                            {--sleep=3 : Seconds to wait when no jobs are available}
                            {--tries=3 : Number of times to attempt a job}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process promotion evaluation and application queues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeout = $this->option('timeout');
        $sleep = $this->option('sleep');
        $tries = $this->option('tries');

        $this->info('Starting promotion queue processing...');
        $this->info("Timeout: {$timeout}s, Sleep: {$sleep}s, Max Tries: {$tries}");

        // Process both promotion queues
        $queues = ['promotion-evaluation', 'promotion-application'];

        foreach ($queues as $queue) {
            $this->info("Processing queue: {$queue}");

            // Use Laravel's built-in queue:work command with specific parameters
            $exitCode = $this->call('queue:work', [
                'connection' => 'database',
                '--queue' => $queue,
                '--timeout' => $timeout,
                '--sleep' => $sleep,
                '--tries' => $tries,
                '--stop-when-empty' => true, // Process all current jobs then stop
                '--verbose' => true
            ]);

            if ($exitCode !== 0) {
                $this->error("Queue {$queue} processing failed with exit code: {$exitCode}");
            } else {
                $this->info("Queue {$queue} processing completed successfully");
            }
        }

        $this->info('All promotion queues processed.');
        return 0;
    }
}
