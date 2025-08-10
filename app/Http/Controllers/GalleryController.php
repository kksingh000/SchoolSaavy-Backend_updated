<?php

namespace App\Http\Controllers;

use App\Services\GalleryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreGalleryAlbumRequest;
use App\Http\Requests\UpdateGalleryAlbumRequest;
use App\Http\Requests\AddGalleryMediaRequest;

class GalleryController extends Controller
{
    protected $galleryService;

    public function __construct(GalleryService $galleryService)
    {
        $this->galleryService = $galleryService;
    }

    /**
     * Display a listing of gallery albums
     */
    public function index(Request $request)
    {
        try {
            $perPage = min($request->get('per_page', 15), 50); // Max 50 items per page
            $schoolId = $request->school_id;

            // Use search method for better performance and flexibility
            $albums = $this->galleryService->searchAlbums($request, $schoolId, $perPage);

            // Format albums with photo URLs
            $albums = $this->galleryService->formatAlbumsWithPhotos($albums);

            return response()->json([
                'success' => true,
                'data' => $albums,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch albums',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Store a newly created gallery album
     */
    public function store(StoreGalleryAlbumRequest $request)
    {
        try {
            $schoolId = $request->school_id;
            $userId = Auth::id();

            $data = $request->validated();
            $mediaFiles = $request->file('media_files');

            $result = $this->galleryService->createAlbum($data, $mediaFiles, $schoolId, $userId);

            return response()->json([
                'success' => true,
                'message' => 'Gallery album created successfully',
                'data' => $result,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create gallery album',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified gallery album
     */
    public function show(Request $request, $id)
    {
        try {
            $schoolId = $request->school_id;
            $mediaPerPage = min($request->get('media_per_page', 20), 100); // Max 100 media per page

            $result = $this->galleryService->getAlbumWithMedia($id, $schoolId, $mediaPerPage);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Album not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified gallery album
     */
    public function update(UpdateGalleryAlbumRequest $request, $id)
    {
        try {
            $schoolId = Auth::user()->school_id;
            $data = $request->validated();

            $album = $this->galleryService->updateAlbum($id, $data, $schoolId);

            return response()->json([
                'success' => true,
                'message' => 'Gallery album updated successfully',
                'data' => $album,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update gallery album',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified gallery album
     */
    public function destroy($id)
    {
        try {
            $schoolId = Auth::user()->school_id;

            $this->galleryService->deleteAlbum($id, $schoolId);

            return response()->json([
                'success' => true,
                'message' => 'Gallery album deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete gallery album',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add media to existing album
     */
    public function addMedia(AddGalleryMediaRequest $request, $albumId)
    {
        try {
            $schoolId = Auth::user()->school_id;
            $mediaFiles = $request->file('media_files');

            $uploadedMedia = $this->galleryService->addMediaToAlbum($albumId, $mediaFiles, $schoolId);

            return response()->json([
                'success' => true,
                'message' => 'Media added successfully',
                'data' => $uploadedMedia,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add media',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete specific media from album
     */
    public function deleteMedia($albumId, $mediaId)
    {
        try {
            $schoolId = Auth::user()->school_id;

            $this->galleryService->deleteMediaFromAlbum($albumId, $mediaId, $schoolId);

            return response()->json([
                'success' => true,
                'message' => 'Media deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete media',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get classes (simple list or paginated based on query params)
     */
    public function getClasses(Request $request)
    {
        try {
            $schoolId = $request->school_id;
            $classes = $this->galleryService->getClasses($request, $schoolId);

            return response()->json([
                'success' => true,
                'data' => $classes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch classes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get events (simple list or paginated based on query params)
     */
    public function getEvents(Request $request)
    {
        try {
            $events = $this->galleryService->getEvents($request, $request->school_id);

            return response()->json([
                'success' => true,
                'data' => $events,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch events',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get paginated media for a specific album
     */
    public function getAlbumMedia(Request $request, $albumId)
    {
        try {
            $schoolId = Auth::user()->school_id;
            $perPage = min($request->get('per_page', 20), 100); // Max 100 media per page

            $media = $this->galleryService->getAlbumMedia($albumId, $schoolId, $perPage);

            return response()->json([
                'success' => true,
                'data' => $media,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch album media',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get gallery statistics for dashboard
     */
    public function getStats()
    {
        try {
            $schoolId = Auth::user()->school_id;
            $stats = $this->galleryService->getGalleryStats($schoolId);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch gallery statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
