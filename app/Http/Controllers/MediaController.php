<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MediaController extends BaseController
{
    /**
     * Validate stream token and authorization
     * Called by media server before accepting a stream
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateStream(Request $request)
    {
        try {
            $streamKey = $request->input('stream_key');
            $user = $request->user();
            
            if (!$user) {
                return $this->errorResponse('Unauthorized', null, 401);
            }
            
            // Get user's school
            $school = $user->getSchool();
            
            if (!$school) {
                return $this->errorResponse('School not found', null, 404);
            }
            
            // Check if user has permission to stream
            // Only teachers and admins can start streams
            if (!in_array($user->user_type, ['admin', 'teacher'])) {
                return $this->errorResponse('Insufficient permissions', null, 403);
            }
            
            // Check if school has active live-streaming module
            if (!$this->checkModuleAccess('live-streaming')) {
                return $this->errorResponse('Live streaming module not active', null, 403);
            }
            
            // Get current active streams for school
            $activeStreamsKey = "school_{$school->id}_active_streams";
            $activeStreams = Cache::get($activeStreamsKey, []);
            
            // Check stream limit (from module settings)
            $maxStreams = $school->getModuleSetting('live-streaming', 'max_streams', 10);
            
            if (count($activeStreams) >= $maxStreams && !in_array($streamKey, $activeStreams)) {
                return $this->errorResponse('Maximum stream limit reached for school', null, 429);
            }
            
            // Add stream to active list
            if (!in_array($streamKey, $activeStreams)) {
                $activeStreams[] = $streamKey;
                Cache::put($activeStreamsKey, $activeStreams, 3600); // 1 hour TTL
            }
            
            // Store stream metadata
            $streamMetadata = [
                'stream_key' => $streamKey,
                'school_id' => $school->id,
                'user_id' => $user->id,
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'started_at' => now(),
            ];
            
            Cache::put("stream_{$streamKey}_metadata", $streamMetadata, 3600);
            
            // Log stream start
            Log::info('Stream validation successful', [
                'stream_key' => $streamKey,
                'school_id' => $school->id,
                'user_id' => $user->id,
            ]);
            
            return $this->successResponse([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'metadata' => [
                    'camera_name' => $streamKey,
                    'user_name' => $user->first_name . ' ' . $user->last_name,
                    'school_name' => $school->name,
                    'started_at' => now()->toISOString(),
                ]
            ], 'Stream validation successful');
            
        } catch (\Exception $e) {
            Log::error('Stream validation error: ' . $e->getMessage(), [
                'stream_key' => $request->input('stream_key'),
                'error' => $e->getMessage(),
            ]);
            
            return $this->errorResponse('Stream validation failed', null, 500);
        }
    }
    
    /**
     * Get active streams for authenticated user's school
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActiveStreams(Request $request)
    {
        try {
            $user = $request->user();
            $school = $user->getSchool();
            
            if (!$school) {
                return $this->errorResponse('School not found', null, 404);
            }
            
            $activeStreamsKey = "school_{$school->id}_active_streams";
            $streamKeys = Cache::get($activeStreamsKey, []);
            
            $streams = [];
            foreach ($streamKeys as $streamKey) {
                $metadata = Cache::get("stream_{$streamKey}_metadata");
                if ($metadata) {
                    $streams[] = array_merge($metadata, [
                        'playback_urls' => [
                            'flv' => config('services.media_server.url') . "/live/{$streamKey}.flv",
                            'hls' => config('services.media_server.url') . "/live/{$streamKey}/index.m3u8",
                        ]
                    ]);
                }
            }
            
            return $this->successResponse([
                'total' => count($streams),
                'max_allowed' => $school->getModuleSetting('live-streaming', 'max_streams', 10),
                'streams' => $streams,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching active streams: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch streams', null, 500);
        }
    }
    
    /**
     * End a stream (cleanup)
     * 
     * @param Request $request
     * @param string $streamKey
     * @return \Illuminate\Http\JsonResponse
     */
    public function endStream(Request $request, $streamKey)
    {
        try {
            $user = $request->user();
            $school = $user->getSchool();
            
            $streamMetadata = Cache::get("stream_{$streamKey}_metadata");
            
            if (!$streamMetadata) {
                return $this->errorResponse('Stream not found', null, 404);
            }
            
            // Verify ownership
            if ($streamMetadata['school_id'] != $school->id && !in_array($user->user_type, ['super_admin', 'admin'])) {
                return $this->errorResponse('Unauthorized', null, 403);
            }
            
            // Remove from active streams
            $activeStreamsKey = "school_{$school->id}_active_streams";
            $activeStreams = Cache::get($activeStreamsKey, []);
            $activeStreams = array_filter($activeStreams, fn($key) => $key !== $streamKey);
            Cache::put($activeStreamsKey, $activeStreams, 3600);
            
            // Remove metadata
            Cache::forget("stream_{$streamKey}_metadata");
            
            Log::info('Stream ended', [
                'stream_key' => $streamKey,
                'school_id' => $school->id,
            ]);
            
            return $this->successResponse(null, 'Stream ended successfully');
            
        } catch (\Exception $e) {
            Log::error('Error ending stream: ' . $e->getMessage());
            return $this->errorResponse('Failed to end stream', null, 500);
        }
    }
    
    /**
     * Get stream info
     * 
     * @param Request $request
     * @param string $streamKey
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStreamInfo(Request $request, $streamKey)
    {
        try {
            $user = $request->user();
            $school = $user->getSchool();
            
            $metadata = Cache::get("stream_{$streamKey}_metadata");
            
            if (!$metadata) {
                return $this->errorResponse('Stream not found', null, 404);
            }
            
            // Verify school access
            if ($metadata['school_id'] != $school->id && $user->user_type !== 'super_admin') {
                return $this->errorResponse('Unauthorized', null, 403);
            }
            
            return $this->successResponse(array_merge($metadata, [
                'playback_urls' => [
                    'flv' => config('services.media_server.url') . "/live/{$streamKey}.flv",
                    'hls' => config('services.media_server.url') . "/live/{$streamKey}/index.m3u8",
                ],
                'duration' => now()->diffInSeconds($metadata['started_at']),
            ]));
            
        } catch (\Exception $e) {
            Log::error('Error fetching stream info: ' . $e->getMessage());
            return $this->errorResponse('Failed to fetch stream info', null, 500);
        }
    }
}
