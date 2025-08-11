<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class GenerateThumbnail implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    protected string $originalPath;
    protected string $originalFilename;
    protected array $thumbnailSizes;
    protected string $uploadDisk;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $originalPath,
        string $originalFilename,
        array $thumbnailSizes = ['small' => 150, 'medium' => 300, 'large' => 600],
        string $uploadDisk = 's3'
    ) {
        $this->originalPath = $originalPath;
        $this->originalFilename = $originalFilename;
        $this->thumbnailSizes = $thumbnailSizes;
        $this->uploadDisk = $uploadDisk;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting thumbnail generation', [
                'original_path' => $this->originalPath,
                'filename' => $this->originalFilename,
                'sizes' => $this->thumbnailSizes
            ]);

            // Check if original file exists
            if (!Storage::disk($this->uploadDisk)->exists($this->originalPath)) {
                Log::error('Original file not found for thumbnail generation', [
                    'path' => $this->originalPath
                ]);
                return;
            }

            // Get the original image content
            $originalContent = Storage::disk($this->uploadDisk)->get($this->originalPath);

            // Initialize Image Manager with GD driver
            $manager = new ImageManager(new Driver());

            // Load the image
            $image = $manager->read($originalContent);

            // Generate thumbnails for each size
            foreach ($this->thumbnailSizes as $sizeName => $maxDimension) {
                $this->generateThumbnail($image, $sizeName, $maxDimension);
            }

            Log::info('Thumbnail generation completed successfully', [
                'original_path' => $this->originalPath,
                'generated_sizes' => array_keys($this->thumbnailSizes)
            ]);
        } catch (\Exception $e) {
            Log::error('Thumbnail generation failed', [
                'original_path' => $this->originalPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark job as failed
            $this->fail($e);
        }
    }

    /**
     * Generate a single thumbnail
     */
    private function generateThumbnail($image, string $sizeName, int $maxDimension): void
    {
        try {
            // Clone the image to avoid modifying the original
            $thumbnail = clone $image;

            // Resize the image maintaining aspect ratio
            $thumbnail->scaleDown(width: $maxDimension, height: $maxDimension);

            // Generate thumbnail path
            $thumbnailPath = $this->generateThumbnailPath($sizeName);

            // Convert to string (JPEG format by default)
            $thumbnailContent = $thumbnail->toJpeg(quality: 85);

            // Upload thumbnail to S3
            Storage::disk($this->uploadDisk)->put($thumbnailPath, $thumbnailContent);

            Log::info('Thumbnail generated successfully', [
                'size' => $sizeName,
                'dimension' => $maxDimension,
                'path' => $thumbnailPath
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate thumbnail', [
                'size' => $sizeName,
                'dimension' => $maxDimension,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate thumbnail path based on original path and size
     */
    private function generateThumbnailPath(string $sizeName): string
    {
        $pathInfo = pathinfo($this->originalPath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];

        // Create thumbnail directory structure: original/path/thumbnails/size/filename.jpg
        return $directory . '/thumbnails/' . $sizeName . '/' . $filename . '.jpg';
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Thumbnail generation job failed permanently', [
            'original_path' => $this->originalPath,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
