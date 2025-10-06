<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SchoolCamera extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'room_id',
        'camera_name',
        'camera_type',
        'description',
        'stream_url',
        'rtmp_url',
        'thumbnail_url',
        'status',
        'privacy_level',
        'settings',
        'installation_date',
        'location_description',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'settings' => 'array',
        'installation_date' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected $hidden = [
        // These will be conditionally hidden based on user role
    ];

    protected $appends = [
        'secure_stream_url',
        'direct_stream_url',
        'alternative_stream_urls',
        'is_online',
        'total_viewers',
    ];

    // Relationships
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function room()
    {
        return $this->belongsTo(ClassRoom::class, 'room_id');
    }

    public function permissions()
    {
        return $this->hasMany(CameraPermission::class, 'camera_id');
    }

    public function schedules()
    {
        return $this->hasMany(CameraSchedule::class, 'camera_id');
    }

    public function accessLogs()
    {
        return $this->hasMany(CameraAccessLog::class, 'camera_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeByPrivacyLevel($query, $level)
    {
        return $query->where('privacy_level', $level);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('camera_type', $type);
    }

    public function scopeInRoom($query, $roomId)
    {
        return $query->where('room_id', $roomId);
    }

    // Accessors
    public function getSecureStreamUrlAttribute()
    {
        if (!$this->stream_url || $this->status !== 'active') {
            return null;
        }

        // Generate secure, time-limited stream URL
        return $this->generateSecureStreamUrl();
    }

    public function getDirectStreamUrlAttribute()
    {
        if (!$this->stream_url || $this->status !== 'active') {
            return null;
        }

        // If the stream_url is already an HTTP URL (like from DevTunnels), return as-is
        if (strpos($this->stream_url, 'http://') === 0 || strpos($this->stream_url, 'https://') === 0) {
            return $this->stream_url;
        }

        // If it's an RTMP URL, generate playback URLs via media server
        if (strpos($this->stream_url, 'rtmp://') === 0) {
            $mediaServerService = app(\App\Services\MediaServerService::class);
            
            // Extract stream key from RTMP URL
            // Format: rtmp://host:port/app/stream_key
            $parts = parse_url($this->stream_url);
            if (isset($parts['path'])) {
                $pathParts = explode('/', trim($parts['path'], '/'));
                $streamKey = end($pathParts); // Get last part as stream key
                
                // Get playback URLs from media server
                $playbackUrls = $mediaServerService->getPlaybackUrls($streamKey);
                
                // Return HTTP-FLV as primary playback format (best for mobile)
                return $playbackUrls['http_flv'] ?? null;
            }
        }

        // Fallback to original URL if format is unknown
        return $this->stream_url;
    }

    public function getAlternativeStreamUrlsAttribute()
    {
        if (!$this->stream_url || $this->status !== 'active') {
            return [];
        }

        $urls = [];
        
        // If it's an HTTP/HTTPS URL, try variations
        if (strpos($this->stream_url, 'http://') === 0 || strpos($this->stream_url, 'https://') === 0) {
            $urls[] = $this->stream_url;
            
            // Try .m3u8 (HLS) variant
            if (strpos($this->stream_url, '.flv') !== false) {
                $urls[] = str_replace('.flv', '.m3u8', $this->stream_url);
            }
            
            // Try /hls/ path variant
            if (strpos($this->stream_url, '/live/') !== false) {
                $hlsUrl = str_replace('/live/', '/hls/live/', $this->stream_url);
                $hlsUrl = str_replace('.flv', '.m3u8', $hlsUrl);
                $urls[] = $hlsUrl;
            }
        }
        // If it's an RTMP URL, generate all playback formats via media server
        elseif (strpos($this->stream_url, 'rtmp://') === 0) {
            $mediaServerService = app(\App\Services\MediaServerService::class);
            
            // Extract stream key from RTMP URL
            $parts = parse_url($this->stream_url);
            if (isset($parts['path'])) {
                $pathParts = explode('/', trim($parts['path'], '/'));
                $streamKey = end($pathParts);
                
                // Get all playback URLs from media server
                $playbackUrls = $mediaServerService->getPlaybackUrls($streamKey);
                $urls = array_values($playbackUrls);
                
                // Add original RTMP URL as fallback
                $urls[] = $this->stream_url;
            }
        }
        
        // Add test video as absolute last resort
        $urls[] = 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';
        
        return array_unique($urls);
    }

    public function getIsOnlineAttribute()
    {
        // Check if camera is marked as active in database
        if ($this->status !== 'active') {
            return false;
        }

        // If stream URL is an HTTP/HTTPS URL, assume it's online (external stream)
        if (strpos($this->stream_url, 'http://') === 0 || strpos($this->stream_url, 'https://') === 0) {
            return true;
        }

        // If it's an RTMP URL, check with media server if stream is actually live
        if (strpos($this->stream_url, 'rtmp://') === 0) {
            try {
                $mediaServerService = app(\App\Services\MediaServerService::class);
                
                // Extract stream key
                $parts = parse_url($this->stream_url);
                if (isset($parts['path'])) {
                    $pathParts = explode('/', trim($parts['path'], '/'));
                    $streamKey = end($pathParts);
                    
                    // Check if stream is currently live on media server
                    return $mediaServerService->isStreamLive($streamKey);
                }
            } catch (\Exception $e) {
                Log::debug("Error checking stream status: " . $e->getMessage());
                // Fallback to true if media server is unavailable
                return true;
            }
        }

        // Default to active status
        return true;
    }

    public function getTotalViewersAttribute()
    {
        return $this->accessLogs()
            ->whereNull('access_end_time')
            ->where('access_result', 'success')
            ->count();
    }

    // Methods
    public function hideStreamUrlsFromParents()
    {
        // Hide stream URLs from parent users for security
        $this->addHidden(['stream_url', 'rtmp_url']);
        return $this;
    }

    public function showStreamUrlsForAdmin()
    {
        // Show stream URLs for admin users
        $this->makeVisible(['stream_url', 'rtmp_url']);
        return $this;
    }

    public function generateSecureStreamUrl($parentId = null, $expiresIn = 3600)
    {
        $payload = [
            'camera_id' => $this->id,
            'parent_id' => $parentId,
            'expires_at' => now()->addSeconds($expiresIn)->timestamp,
            'school_id' => $this->school_id,
        ];

        $token = base64_encode(Crypt::encryptString(json_encode($payload)));
        
        return config('app.url') . "/api/camera/stream/{$this->id}?token={$token}";
    }

    public function isWithinSchedule()
    {
        $currentDay = strtolower(now()->format('l'));
        $currentTime = now()->format('H:i:s');

        $activeSchedules = $this->schedules()
            ->where('day_of_week', $currentDay)
            ->where('is_active', true)
            ->where('schedule_type', 'active')
            ->where('start_time', '<=', $currentTime)
            ->where('end_time', '>=', $currentTime)
            ->exists();

        return $activeSchedules;
    }

    public function hasParentAccess($parentId, $studentId = null)
    {
        // Class-based access: Check if parent's child is in this camera's room/class
        $parent = \App\Models\User::find($parentId);
        if (!$parent || $parent->user_type !== 'parent') {
            return false;
        }

        $classRoomIds = collect();
        
        if ($studentId) {
            // Check specific student's class room
            $student = \App\Models\Student::forSchool($this->school_id)->find($studentId);
            if ($student && $student->parents->contains($parent->parent)) {
                $classRoomIds->push($student->class_id);
            }
        } else {
            // Check all children's class rooms
            $children = $parent->parent->students()->forSchool($this->school_id)->get();
            $classRoomIds = $children->pluck('class_id');
        }

        return $classRoomIds->contains($this->room_id);
    }

    public function canBeAccessedBy($parentId, $studentId = null)
    {
        // Check if camera is active and within schedule
        if (!$this->is_online) {
            return false;
        }

        // Check privacy level
        if ($this->privacy_level === 'disabled' || $this->privacy_level === 'private') {
            return false;
        }

        // Check class-based access
        return $this->hasParentAccess($parentId, $studentId);
    }

    public function logAccess($parentId, $studentId = null, $metadata = [])
    {
        return CameraAccessLog::create([
            'camera_id' => $this->id,
            'parent_id' => $parentId,
            'student_id' => $studentId,
            'access_start_time' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'device_type' => $this->detectDeviceType(request()->userAgent()),
            'access_result' => 'success',
            'session_metadata' => $metadata,
        ]);
    }

    private function detectDeviceType($userAgent)
    {
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            return preg_match('/iPad/', $userAgent) ? 'tablet' : 'mobile';
        }
        
        return 'desktop';
    }
}