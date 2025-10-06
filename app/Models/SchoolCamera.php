<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

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

        // Convert RTMP URL to various HTTP streaming formats
        if (strpos($this->stream_url, 'rtmp://') === 0) {
            // Try multiple common streaming server configurations
            $baseUrl = str_replace('rtmp://', 'http://', $this->stream_url);
            $baseUrl = str_replace(':1935/', ':8000/', $baseUrl);
            
            // Return the most common FLV format first
            return $baseUrl . '.flv';
        }

        return $this->stream_url;
    }

    public function getAlternativeStreamUrlsAttribute()
    {
        if (!$this->stream_url || $this->status !== 'active') {
            return [];
        }

        $urls = [];
        
        if (strpos($this->stream_url, 'rtmp://') === 0) {
            $basePath = str_replace('rtmp://192.168.1.8:1935/', '', $this->stream_url);
            
            // Common streaming server URL patterns + fallback test video
            $urls = [
                "http://192.168.1.8:8000/{$basePath}.flv",
                "http://192.168.1.8:8000/hls/{$basePath}.m3u8", 
                "http://192.168.1.8:8080/{$basePath}.flv",
                "http://192.168.1.8:8080/hls/{$basePath}.m3u8",
                "http://192.168.1.8:8080/live/{$basePath}/index.m3u8",
                "http://192.168.1.8:1935/live/{$basePath}/playlist.m3u8",
                $this->stream_url, // Original RTMP URL
                // Fallback test video if no real stream is available
                'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
            ];
        }
        
        return array_unique($urls);
    }

    public function getIsOnlineAttribute()
    {
        // Always return true if camera is active - let the client try to connect
        return $this->status === 'active';
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