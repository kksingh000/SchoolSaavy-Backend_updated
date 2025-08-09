<?php

namespace App\Services;

use App\Models\GalleryAlbum;
use App\Models\GalleryMedia;
use App\Models\Event;
use App\Models\ClassRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

class GalleryService
{
    /**
     * Get paginated gallery albums with filters
     */
    public function getAlbums(Request $request, int $schoolId, int $perPage = 15)
    {
        $query = GalleryAlbum::with([
            'class',
            'event',
            'creator',
            'media' => function ($query) {
                $query->where('type', 'photo')
                    ->where('status', 'active')
                    ->orderBy('sort_order')
                    ->limit(3);
            }
        ])
            ->withCount([
                'media as total_media_count' => function ($query) {
                    $query->where('status', 'active');
                },
                'media as photos_count' => function ($query) {
                    $query->where('type', 'photo')->where('status', 'active');
                }
            ])
            ->where('school_id', $schoolId)
            ->orderBy('event_date', 'desc');

        // Apply filters
        $this->applyFilters($query, $request);

        return $query->paginate($perPage);
    }

    /**
     * Get paginated media for an album
     */
    public function getAlbumMedia(int $albumId, int $schoolId, int $perPage = 20)
    {
        $album = $this->getAlbumByIdAndSchool($albumId, $schoolId);

        return $album->media()
            ->active()
            ->ordered()
            ->paginate($perPage);
    }

    /**
     * Create a new gallery album with media files
     */
    public function createAlbum(array $data, array $mediaFiles, int $schoolId, int $userId)
    {
        return DB::transaction(function () use ($data, $mediaFiles, $schoolId, $userId) {
            // Create the album
            $album = GalleryAlbum::create([
                'school_id' => $schoolId,
                'class_id' => $data['class_id'],
                'event_id' => $data['event_id'],
                'created_by' => $userId,
                'title' => $data['title'],
                'description' => $data['description'],
                'event_date' => $data['event_date'],
                'is_public' => $data['is_public'] ?? true,
                'status' => 'active',
            ]);

            // Upload and process media files
            $uploadedMedia = $this->processMediaFiles($mediaFiles, $album);

            // Update album media count and set cover image
            $album->update([
                'media_count' => count($uploadedMedia),
                'cover_image' => $uploadedMedia[0]['file_path'] ?? null,
            ]);

            return [
                'album' => $album->load(['class', 'event', 'creator']),
                'media' => $uploadedMedia,
            ];
        });
    }

    /**
     * Update an existing gallery album
     */
    public function updateAlbum(int $albumId, array $data, int $schoolId)
    {
        $album = $this->getAlbumByIdAndSchool($albumId, $schoolId);

        $album->update(array_filter([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'event_date' => $data['event_date'] ?? null,
            'is_public' => $data['is_public'] ?? null,
            'status' => $data['status'] ?? null,
        ]));

        return $album->load(['class', 'event', 'creator']);
    }

    /**
     * Delete a gallery album and all its media
     */
    public function deleteAlbum(int $albumId, int $schoolId)
    {
        $album = $this->getAlbumByIdAndSchool($albumId, $schoolId);

        return DB::transaction(function () use ($album) {
            // Delete all media files
            foreach ($album->media as $media) {
                $this->deleteMediaFile($media);
            }

            // Delete the album
            $album->delete();

            return true;
        });
    }

    /**
     * Add media files to existing album
     */
    public function addMediaToAlbum(int $albumId, array $mediaFiles, int $schoolId)
    {
        $album = $this->getAlbumByIdAndSchool($albumId, $schoolId);

        return DB::transaction(function () use ($album, $mediaFiles) {
            $nextSortOrder = $album->media()->max('sort_order') + 1;
            $uploadedMedia = $this->processMediaFiles($mediaFiles, $album, $nextSortOrder);

            // Update album media count
            $album->increment('media_count', count($uploadedMedia));

            return $uploadedMedia;
        });
    }

    /**
     * Delete specific media from album
     */
    public function deleteMediaFromAlbum(int $albumId, int $mediaId, int $schoolId)
    {
        $album = $this->getAlbumByIdAndSchool($albumId, $schoolId);
        $media = $album->media()->findOrFail($mediaId);

        return DB::transaction(function () use ($album, $media) {
            $this->deleteMediaFile($media);
            $album->decrement('media_count');

            // Update cover image if this was the cover
            if ($album->cover_image === $media->file_path) {
                $newCover = $album->media()->first();
                $album->update(['cover_image' => $newCover->file_path ?? null]);
            }

            return true;
        });
    }

    /**
     * Get classes that have gallery albums, with optional pagination and search
     */
    public function getClasses(Request $request, int $schoolId)
    {
        return $this->getClassesPaginated($request, $schoolId);
    }

    /**
     * Get events that have gallery albums, with optional pagination and search
     */
    public function getEvents(Request $request, int $schoolId)
    {
        return $this->getEventsPaginated($request, $schoolId);
    }

    /**
     * Get paginated classes that have gallery albums, with search
     */
    private function getClassesPaginated(Request $request, int $schoolId, int $perPage = 20)
    {
        $perPage = min($request->get('per_page', $perPage), 100);

        $query = ClassRoom::where('school_id', $schoolId)
            ->where('is_active', true)
            ->whereHas('galleryAlbums', function ($q) {
                $q->where('status', 'active');
            })
            ->withCount([
                'galleryAlbums as albums_count' => function ($q) {
                    $q->where('status', 'active');
                }
            ]);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('grade_level', 'like', "%{$search}%")
                    ->orWhere('section', 'like', "%{$search}%");
            });
        }

        // Filter by grade level
        if ($request->filled('grade_level')) {
            $query->where('grade_level', $request->grade_level);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'grade_level');
        $sortOrder = $request->get('sort_order', 'asc');

        $allowedSorts = ['grade_level', 'section', 'name', 'created_at', 'albums_count'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('grade_level', 'asc')->orderBy('section', 'asc');
        }

        return $query->paginate($perPage, ['id', 'name', 'grade_level', 'section', 'is_active', 'created_at']);
    }

    /**
     * Get paginated events that have gallery albums, with search
     */
    private function getEventsPaginated(Request $request, int $schoolId, int $perPage = 20)
    {
        $perPage = min($request->get('per_page', $perPage), 100);

        $query = Event::where('school_id', $schoolId)
            ->whereHas('galleryAlbums', function ($q) {
                $q->where('status', 'active');
            })
            ->withCount([
                'galleryAlbums as albums_count' => function ($q) {
                    $q->where('status', 'active');
                }
            ]);

        // Filter by published status
        if ($request->filled('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        } else {
            // Default to published events only
            $query->where('is_published', true);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        // Filter by event type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('event_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('event_date', '<=', $request->date_to);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'event_date');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSorts = ['event_date', 'title', 'type', 'created_at', 'albums_count'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('event_date', 'desc');
        }

        return $query->paginate($perPage, ['id', 'title', 'description', 'event_date', 'type', 'is_published', 'created_at']);
    }

    /**
     * Get album by ID and school (with authorization check)
     */
    public function getAlbumByIdAndSchool(int $albumId, int $schoolId)
    {
        return GalleryAlbum::where('school_id', $schoolId)->findOrFail($albumId);
    }

    /**
     * Get album with media for display
     */
    public function getAlbumWithMedia(int $albumId, int $schoolId, int $mediaPerPage = 20)
    {
        $album = GalleryAlbum::with(['class', 'event', 'creator'])
            ->where('school_id', $schoolId)
            ->findOrFail($albumId);

        $media = $album->media()
            ->active()
            ->ordered()
            ->paginate($mediaPerPage);

        return [
            'album' => $album,
            'media' => $media
        ];
    }

    /**
     * Apply filters to album query
     */
    private function applyFilters($query, Request $request)
    {
        // Filter by class
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Filter by event
        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('event_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('event_date', '<=', $request->date_to);
        }

        // Filter by public/private
        if ($request->filled('is_public')) {
            $query->where('is_public', $request->boolean('is_public'));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Process and upload multiple media files
     */
    private function processMediaFiles(array $mediaFiles, GalleryAlbum $album, int $startSortOrder = 1)
    {
        $uploadedMedia = [];
        $sortOrder = $startSortOrder;

        foreach ($mediaFiles as $file) {
            $mediaData = $this->uploadMediaFile($file, $album, $sortOrder);
            if ($mediaData) {
                $uploadedMedia[] = $mediaData;
                $sortOrder++;
            }
        }

        return $uploadedMedia;
    }

    /**
     * Upload and process a single media file
     */
    private function uploadMediaFile($file, GalleryAlbum $album, int $sortOrder)
    {
        try {
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();

            // Determine media type
            $type = str_starts_with($mimeType, 'image/') ? 'photo' : 'video';

            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;

            // Create directory structure
            $directory = "gallery/{$album->school_id}/{$album->id}";

            // Store the file
            $filePath = $file->storeAs($directory, $filename, 'public');

            // Create thumbnail for images
            $thumbnailPath = null;
            if ($type === 'photo') {
                $thumbnailPath = $this->createImageThumbnail($filePath);
            }

            // Create media record with auto-generated title
            $autoTitle = $type === 'photo' ? "Photo {$sortOrder}" : "Video {$sortOrder}";

            $media = GalleryMedia::create([
                'album_id' => $album->id,
                'uploaded_by' => Auth::id(),
                'type' => $type,
                'title' => $autoTitle,
                'description' => null, // No individual descriptions needed
                'file_path' => $filePath,
                'file_name' => $originalName,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'thumbnail_path' => $thumbnailPath,
                'sort_order' => $sortOrder,
                'status' => 'active',
            ]);

            // Extract metadata
            $media->extractMetadata();

            return $media;
        } catch (\Exception $e) {
            Log::error('Failed to upload media file: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete media file and its record
     */
    private function deleteMediaFile(GalleryMedia $media)
    {
        // Delete physical files
        if ($media->file_path && Storage::disk('public')->exists($media->file_path)) {
            Storage::disk('public')->delete($media->file_path);
        }

        if ($media->thumbnail_path && Storage::disk('public')->exists($media->thumbnail_path)) {
            Storage::disk('public')->delete($media->thumbnail_path);
        }

        // Delete the record
        $media->delete();
    }

    /**
     * Create thumbnail for image
     */
    private function createImageThumbnail($filePath)
    {
        try {
            $fullPath = Storage::disk('public')->path($filePath);
            $thumbnailPath = str_replace('.', '_thumb.', $filePath);
            $thumbnailFullPath = Storage::disk('public')->path($thumbnailPath);

            // Create thumbnail directory if it doesn't exist
            $thumbnailDir = dirname($thumbnailFullPath);
            if (!file_exists($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            // Create thumbnail using GD library
            $imageInfo = getimagesize($fullPath);
            if ($imageInfo === false) {
                return null;
            }

            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $type = $imageInfo[2];

            // Calculate new dimensions
            $thumbWidth = 300;
            $thumbHeight = 300;

            if ($width > $height) {
                $thumbHeight = ($height / $width) * $thumbWidth;
            } else {
                $thumbWidth = ($width / $height) * $thumbHeight;
            }

            // Create image resource based on type
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $source = imagecreatefromjpeg($fullPath);
                    break;
                case IMAGETYPE_PNG:
                    $source = imagecreatefrompng($fullPath);
                    break;
                case IMAGETYPE_GIF:
                    $source = imagecreatefromgif($fullPath);
                    break;
                default:
                    return null;
            }

            if (!$source) {
                return null;
            }

            // Create thumbnail
            $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);

            // Preserve transparency for PNG and GIF
            if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
                imagefilledrectangle($thumb, 0, 0, $thumbWidth, $thumbHeight, $transparent);
            }

            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

            // Save thumbnail
            switch ($type) {
                case IMAGETYPE_JPEG:
                    imagejpeg($thumb, $thumbnailFullPath, 80);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($thumb, $thumbnailFullPath);
                    break;
                case IMAGETYPE_GIF:
                    imagegif($thumb, $thumbnailFullPath);
                    break;
            }

            // Clean up
            imagedestroy($source);
            imagedestroy($thumb);

            return $thumbnailPath;
        } catch (\Exception $e) {
            Log::error('Failed to create thumbnail: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Clear cache for school-specific data
     */
    public function clearSchoolCache(int $schoolId)
    {
        Cache::forget("school_{$schoolId}_active_classes");
        Cache::forget("school_{$schoolId}_published_events");
    }

    /**
     * Get gallery statistics for dashboard
     */
    public function getGalleryStats(int $schoolId)
    {
        $stats = Cache::remember("school_{$schoolId}_gallery_stats", 1800, function () use ($schoolId) { // 30 minutes
            return [
                'total_albums' => GalleryAlbum::where('school_id', $schoolId)->count(),
                'total_photos' => GalleryMedia::whereHas('album', function ($query) use ($schoolId) {
                    $query->where('school_id', $schoolId);
                })->where('type', 'photo')->count(),
                'total_videos' => GalleryMedia::whereHas('album', function ($query) use ($schoolId) {
                    $query->where('school_id', $schoolId);
                })->where('type', 'video')->count(),
                'recent_albums' => GalleryAlbum::where('school_id', $schoolId)
                    ->with(['class', 'event'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'title', 'media_count', 'cover_image', 'created_at', 'class_id', 'event_id']),
                'storage_used' => GalleryMedia::whereHas('album', function ($query) use ($schoolId) {
                    $query->where('school_id', $schoolId);
                })->sum('file_size'),
            ];
        });

        return $stats;
    }

    /**
     * Search albums with advanced filters
     */
    public function searchAlbums(Request $request, int $schoolId, int $perPage = 15)
    {
        $query = GalleryAlbum::with([
            'class',
            'event',
            'creator',
            'media' => function ($query) {
                $query->where('type', 'photo')
                    ->where('status', 'active')
                    ->orderBy('sort_order')
                    ->limit(3);
            }
        ])
            ->withCount([
                'media as total_media_count' => function ($query) {
                    $query->where('status', 'active');
                },
                'media as photos_count' => function ($query) {
                    $query->where('type', 'photo')->where('status', 'active');
                }
            ])
            ->where('school_id', $schoolId);

        // Apply all filters
        $this->applyFilters($query, $request);

        // Advanced sorting options
        $sortBy = $request->get('sort_by', 'event_date');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSorts = ['event_date', 'created_at', 'title', 'media_count'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('event_date', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Format album data with photo URLs for frontend
     */
    public function formatAlbumsWithPhotos($albums)
    {
        if ($albums instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $albums->getCollection()->transform(function ($album) {
                return $this->formatAlbumWithPhotos($album);
            });
        } else {
            $albums->transform(function ($album) {
                return $this->formatAlbumWithPhotos($album);
            });
        }

        return $albums;
    }

    /**
     * Format single album with photo URLs
     */
    private function formatAlbumWithPhotos($album)
    {
        // Add thumbnail photos array with full URLs
        $album->thumbnail_photos = $album->media->map(function ($media) {
            // Check if file_path is already a complete URL
            $isExternalUrl = filter_var($media->file_path, FILTER_VALIDATE_URL);

            // For complete URLs, use as-is. For local paths, add asset() wrapper
            $url = $isExternalUrl ? $media->file_path : asset('storage/' . $media->file_path);

            // Same logic for thumbnail
            $thumbnailUrl = null;
            if ($media->thumbnail_path) {
                $isThumbnailExternal = filter_var($media->thumbnail_path, FILTER_VALIDATE_URL);
                $thumbnailUrl = $isThumbnailExternal ? $media->thumbnail_path : asset('storage/' . $media->thumbnail_path);
            } else {
                $thumbnailUrl = $url; // Use main URL as thumbnail if no separate thumbnail
            }

            return [
                'id' => $media->id,
                'url' => $url,
                'thumbnail_url' => $thumbnailUrl,
                'title' => $media->title,
            ];
        });

        // Remove the media relation to avoid confusion
        unset($album->media);

        return $album;
    }
}
