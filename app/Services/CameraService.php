<?php

namespace App\Services;

use App\Models\SchoolCamera;
use App\Models\CameraPermission;
use App\Models\CameraAccessLog;
use App\Models\CameraSchedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class CameraService extends BaseService
{
    protected MediaServerService $mediaServerService;

    public function __construct()
    {
        $this->mediaServerService = app(MediaServerService::class);
    }
    protected function initializeModel()
    {
        $this->model = SchoolCamera::class;
    }

    /**
     * Get cameras for school with filters
     */
    public function getCameras(int $schoolId, array $filters = [], int $perPage = 15)
    {
        $query = SchoolCamera::with(['room', 'schedules'])
            ->forSchool($schoolId);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['camera_type'])) {
            $query->where('camera_type', $filters['camera_type']);
        }

        if (!empty($filters['privacy_level'])) {
            $query->where('privacy_level', $filters['privacy_level']);
        }

        if (!empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('camera_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('location_description', 'like', '%' . $filters['search'] . '%');
            });
        }

        $cameras = $query->orderBy('camera_name')->paginate($perPage);
        
        // Show stream URLs for admin users
        $cameras->getCollection()->transform(function ($camera) {
            return $camera->showStreamUrlsForAdmin();
        });

        return $cameras;
    }

    /**
     * Create new camera
     */
    public function createCamera(array $data)
    {
        $data['school_id'] = $data['school_id'] ?? Auth::user()->school_id;
        $data['installation_date'] = $data['installation_date'] ?? now();

        $camera = SchoolCamera::create($data);

        // Create default schedules (Monday to Friday, 8 AM to 4 PM)
        if (!empty($data['create_default_schedule'])) {
            $this->createDefaultSchedules($camera->id);
        }

        return $camera->load(['room', 'schedules']);
    }

    /**
     * Update camera
     */
    public function updateCamera(int $cameraId, array $data, int $schoolId)
    {
        $camera = SchoolCamera::forSchool($schoolId)->findOrFail($cameraId);
        $camera->update($data);

        return $camera->load(['room', 'schedules']);
    }

    /**
     * Delete camera
     */
    public function deleteCamera(int $cameraId, int $schoolId)
    {
        $camera = SchoolCamera::forSchool($schoolId)->findOrFail($cameraId);
        
        // End all active sessions
        CameraAccessLog::forCamera($cameraId)
            ->active()
            ->update([
                'access_end_time' => now(),
                'duration_seconds' => DB::raw('TIMESTAMPDIFF(SECOND, access_start_time, NOW())')
            ]);

        return $camera->delete();
    }

    /**
     * Get cameras accessible by parent (class-based access)
     */
    public function getAccessibleCamerasForParent(int $parentId, int $schoolId, int $studentId = null)
    {
        // Get parent's children to find their classes
        $parent = \App\Models\User::find($parentId);
        if (!$parent || $parent->user_type !== 'parent') {
            return collect();
        }

        // Get children's class rooms
        $classRoomIds = collect();
        
        if ($studentId) {
            // Get specific student's current class room
            $student = \App\Models\Student::forSchool($schoolId)->find($studentId);
            if ($student && $student->parents->contains($parent->parent)) {
                // Get student's current classes (active enrollments)
                $currentClasses = $student->classes()->wherePivot('is_active', true)->get();
                $classRoomIds = $currentClasses->pluck('id');
            }
        } else {
            // Get all children's current class rooms
            $children = $parent->parent->students()
                ->forSchool($schoolId)
                ->with(['classes' => function($query) {
                    $query->wherePivot('is_active', true);
                }])
                ->get();
                
            foreach ($children as $child) {
                foreach ($child->classes as $class) {
                    $classRoomIds->push($class->id);
                }
            }
            $classRoomIds = $classRoomIds->unique();
        }

        if ($classRoomIds->isEmpty()) {
            Log::debug("Parent {$parentId} has no children with active class enrollments in school {$schoolId}");
            return collect();
        }

        Log::debug("Parent {$parentId} has children in classes: " . $classRoomIds->implode(', '));

        // Get cameras in children's class rooms with enhanced data
        $cameras = SchoolCamera::with(['room'])
            ->forSchool($schoolId)
            ->active()
            ->whereIn('privacy_level', ['public', 'restricted'])
            ->whereIn('room_id', $classRoomIds)
            ->get();

        // Add media server status for RTMP cameras
        $cameras->transform(function($camera) {
            if (strpos($camera->stream_url, 'rtmp://') === 0) {
                try {
                    $parts = parse_url($camera->stream_url);
                    if (isset($parts['path'])) {
                        $pathParts = explode('/', trim($parts['path'], '/'));
                        $streamKey = end($pathParts);
                        
                        // Add real-time status from media server
                        $camera->setAttribute('stream_server_status', [
                            'is_live' => $this->mediaServerService->isStreamLive($streamKey),
                            'stream_key' => $streamKey,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::debug("Could not check media server status for camera {$camera->id}");
                }
            }
            
            return $camera;
        });

        return $cameras;
    }

    /**
     * Check if parent can access camera based on student's class
     */
    public function canParentAccessCamera(int $parentId, int $cameraId, int $schoolId, int $studentId = null)
    {
        $camera = SchoolCamera::forSchool($schoolId)->find($cameraId);
        if (!$camera || $camera->status !== 'active') {
            Log::debug("Camera {$cameraId} not found or not active");
            return false;
        }

        // Check if camera privacy allows parent access
        if (!in_array($camera->privacy_level, ['public', 'restricted'])) {
            Log::debug("Camera {$cameraId} privacy level ({$camera->privacy_level}) doesn't allow parent access");
            return false;
        }

        // Get parent user
        $parent = User::find($parentId);
        if (!$parent || $parent->user_type !== 'parent' || !$parent->parent) {
            Log::debug("Invalid parent user: {$parentId}");
            return false;
        }

        // Check if camera has a room assigned
        if (!$camera->room_id) {
            Log::debug("Camera {$cameraId} has no room assigned");
            // Allow access if camera has no room (might be a common area camera)
            return true;
        }

        // Get the children's class room IDs
        $childrenQuery = $parent->parent->students()->forSchool($schoolId);
        
        if ($studentId) {
            $childrenQuery->where('id', $studentId);
        }
        
        $children = $childrenQuery->with(['classes' => function($query) {
            $query->wherePivot('is_active', true);
        }])->get();

        if ($children->isEmpty()) {
            Log::debug("Parent {$parentId} has no children in school {$schoolId}");
            return false;
        }

        // Check if any child is enrolled in the camera's classroom
        foreach ($children as $child) {
            foreach ($child->classes as $class) {
                if ($class->id == $camera->room_id) {
                    Log::debug("Access granted: Child {$child->id} is in class {$class->id} matching camera room");
                    return true;
                }
            }
        }

        Log::debug("Access denied: No children in camera's room {$camera->room_id}");
        return false;
    }

    /**
     * Approve camera access
     */
    public function approveCameraAccess(int $permissionId, int $schoolId, int $approvedBy, array $settings = [])
    {
        $permission = CameraPermission::forSchool($schoolId)->findOrFail($permissionId);
        
        $permission->approve(
            $approvedBy,
            $settings['access_start_time'] ?? null,
            $settings['access_end_time'] ?? null
        );

        // Send notification to parent
        $this->sendAccessApprovalNotification($permission);

        return $permission->load(['parent', 'student', 'camera']);
    }

    /**
     * Reject camera access
     */
    public function rejectCameraAccess(int $permissionId, int $schoolId, int $rejectedBy, string $reason = null)
    {
        $permission = CameraPermission::forSchool($schoolId)->findOrFail($permissionId);
        $permission->reject($rejectedBy, $reason);

        // Send notification to parent
        $this->sendAccessRejectionNotification($permission);

        return $permission;
    }

    /**
     * Generate secure stream token (class-based access)
     */
    public function generateStreamToken(int $cameraId, int $parentId, int $studentId = null, int $expiresIn = 3600)
    {
        $camera = SchoolCamera::findOrFail($cameraId);
        
        // Verify parent has class-based access to this camera
        if (!$this->canParentAccessCamera($parentId, $cameraId, $camera->school_id, $studentId)) {
            throw new \Exception('Access denied. You can only view cameras in your child\'s classroom.');
        }

        // Log access attempt
        $accessLog = $camera->logAccess($parentId, $studentId);

        // Get playback URLs
        $playbackUrls = $this->getPlaybackUrls($camera);

        return [
            'token' => $camera->generateSecureStreamUrl($parentId, $expiresIn),
            'expires_in' => $expiresIn,
            'access_log_id' => $accessLog->id,
            'camera_name' => $camera->camera_name,
            'classroom' => $camera->room ? $camera->room->name : null,
            'stream_urls' => $playbackUrls,
            'is_live' => $camera->is_online,
            'recommended_format' => 'http_flv', // Best for mobile
        ];
    }

    /**
     * Get playback URLs for a camera
     */
    private function getPlaybackUrls(SchoolCamera $camera): array
    {
        $urls = [
            'primary' => $camera->direct_stream_url,
            'alternatives' => $camera->alternative_stream_urls,
        ];

        // If camera uses RTMP, add media server playback URLs
        if (strpos($camera->stream_url, 'rtmp://') === 0) {
            try {
                $parts = parse_url($camera->stream_url);
                if (isset($parts['path'])) {
                    $pathParts = explode('/', trim($parts['path'], '/'));
                    $streamKey = end($pathParts);
                    
                    $mediaServerUrls = $this->mediaServerService->getPlaybackUrls($streamKey);
                    $urls['media_server'] = $mediaServerUrls;
                    
                    // Check if stream is actually live
                    $urls['is_live_on_server'] = $this->mediaServerService->isStreamLive($streamKey);
                }
            } catch (\Exception $e) {
                Log::warning("Could not get media server URLs for camera {$camera->id}: " . $e->getMessage());
            }
        }

        return $urls;
    }

    /**
     * Validate stream token
     */
    public function validateStreamToken(string $token, int $cameraId)
    {
        try {
            $payload = json_decode(Crypt::decryptString(base64_decode($token)), true);

            if ($payload['camera_id'] != $cameraId) {
                return false;
            }

            if ($payload['expires_at'] < now()->timestamp) {
                return false;
            }

            return $payload;
        } catch (\Exception $e) {
            Log::error('Stream token validation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * End camera access session
     */
    public function endAccessSession(int $accessLogId)
    {
        $accessLog = CameraAccessLog::findOrFail($accessLogId);
        return $accessLog->endSession();
    }

    /**
     * Get camera analytics
     */
    public function getCameraAnalytics(int $cameraId, int $schoolId, array $filters = [])
    {
        $camera = SchoolCamera::forSchool($schoolId)->findOrFail($cameraId);
        
        $startDate = $filters['start_date'] ?? now()->subDays(30);
        $endDate = $filters['end_date'] ?? now();

        $analytics = [
            'total_views' => $camera->accessLogs()
                ->inDateRange($startDate, $endDate)
                ->successful()
                ->count(),
            
            'unique_viewers' => $camera->accessLogs()
                ->inDateRange($startDate, $endDate)
                ->successful()
                ->distinct('parent_id')
                ->count(),
            
            'average_session_duration' => $camera->accessLogs()
                ->inDateRange($startDate, $endDate)
                ->successful()
                ->whereNotNull('access_end_time')
                ->avg('duration_seconds'),
            
            'peak_viewing_hours' => $this->getPeakViewingHours($cameraId, $startDate, $endDate),
            
            'daily_views' => $this->getDailyViewStats($cameraId, $startDate, $endDate),
            
            'device_breakdown' => $this->getDeviceBreakdown($cameraId, $startDate, $endDate),
        ];

        return $analytics;
    }

    /**
     * Get cameras by room/class
     */
    public function getCamerasByRoom(int $roomId, int $schoolId)
    {
        return SchoolCamera::forSchool($schoolId)
            ->inRoom($roomId)
            ->active()
            ->with(['schedules'])
            ->get();
    }

    /**
     * Update camera privacy level
     */
    public function updateCameraPrivacy(int $cameraId, int $schoolId, string $privacyLevel)
    {
        $camera = SchoolCamera::forSchool($schoolId)->findOrFail($cameraId);
        
        $camera->update(['privacy_level' => $privacyLevel]);

        // If privacy is set to disabled or private, end all active sessions
        if (in_array($privacyLevel, ['disabled', 'private'])) {
            CameraAccessLog::forCamera($cameraId)
                ->active()
                ->update([
                    'access_end_time' => now(),
                    'duration_seconds' => DB::raw('TIMESTAMPDIFF(SECOND, access_start_time, NOW())')
                ]);
        }

        return $camera;
    }

    /**
     * Get camera permissions for management
     */
    public function getCameraPermissions(int $schoolId, array $filters = [], int $perPage = 15)
    {
        $query = CameraPermission::with(['camera', 'parent', 'student', 'approver'])
            ->forSchool($schoolId);

        if (!empty($filters['status'])) {
            $query->where('request_status', $filters['status']);
        }

        if (!empty($filters['camera_id'])) {
            $query->where('camera_id', $filters['camera_id']);
        }

        if (!empty($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    // Private helper methods
    private function createDefaultSchedules(int $cameraId)
    {
        $weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        
        foreach ($weekdays as $day) {
            CameraSchedule::create([
                'camera_id' => $cameraId,
                'schedule_name' => 'Default School Hours',
                'day_of_week' => $day,
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'is_active' => true,
                'schedule_type' => 'active',
                'description' => 'Default active hours for school days',
            ]);
        }
    }

    private function sendAccessApprovalNotification(CameraPermission $permission)
    {
        // Implement notification logic here
        // This would integrate with your existing notification system
    }

    private function sendAccessRejectionNotification(CameraPermission $permission)
    {
        // Implement notification logic here
    }

    private function getPeakViewingHours(int $cameraId, $startDate, $endDate)
    {
        return CameraAccessLog::forCamera($cameraId)
            ->inDateRange($startDate, $endDate)
            ->successful()
            ->selectRaw('HOUR(access_start_time) as hour, COUNT(*) as views')
            ->groupBy('hour')
            ->orderBy('views', 'desc')
            ->get();
    }

    private function getDailyViewStats(int $cameraId, $startDate, $endDate)
    {
        return CameraAccessLog::forCamera($cameraId)
            ->inDateRange($startDate, $endDate)
            ->successful()
            ->selectRaw('DATE(access_start_time) as date, COUNT(*) as views, COUNT(DISTINCT parent_id) as unique_viewers')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    private function getDeviceBreakdown(int $cameraId, $startDate, $endDate)
    {
        return CameraAccessLog::forCamera($cameraId)
            ->inDateRange($startDate, $endDate)
            ->successful()
            ->selectRaw('device_type, COUNT(*) as count, AVG(duration_seconds) as avg_duration')
            ->groupBy('device_type')
            ->get();
    }
}