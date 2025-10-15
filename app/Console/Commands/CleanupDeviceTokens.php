<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use App\Models\UserDeviceToken;

class CleanupDeviceTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:cleanup-tokens {--days=60 : Days since last use to consider a token stale}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up stale and duplicate device tokens';

    /**
     * The notification service instance.
     */
    protected $notificationService;

    /**
     * Create a new command instance.
     *
     * @param NotificationService $notificationService
     * @return void
     */
    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting device token cleanup...');
        
        // Get total tokens before cleanup
        $tokenCountBefore = UserDeviceToken::count();
        $activeTokensBefore = UserDeviceToken::where('is_active', true)->count();
        
        $this->info("Found {$tokenCountBefore} total tokens ({$activeTokensBefore} active)");
        
        // Get days from command option
        $days = (int)$this->option('days');
        if ($days < 1) {
            $days = 60; // Default to 60 days
        }
        
        $this->info("Using {$days} days as threshold for stale tokens");
        
        // Run cleanup
        $stats = $this->notificationService->cleanupDeviceTokens($days);
        
        // Show results
        $this->info('Cleanup completed:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Stale tokens deactivated', $stats['stale_tokens_deactivated']],
                ['Duplicate tokens removed', $stats['duplicate_tokens_removed']],
                ['Empty tokens removed', $stats['empty_tokens_removed']]
            ]
        );
        
        // Get totals after cleanup
        $tokenCountAfter = UserDeviceToken::count();
        $activeTokensAfter = UserDeviceToken::where('is_active', true)->count();
        
        $this->info("Now have {$tokenCountAfter} total tokens ({$activeTokensAfter} active)");
        
        return Command::SUCCESS;
    }
}
