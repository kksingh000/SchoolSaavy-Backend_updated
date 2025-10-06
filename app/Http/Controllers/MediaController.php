<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MediaController extends BaseController
{
    /**
     * Get streaming credentials using existing auth token
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStreamingCredentials(Request $request)
    {
        try {
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
                return $this->errorResponse('Only teachers and admins can stream', null, 403);
            }
            
            // Check if school has active live-streaming module
            if (!$this->checkModuleAccess('live-streaming')) {
                return $this->errorResponse('Live streaming module not active', null, 403);
            }
            
            // Get optional camera name from request
            $cameraName = $request->input('camera_name', 'Camera ' . $user->id);
            $description = $request->input('description', '');
            
            // Generate stream key based on user ID and school ID
            // Format: {school_id}_{user_id}
            $streamKey = sprintf('%s_%s', $school->id, $user->id);
            
            // Get the current auth token (from Bearer header)
            $authToken = $request->bearerToken();
            
            // URL-encode the token for RTMP URL (handles special characters like |)
            $encodedToken = urlencode($authToken);
            
            // Store stream metadata
            $streamData = [
                'stream_key' => $streamKey,
                'school_id' => $school->id,
                'user_id' => $user->id,
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'camera_name' => $cameraName,
                'description' => $description,
                'created_at' => now()->toISOString(),
                'status' => 'ready', // ready to stream
            ];
            
            // Store in cache (24 hours)
            Cache::put("stream_credentials_{$user->id}", $streamData, 86400);
            
            Log::info('Streaming credentials generated', [
                'stream_key' => $streamKey,
                'school_id' => $school->id,
                'user_id' => $user->id,
            ]);
            
            $mediaServerUrl = config('services.media_server.public_host', 'stream.schoolsaavy.com');
            
            return $this->successResponse([
                'stream_key' => $streamKey,
                'camera_name' => $cameraName,
                'rtmp_url' => "rtmp://{$mediaServerUrl}/live/{$streamKey}?token={$encodedToken}",
                'playback_urls' => [
                    'flv' => "https://{$mediaServerUrl}/live/{$streamKey}.flv",
                    'hls' => "https://{$mediaServerUrl}/hls/{$streamKey}/index.m3u8",
                ],
                'instructions' => [
                    'step_1' => 'Open Larix Broadcaster app on your mobile device',
                    'step_2' => 'Add new connection with the RTMP URL above',
                    'step_3' => 'Start streaming',
                    'step_4' => 'Share the playback URL with viewers',
                ],
                'note' => 'Your existing authentication token is used for streaming. Keep it secure!'
            ], 'Streaming credentials generated successfully');
            
        } catch (\Exception $e) {
            Log::error('Error generating streaming credentials: ' . $e->getMessage());
            return $this->errorResponse('Failed to generate streaming credentials', null, 500);
        }
    }
    
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
            $token = $request->input('token');
            
            if (!$streamKey || !$token) {
                return $this->errorResponse('Missing stream key or token', null, 400);
            }
            
            // URL-decode the token (handles special characters like | that were encoded)
            $decodedToken = urldecode($token);
            
            // Parse stream key: {school_id}_{user_id}
            $parts = explode('_', $streamKey);
            if (count($parts) !== 2) {
                return $this->errorResponse('Invalid stream key format', null, 400);
            }
            
            [$schoolId, $userId] = $parts;
            
            // Validate the token using Sanctum (use decoded token)
            $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($decodedToken);
            
            if (!$tokenModel) {
                return $this->errorResponse('Invalid authentication token', null, 401);
            }
            
            $user = $tokenModel->tokenable;
            
            // Verify user ID matches stream key
            if ($user->id != $userId) {
                return $this->errorResponse('Token does not match stream key', null, 403);
            }
            
            // Get user's school
            $school = $user->getSchool();
            
            if (!$school || $school->id != $schoolId) {
                return $this->errorResponse('School mismatch', null, 403);
            }
            
            // Check if user has permission to stream
            if (!in_array($user->user_type, ['admin', 'teacher'])) {
                return $this->errorResponse('Insufficient permissions', null, 403);
            }
            
            // Check if school has active live-streaming module
            // Note: checkModuleAccess needs user context
            $moduleActive = $school->modules()
                ->where('module_name', 'live-streaming')
                ->where('is_active', true)
                ->exists();
                
            if (!$moduleActive) {
                return $this->errorResponse('Live streaming module not active', null, 403);
            }
            
            // Get current active streams for school
            $activeStreamsKey = "school_{$school->id}_active_streams";
            $activeStreams = Cache::get($activeStreamsKey, []);
            
            // Check stream limit
            $maxStreams = 10; // Default, can be configured per school
            
            if (count($activeStreams) >= $maxStreams && !in_array($streamKey, $activeStreams)) {
                return $this->errorResponse('Maximum stream limit reached for school', null, 429);
            }
            
            // Add stream to active list
            if (!in_array($streamKey, $activeStreams)) {
                $activeStreams[] = $streamKey;
                Cache::put($activeStreamsKey, $activeStreams, 3600); // 1 hour TTL
            }
            
            // Get or create stream metadata
            $streamMetadata = Cache::get("stream_credentials_{$userId}", [
                'camera_name' => 'Camera ' . $userId,
                'description' => '',
            ]);
            
            // Update stream metadata with active status
            $streamMetadata = array_merge($streamMetadata, [
                'stream_key' => $streamKey,
                'school_id' => $school->id,
                'user_id' => $user->id,
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'started_at' => now()->toISOString(),
                'status' => 'active',
            ]);
            
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
                    'camera_name' => $streamMetadata['camera_name'] ?? 'Camera',
                    'user_name' => $user->first_name . ' ' . $user->last_name,
                    'school_name' => $school->name,
                    'started_at' => now()->toISOString(),
                ]
            ], 'Stream validation successful');
            
        } catch (\Exception $e) {
            Log::error('Stream validation error: ' . $e->getMessage(), [
                'stream_key' => $request->input('stream_key'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
