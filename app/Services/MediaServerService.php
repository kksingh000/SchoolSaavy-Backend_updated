<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * MediaServerService - Communicates with Node.js RTMP Media Server
 * 
 * Handles:
 * - Checking if streams are live
 * - Getting playback URLs for active streams
 * - Validating stream keys
 * - Getting stream statistics
 */
class MediaServerService
{
    private string $mediaServerUrl;
    private int $timeout;
    private bool $enabled;

    public function __construct()
    {
        $this->mediaServerUrl = config('services.media_server.url', 'http://localhost:8000');
        $this->timeout = config('services.media_server.timeout', 5);
        $this->enabled = config('services.media_server.enabled', true);
    }

    /**
     * Check if media server is online
     */
    public function isOnline(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->mediaServerUrl}/health");
            
            return $response->successful() && $response->json('status') === 'healthy';
        } catch (\Exception $e) {
            Log::warning('Media server health check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a specific stream is currently live
     */
    public function isStreamLive(string $streamKey): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // Cache the result for 10 seconds to reduce API calls
        return Cache::remember(
            "stream_live_{$streamKey}",
            10,
            function () use ($streamKey) {
                try {
                    $response = Http::timeout($this->timeout)
                        ->get("{$this->mediaServerUrl}/api/stream/{$streamKey}");
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        return $data['status'] === 'success' && 
                               isset($data['data']['isActive']) && 
                               $data['data']['isActive'];
                    }
                    
                    return false;
                } catch (\Exception $e) {
                    Log::debug("Stream check failed for {$streamKey}: " . $e->getMessage());
                    return false;
                }
            }
        );
    }

    /**
     * Get all active streams
     */
    public function getActiveStreams(): array
    {
        if (!$this->enabled) {
            return [];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->mediaServerUrl}/api/streams");
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['data']['streams'] ?? [];
            }
            
            return [];
        } catch (\Exception $e) {
            Log::warning('Failed to fetch active streams: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get streams for a specific school
     */
    public function getSchoolStreams(int $schoolId): array
    {
        if (!$this->enabled) {
            return [];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->mediaServerUrl}/api/streams/school/{$schoolId}");
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['data']['streams'] ?? [];
            }
            
            return [];
        } catch (\Exception $e) {
            Log::warning("Failed to fetch streams for school {$schoolId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get playback URLs for a stream key
     * 
     * @param string $streamKey The unique stream identifier
     * @return array Array of playback URLs with formats
     */
    public function getPlaybackUrls(string $streamKey): array
    {
        $host = parse_url($this->mediaServerUrl, PHP_URL_HOST) ?? 'localhost';
        $httpPort = config('services.media_server.http_port', 8000);
        $rtmpPort = config('services.media_server.rtmp_port', 1935);

        return [
            'http_flv' => "http://{$host}:{$httpPort}/live/{$streamKey}.flv",
            'hls' => "http://{$host}:{$httpPort}/live/{$streamKey}/index.m3u8",
            'rtmp' => "rtmp://{$host}:{$rtmpPort}/live/{$streamKey}",
        ];
    }

    /**
     * Generate RTMP publish URL for a camera
     * 
     * @param int $cameraId Database camera ID
     * @param int $schoolId School ID for organization
     * @param int $userId User ID who is streaming
     * @param string|null $token Optional authentication token
     * @return array Publish URL and stream key
     */
    public function generatePublishUrl(int $cameraId, int $schoolId, int $userId, ?string $token = null): array
    {
        $streamKey = $this->generateStreamKey($schoolId, $userId, $cameraId);
        $host = parse_url($this->mediaServerUrl, PHP_URL_HOST) ?? 'localhost';
        $rtmpPort = config('services.media_server.rtmp_port', 1935);
        
        $publishUrl = "rtmp://{$host}:{$rtmpPort}/live";
        
        // Add token to stream key if authentication is enabled
        if ($token && config('services.media_server.auth_enabled', false)) {
            $streamKey .= "?token={$token}";
        }

        return [
            'publish_url' => $publishUrl,
            'stream_key' => $streamKey,
            'full_url' => "{$publishUrl}/{$streamKey}",
        ];
    }

    /**
     * Generate a unique stream key
     * Format: {school_id}_{user_id}_{camera_id}
     */
    public function generateStreamKey(int $schoolId, int $userId, int $cameraId): string
    {
        return "{$schoolId}_{$userId}_{$cameraId}";
    }

    /**
     * Parse stream key into components
     * 
     * @param string $streamKey
     * @return array|null [school_id, user_id, camera_id] or null if invalid
     */
    public function parseStreamKey(string $streamKey): ?array
    {
        // Remove token if present
        $streamKey = explode('?', $streamKey)[0];
        
        $parts = explode('_', $streamKey);
        
        if (count($parts) !== 3) {
            return null;
        }

        return [
            'school_id' => (int) $parts[0],
            'user_id' => (int) $parts[1],
            'camera_id' => (int) $parts[2],
        ];
    }

    /**
     * Get stream statistics
     */
    public function getStreamStats(string $streamKey): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->mediaServerUrl}/api/stream/{$streamKey}");
            
            if ($response->successful()) {
                return $response->json('data');
            }
            
            return null;
        } catch (\Exception $e) {
            Log::debug("Failed to get stats for {$streamKey}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate stream credentials with media server
     */
    public function validateStreamCredentials(string $streamKey, string $token): bool
    {
        if (!$this->enabled || !config('services.media_server.auth_enabled', false)) {
            return true; // No validation needed
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Authorization' => "Bearer {$token}"])
                ->post("{$this->mediaServerUrl}/api/validate-stream", [
                    'stream_key' => $streamKey,
                ]);
            
            return $response->successful() && $response->json('valid') === true;
        } catch (\Exception $e) {
            Log::error("Stream validation failed for {$streamKey}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recommended stream settings for mobile broadcasting
     */
    public function getRecommendedStreamSettings(): array
    {
        return [
            'video' => [
                'codec' => 'H.264',
                'profile' => 'baseline',
                'bitrate' => '1500kbps',
                'fps' => 30,
                'resolution' => '1280x720',
                'keyframe_interval' => 2,
            ],
            'audio' => [
                'codec' => 'AAC',
                'bitrate' => '128kbps',
                'sample_rate' => '44100Hz',
                'channels' => 2,
            ],
            'network' => [
                'protocol' => 'RTMP',
                'chunk_size' => 4096,
                'buffer_size' => '1s',
            ],
        ];
    }

    /**
     * Clear stream cache
     */
    public function clearStreamCache(string $streamKey): void
    {
        Cache::forget("stream_live_{$streamKey}");
    }

    /**
     * Get media server configuration
     */
    public function getServerConfig(): array
    {
        return [
            'url' => $this->mediaServerUrl,
            'enabled' => $this->enabled,
            'auth_enabled' => config('services.media_server.auth_enabled', false),
            'http_port' => config('services.media_server.http_port', 8000),
            'rtmp_port' => config('services.media_server.rtmp_port', 1935),
            'is_online' => $this->isOnline(),
        ];
    }
}
