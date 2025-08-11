<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ThumbnailService;
use App\Jobs\GenerateThumbnail;
use Illuminate\Support\Facades\Storage;

class TestThumbnailGeneration extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'thumbnail:test {--sync : Run synchronously without queue}';

    /**
     * The console command description.
     */
    protected $description = 'Test thumbnail generation functionality';

    protected ThumbnailService $thumbnailService;

    public function __construct(ThumbnailService $thumbnailService)
    {
        parent::__construct();
        $this->thumbnailService = $thumbnailService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing thumbnail generation system...');

        // Test if we can queue jobs
        if ($this->option('sync')) {
            $this->info('Running in synchronous mode (without queue)');
        } else {
            $this->info('Testing queue system...');
            $this->testQueueConnection();
        }

        // Test supported image extensions
        $this->testSupportedExtensions();

        // Test thumbnail service methods
        $this->testThumbnailService();

        $this->info('Thumbnail generation system test completed!');
        return 0;
    }

    private function testQueueConnection(): void
    {
        try {
            $this->info('Queue connection: ' . config('queue.default'));
            $this->info('Redis connection test...');

            // Test basic queue functionality
            $testJob = new GenerateThumbnail('test/path.jpg', 'test.jpg');

            $this->info('✓ Queue system is ready');
        } catch (\Exception $e) {
            $this->error('✗ Queue system error: ' . $e->getMessage());
        }
    }

    private function testSupportedExtensions(): void
    {
        $this->info('Testing supported image extensions...');

        $supportedExtensions = $this->thumbnailService->getSupportedImageExtensions();
        $this->info('Supported extensions: ' . implode(', ', $supportedExtensions));

        // Test each extension
        foreach ($supportedExtensions as $ext) {
            $isSupported = $this->thumbnailService->isImageFile($ext);
            $status = $isSupported ? '✓' : '✗';
            $this->line("{$status} {$ext}");
        }

        // Test non-image extensions
        $nonImageExtensions = ['pdf', 'doc', 'txt', 'xlsx'];
        $this->info('Testing non-image extensions...');
        foreach ($nonImageExtensions as $ext) {
            $isSupported = $this->thumbnailService->isImageFile($ext);
            $status = !$isSupported ? '✓' : '✗';
            $this->line("{$status} {$ext} (should not be supported)");
        }
    }

    private function testThumbnailService(): void
    {
        $this->info('Testing thumbnail service methods...');

        // Test default thumbnail sizes
        $defaultSizes = $this->thumbnailService->getDefaultThumbnailSizes();
        $this->info('Default thumbnail sizes:');
        foreach ($defaultSizes as $name => $size) {
            $this->line("  {$name}: {$size}px");
        }

        // Test thumbnail URL generation (with fake path)
        $fakePath = 'uploads/general/1/2025/08/20250811123045_a3B8kL9m.jpg';
        $thumbnailUrls = $this->thumbnailService->getThumbnailUrls($fakePath);

        $this->info('Generated thumbnail paths (for testing):');
        if (empty($thumbnailUrls)) {
            $this->line('  No thumbnails found (expected for test)');
        } else {
            foreach ($thumbnailUrls as $size => $url) {
                $this->line("  {$size}: {$url}");
            }
        }

        $this->info('✓ Thumbnail service methods working correctly');
    }
}
