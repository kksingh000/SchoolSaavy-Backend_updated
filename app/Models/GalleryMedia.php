<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GalleryMedia extends Model
{
    use HasFactory;

    protected $table = 'gallery_media';

    protected $fillable = [
        'album_id',
        'uploaded_by',
        'type',
        'title',
        'description',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'thumbnail_path',
        'metadata',
        'views_count',
        'downloads_count',
        'sort_order',
        'is_featured',
        'status',
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
        'views_count' => 'integer',
        'downloads_count' => 'integer',
        'sort_order' => 'integer',
        'is_featured' => 'boolean',
    ];

    // Relationships
    public function album()
    {
        return $this->belongsTo(GalleryAlbum::class, 'album_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePhotos($query)
    {
        return $query->where('type', 'photo');
    }

    public function scopeVideos($query)
    {
        return $query->where('type', 'video');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at', 'desc');
    }

    // Accessors
    public function getFileUrlAttribute()
    {
        return Storage::url($this->file_path);
    }

    public function getThumbnailUrlAttribute()
    {
        // If this is a photo/image, try to get the generated thumbnail
        if ($this->type === 'photo' && $this->file_path) {
            $thumbnailUrls = $this->getGeneratedThumbnailUrls();

            // Return small thumbnail if available, otherwise medium, then large
            if (!empty($thumbnailUrls['small'])) {
                return $thumbnailUrls['small'];
            } elseif (!empty($thumbnailUrls['medium'])) {
                return $thumbnailUrls['medium'];
            } elseif (!empty($thumbnailUrls['large'])) {
                return $thumbnailUrls['large'];
            }
        }

        // Fallback to the old thumbnail_path if it exists
        if ($this->thumbnail_path) {
            return Storage::url($this->thumbnail_path);
        }

        // For photos without thumbnails, return the original image
        if ($this->type === 'photo') {
            return $this->file_url;
        }

        // For videos without thumbnail, return a default
        return asset('images/video-placeholder.png');
    }

    /**
     * Get all generated thumbnail URLs
     */
    public function getThumbnailUrlsAttribute()
    {
        if ($this->type === 'photo' && $this->file_path) {
            return $this->getGeneratedThumbnailUrls();
        }

        return [];
    }

    /**
     * Get generated thumbnail URLs for this media
     */
    private function getGeneratedThumbnailUrls(): array
    {
        if (!$this->file_path) {
            return [];
        }

        $thumbnailUrls = [];
        $pathInfo = pathinfo($this->file_path);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];

        // Define thumbnail sizes
        $sizes = ['small' => 150, 'medium' => 300, 'large' => 600];

        foreach ($sizes as $sizeName => $dimension) {
            $thumbnailPath = $directory . '/thumbnails/' . $sizeName . '/' . $filename . '.jpg';
            $thumbnailUrls[$sizeName] = config('upload.media_url') . $thumbnailPath;
        }

        return $thumbnailUrls;
    }

    public function getFileSizeFormattedAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getDimensionsAttribute()
    {
        if ($this->metadata && isset($this->metadata['dimensions'])) {
            return $this->metadata['dimensions'];
        }
        return null;
    }

    public function getDurationAttribute()
    {
        if ($this->type === 'video' && $this->metadata && isset($this->metadata['duration'])) {
            return $this->metadata['duration'];
        }
        return null;
    }

    public function getDurationFormattedAttribute()
    {
        if ($duration = $this->duration) {
            $minutes = floor($duration / 60);
            $seconds = $duration % 60;
            return sprintf('%d:%02d', $minutes, $seconds);
        }
        return null;
    }

    // Helper Methods
    public function incrementViews()
    {
        $this->increment('views_count');
    }

    public function incrementDownloads()
    {
        $this->increment('downloads_count');
    }

    public function updateSortOrder($order)
    {
        $this->update(['sort_order' => $order]);
    }

    public function toggleFeatured()
    {
        $this->update(['is_featured' => !$this->is_featured]);
    }

    public function deleteWithFile()
    {
        // Delete the actual file
        if (Storage::exists($this->file_path)) {
            Storage::delete($this->file_path);
        }

        // Delete thumbnail if exists
        if ($this->thumbnail_path && Storage::exists($this->thumbnail_path)) {
            Storage::delete($this->thumbnail_path);
        }

        // Delete the database record
        return $this->delete();
    }

    public function canBeDownloadedBy($user)
    {
        // Check if album allows downloads
        if (!$this->album->is_public && !$this->album->canBeViewedBy($user)) {
            return false;
        }

        return true;
    }

    /**
     * Generate thumbnail for video
     */
    public function generateVideoThumbnail()
    {
        // This would use FFmpeg or similar to generate video thumbnail
        // Implementation depends on server setup
        // For now, return false
        return false;
    }

    /**
     * Extract metadata from file
     */
    public function extractMetadata()
    {
        $metadata = [];

        if ($this->type === 'photo') {
            // Extract image dimensions
            try {
                $path = Storage::path($this->file_path);
                if (file_exists($path)) {
                    list($width, $height) = getimagesize($path);
                    $metadata['dimensions'] = [
                        'width' => $width,
                        'height' => $height,
                    ];
                }
            } catch (\Exception $e) {
                // Log error
            }
        } elseif ($this->type === 'video') {
            // Extract video metadata using FFmpeg or similar
            // This requires additional setup
        }

        $this->update(['metadata' => $metadata]);
        return $metadata;
    }
}
