<?php

namespace App\Traits;

trait GeneratesFileUrls
{
    /**
     * Build file URL using the media_url config (same as GalleryService.buildFileUrl)
     */
    protected function buildFileUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // Already an absolute URL
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        // Append media_url
        $mediaUrl = rtrim(config('upload.media_url'), '/');
        return $mediaUrl . '/' . ltrim($path, '/');
    }

    /**
     * Build multiple file URLs
     */
    protected function buildFileUrls(array $filePaths): array
    {
        return array_map(function ($path) {
            return $this->buildFileUrl($path);
        }, $filePaths);
    }

    /**
     * Generate file URL with thumbnails for media objects
     */
    protected function generateFileUrl(?string $filePath): ?string
    {
        return $this->buildFileUrl($filePath);
    }

    /**
     * Generate thumbnail URLs for an image file path
     */
    protected function generateThumbnailUrls(?string $filePath): array
    {
        if (!$filePath || !str_contains(strtolower($filePath), '.')) {
            return [];
        }

        $pathInfo = pathinfo($filePath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];

        // Define thumbnail sizes
        $sizes = ['small' => 150, 'medium' => 300, 'large' => 600];
        $thumbnailUrls = [];

        foreach ($sizes as $sizeName => $dimension) {
            $thumbnailPath = $directory . '/thumbnails/' . $sizeName . '/' . $filename . '.jpg';
            $thumbnailUrls[$sizeName] = $this->buildFileUrl($thumbnailPath);
        }

        return $thumbnailUrls;
    }

    /**
     * Transform media object with URLs and thumbnails
     */
    protected function transformMediaWithUrls($media): array
    {
        if (!$media) {
            return [];
        }

        $fileUrl = $this->generateFileUrl($media->file_path);
        $thumbnails = [];

        // Generate thumbnails for images only
        if ($media->type === 'photo' || str_starts_with($media->mime_type ?? '', 'image/')) {
            $thumbnails = $this->generateThumbnailUrls($media->file_path);
        }

        return [
            'id' => $media->id,
            'file_path' => $media->file_path,
            'file_url' => $fileUrl,
            'file_name' => $media->file_name ?? null,
            'original_name' => $media->original_name ?? $media->file_name,
            'title' => $media->title ?? null,
            'description' => $media->description ?? null,
            'type' => $media->type ?? 'file',
            'mime_type' => $media->mime_type ?? null,
            'file_size' => $media->file_size ?? null,
            'sort_order' => $media->sort_order ?? 0,
            'thumbnails' => $thumbnails,
            'thumbnail_url' => $thumbnails['small'] ?? $fileUrl, // Fallback to original if no thumbnail
            'created_at' => $media->created_at ?? null,
            'updated_at' => $media->updated_at ?? null,
        ];
    }
}
