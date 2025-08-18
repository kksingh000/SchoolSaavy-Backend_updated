<?php

namespace App\Http\Controllers;

use App\Services\GalleryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreGalleryAlbumRequest;
use App\Http\Requests\UpdateGalleryAlbumRequest;
use App\Http\Requests\AddGalleryMediaRequest;

/**
 * @see file:copilot-instructions.md
 * 
 * GalleryController - Handles gallery album management
 * Follows SchoolSavvy architecture patterns with proper validation and responses
 */
class GalleryController extends BaseController
{
    protected $galleryService;

    public function __construct(GalleryService $galleryService)
    {
        $this->galleryService = $galleryService;
    }

    /**
     * Display a listing of gallery albums with filters
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Check module access
        if (!$this->checkModuleAccess('gallery-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            // Validate request parameters
            $request->validate([
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1',
                'search' => 'nullable|string|max:255',
                'class_id' => 'nullable|exists:classes,id',
                'event_id' => 'nullable|exists:events,id',
                'event_type' => 'nullable|in:holiday,announcement,sports,cultural,academic,exam,meeting',
                'status' => 'nullable|in:active,inactive,archived',
                'is_public' => 'nullable|boolean',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'sort_by' => 'nullable|in:title,event_date,created_at,updated_at',
                'sort_order' => 'nullable|in:asc,desc'
            ]);

            $perPage = min($request->get('per_page', 15), 100);

            // Get filtered albums
            $albums = $this->galleryService->getAlbums($request, request()->school_id, $perPage);

            return $this->successResponse(
                $albums,
                'Gallery albums retrieved successfully'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to fetch gallery albums',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Get filter options for gallery (classes, events, etc.)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFilterOptions()
    {
        // Check module access
        if (!$this->checkModuleAccess('gallery-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $options = $this->galleryService->getFilterOptions(request()->school_id);

            return $this->successResponse(
                $options,
                'Gallery filter options retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to fetch filter options',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
    /**
     * Store a newly created gallery album
     */
    public function store(StoreGalleryAlbumRequest $request)
    {
        // Check module access
        if (!$this->checkModuleAccess('gallery-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $data = $request->validated();
            $mediaFiles = $data['media_files'] ?? [];

            $result = $this->galleryService->createAlbum(
                $data,
                $mediaFiles,
                request()->school_id,
                Auth::id()
            );

            return $this->successResponse(
                $result,
                'Gallery album created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create gallery album',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Display the specified gallery album
     */
    public function show(Request $request, $id)
    {
        // Check module access
        if (!$this->checkModuleAccess('gallery-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'media_per_page' => 'nullable|integer|min:1|max:100'
            ]);

            $mediaPerPage = min($request->get('media_per_page', 20), 100);
            $result = $this->galleryService->getAlbumWithMedia($id, request()->school_id, $mediaPerPage);

            return $this->successResponse(
                $result,
                'Gallery album retrieved successfully'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Album not found',
                ['error' => $e->getMessage()],
                404
            );
        }
    }

    /**
     * Update the specified gallery album
     */
    public function update(UpdateGalleryAlbumRequest $request, $id)
    {
        try {
            $schoolId = $request->school_id;
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
            $schoolId = request()->school_id;

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
            $schoolId = $request->school_id;
            $data = $request->validated();
            // Get media files metadata from the validated data instead of file uploads
            $mediaFiles = $data['media_files'] ?? [];
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
            $schoolId = request()->school_id;

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
            $schoolId = $request->school_id;
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
            $schoolId = request()->school_id;
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
