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
}
