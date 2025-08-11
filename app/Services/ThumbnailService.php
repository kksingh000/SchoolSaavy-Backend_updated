<?php

namespace App\Services;

use App\Jobs\GenerateThumbnail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ThumbnailService
{
    /**
     * Default thumbnail sizes
     */
    private const DEFAULT_THUMBNAIL_SIZES = [
        'small' => 150,
        'medium' => 300,
        'large' => 600
    ];

    /**
     * Image extensions that support thumbnail generation
     */
    private const SUPPORTED_IMAGE_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'bmp'
    ];

    /**
     * Check if file is an image that supports thumbnail generation
     */
    public function isImageFile(string $extension): bool
    {
        return in_array(strtolower($extension), self::SUPPORTED_IMAGE_EXTENSIONS);
    }

    /**
     * Queue thumbnail generation for an uploaded image
     */
    public function queueThumbnailGeneration(
        string $filePath,
        string $filename,
        ?array $thumbnailSizes = null,
        string $uploadDisk = 's3'
    ): bool {
        try {
            // Use default sizes if none provided
            $sizes = $thumbnailSizes ?? self::DEFAULT_THUMBNAIL_SIZES;

            // Dispatch the thumbnail generation job
            GenerateThumbnail::dispatch($filePath, $filename, $sizes, $uploadDisk);

            Log::info('Thumbnail generation job queued', [
                'file_path' => $filePath,
                'filename' => $filename,
                'sizes' => array_keys($sizes)
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to queue thumbnail generation', [
                'file_path' => $filePath,
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get thumbnail URLs for a given image
     */
    public function getThumbnailUrls(string $originalPath, string $uploadDisk = 's3'): array
    {
        $thumbnailUrls = [];
        $pathInfo = pathinfo($originalPath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];

        foreach (self::DEFAULT_THUMBNAIL_SIZES as $sizeName => $dimension) {
            $thumbnailPath = $directory . '/thumbnails/' . $sizeName . '/' . $filename . '.jpg';

            if (Storage::disk($uploadDisk)->exists($thumbnailPath)) {
                if ($uploadDisk === 's3') {
                    $thumbnailUrls[$sizeName] = $this->generateS3Url($thumbnailPath);
                } else {
                    $thumbnailUrls[$sizeName] = asset('storage/' . $thumbnailPath);
                }
            }
        }

        return $thumbnailUrls;
    }

    /**
     * Check if thumbnails exist for a given image
     */
    public function thumbnailsExist(string $originalPath, string $uploadDisk = 's3'): array
    {
        $thumbnailStatus = [];
        $pathInfo = pathinfo($originalPath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];

        foreach (self::DEFAULT_THUMBNAIL_SIZES as $sizeName => $dimension) {
            $thumbnailPath = $directory . '/thumbnails/' . $sizeName . '/' . $filename . '.jpg';
            $thumbnailStatus[$sizeName] = Storage::disk($uploadDisk)->exists($thumbnailPath);
        }

        return $thumbnailStatus;
    }

    /**
     * Delete thumbnails for a given image
     */
    public function deleteThumbnails(string $originalPath, string $uploadDisk = 's3'): bool
    {
        try {
            $pathInfo = pathinfo($originalPath);
            $directory = $pathInfo['dirname'];
            $filename = $pathInfo['filename'];

            $deletedCount = 0;
            $totalSizes = count(self::DEFAULT_THUMBNAIL_SIZES);

            foreach (self::DEFAULT_THUMBNAIL_SIZES as $sizeName => $dimension) {
                $thumbnailPath = $directory . '/thumbnails/' . $sizeName . '/' . $filename . '.jpg';

                if (Storage::disk($uploadDisk)->exists($thumbnailPath)) {
                    Storage::disk($uploadDisk)->delete($thumbnailPath);
                    $deletedCount++;
                }
            }

            Log::info('Thumbnails deleted', [
                'original_path' => $originalPath,
                'deleted_count' => $deletedCount,
                'total_sizes' => $totalSizes
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete thumbnails', [
                'original_path' => $originalPath,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Generate S3 URL for a given path
     */
    private function generateS3Url(string $path): string
    {
        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');
        return "https://{$bucket}.s3.{$region}.amazonaws.com/{$path}";
    }

    /**
     * Get default thumbnail sizes
     */
    public function getDefaultThumbnailSizes(): array
    {
        return self::DEFAULT_THUMBNAIL_SIZES;
    }

    /**
     * Get supported image extensions
     */
    public function getSupportedImageExtensions(): array
    {
        return self::SUPPORTED_IMAGE_EXTENSIONS;
    }
}
