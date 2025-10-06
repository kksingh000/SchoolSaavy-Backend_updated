<?php

namespace App\Http\Controllers;

use App\Models\SchoolCamera;
use App\Services\CameraService;
use App\Http\Requests\CreateCameraRequest;
use App\Http\Requests\UpdateCameraRequest;
use App\Http\Requests\CameraAccessRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * CameraController - Manages school camera system
 * 
 * Handles camera CRUD operations, access control, and live streaming
 * Follows SchoolSavvy architecture patterns with proper validation
 */
class CameraController extends BaseController
{
    protected CameraService $cameraService;

    public function __construct(CameraService $cameraService)
    {
        $this->cameraService = $cameraService;
    }

    /**
     * Get all cameras for school
     */
    public function index(Request $request)
    {
        if (!$this->checkModuleAccess('camera-monitoring')) {
            return $this->moduleAccessDenied();
        }

        try {
            $schoolId = $request->school_id;
            $filters = $request->only(['status', 'camera_type', 'privacy_level', 'room_id', 'search']);
            $perPage = min($request->get('per_page', 15), 50);

            $cameras = $this->cameraService->getCameras($schoolId, $filters, $perPage);

            return $this->successResponse($cameras, 'Cameras retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve cameras: ' . $e->getMessage());
        }
    }

    /**
     * Get single camera details
     */
    public function show(Request $request, int $id)
    {
        if (!$this->checkModuleAccess('camera-monitoring')) {
            return $this->moduleAccessDenied();
        }

        try {
            $schoolId = $request->school_id;
            $camera = $this->cameraService->find($id);

            if (!$camera || $camera->school_id !== $schoolId) {
                return $this->errorResponse('Camera not found', null, 404);
            }

            return $this->successResponse($camera->load(['room', 'schedules'])->showStreamUrlsForAdmin(), 'Camera retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve camera: ' . $e->getMessage());
        }
    }

    /**
     * Create new camera
     */
    public function store(CreateCameraRequest $request)
    {
        if (!$this->checkModuleAccess('camera-monitoring')) {
            return $this->moduleAccessDenied();
        }

        try {
            $data = $request->validated();
            $data['school_id'] = $request->school_id;

            $camera = $this->cameraService->createCamera($data);

            return $this->successResponse($camera, 'Camera created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create camera: ' . $e->getMessage());
        }
    }

    /**
     * Update camera
     */
    public function update(UpdateCameraRequest $request, int $id)
    {
        if (!$this->checkModuleAccess('camera-monitoring')) {
            return $this->moduleAccessDenied();
        }

        try {
            $schoolId = $request->school_id;
            $data = $request->validated();

            $camera = $this->cameraService->updateCamera($id, $data, $schoolId);

            return $this->successResponse($camera, 'Camera updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update camera: ' . $e->getMessage());
        }
    }

    /**
     * Delete camera
     */
    public function destroy(Request $request, int $id)
    {
        if (!$this->checkModuleAccess('camera-monitoring')) {
            return $this->moduleAccessDenied();
        }

        try {
            $schoolId = $request->school_id;
            $this->cameraService->deleteCamera($id, $schoolId);

            return $this->successResponse(null, 'Camera deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete camera: ' . $e->getMessage());
        }
    }

    /**
     * Get cameras by room/class
     */
    public function getCamerasByRoom(Request $request, int $roomId)
    {
        if (!$this->checkModuleAccess('camera-monitoring')) {
            return $this->moduleAccessDenied();
        }

        try {
            $schoolId = $request->school_id;
            $cameras = $this->cameraService->getCamerasByRoom($roomId, $schoolId);

            return $this->successResponse($cameras, 'Room cameras retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve room cameras: ' . $e->getMessage());
        }
    }

    /**
     * Update camera privacy level
     */
    public function updatePrivacy(Request $request, int $id)
    {
        if (!$this->checkModuleAccess('camera-monitoring')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'privacy_level' => 'required|in:public,restricted,private,disabled'
            ]);

            $schoolId = $request->school_id;
            $camera = $this->cameraService->updateCameraPrivacy($id, $schoolId, $request->privacy_level);

            return $this->successResponse($camera, 'Camera privacy updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update camera privacy: ' . $e->getMessage());
        }
    }

    /**
     * Get camera analytics
     */
    public function getAnalytics(Request $request, int $id)
    {
        if (!$this->checkModuleAccess('camera-monitoring')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            $schoolId = $request->school_id;
            $filters = $request->only(['start_date', 'end_date']);
            
            $analytics = $this->cameraService->getCameraAnalytics($id, $schoolId, $filters);

            return $this->successResponse($analytics, 'Camera analytics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve analytics: ' . $e->getMessage());
        }
    }

    /**
     * Get camera permissions for management
     */
    public function getPermissions(Request $request)
    {
        if (!$this->checkModuleAccess('camera-monitoring')) {
            return $this->moduleAccessDenied();
        }

        try {
            $schoolId = $request->school_id;
            $filters = $request->only(['status', 'camera_id', 'parent_id']);
            $perPage = min($request->get('per_page', 15), 50);

            $permissions = $this->cameraService->getCameraPermissions($schoolId, $filters, $perPage);

            return $this->successResponse($permissions, 'Camera permissions retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve permissions: ' . $e->getMessage());
        }
    }

    /**
     * Create camera permission (Admin only)
     */
    public function createPermission(Request $request)
    {
        if (!$this->checkModuleAccess('camera-monitoring')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'camera_id' => 'required|integer|exists:school_cameras,id',
                'parent_id' => 'required|integer|exists:users,id',
                'student_id' => 'required|integer|exists:students,id',
                'permission_type' => 'required|in:permanent,temporary,scheduled',
                'justification' => 'nullable|string|max:500',
                'access_start_time' => 'nullable|date|required_if:permission_type,temporary',
                'access_end_time' => 'nullable|date|after:access_start_time|required_if:permission_type,temporary',
                'schedule_settings' => 'nullable|array|required_if:permission_type,scheduled',
                'auto_approve' => 'boolean'
            ]);

            $data = $request->validated();
            $data['school_id'] = $request->school_id;
            $data['created_by_admin'] = true;

            // Create the permission request
            $permission = $this->cameraService->requestCameraAccess($data);

            // If auto_approve is true, automatically approve it
            if ($request->get('auto_approve', false)) {
                $permission = $this->cameraService->approveCameraAccess(
                    $permission->id, 
                    $request->school_id, 
                    Auth::id(),
                    $request->only(['access_start_time', 'access_end_time'])
                );
            }

            return $this->successResponse($permission, 'Camera permission created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create permission: ' . $e->getMessage());
        }
    }

    /**
     * Approve camera access request
     */
    public function approveAccess(Request $request, int $permissionId)
    {
        if (!$this->checkModuleAccess('camera-monitoring')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'access_start_time' => 'nullable|date',
                'access_end_time' => 'nullable|date|after:access_start_time',
            ]);

            $schoolId = $request->school_id;
            $approvedBy = Auth::id();
            $settings = $request->only(['access_start_time', 'access_end_time']);

            $permission = $this->cameraService->approveCameraAccess($permissionId, $schoolId, $approvedBy, $settings);

            return $this->successResponse($permission, 'Camera access approved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to approve access: ' . $e->getMessage());
        }
    }

    /**
     * Reject camera access request
     */
    public function rejectAccess(Request $request, int $permissionId)
    {
        if (!$this->checkModuleAccess('camera-monitoring')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'rejection_reason' => 'nullable|string|max:500',
            ]);

            $schoolId = $request->school_id;
            $rejectedBy = Auth::id();
            $reason = $request->rejection_reason;

            $permission = $this->cameraService->rejectCameraAccess($permissionId, $schoolId, $rejectedBy, $reason);

            return $this->successResponse($permission, 'Camera access rejected successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reject access: ' . $e->getMessage());
        }
    }

    /**
     * Generate secure stream token (for authenticated streaming)
     */
    public function generateStreamToken(Request $request, int $id)
    {
        try {
            $request->validate([
                'student_id' => 'nullable|integer',
                'expires_in' => 'nullable|integer|min:60|max:14400', // 1 min to 4 hours
            ]);

            $parentId = Auth::id();
            $studentId = $request->student_id;
            $expiresIn = $request->get('expires_in', 3600); // Default 1 hour

            $tokenData = $this->cameraService->generateStreamToken($id, $parentId, $studentId, $expiresIn);

            return $this->successResponse($tokenData, 'Stream token generated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate stream token: ' . $e->getMessage());
        }
    }

    /**
     * Validate and serve camera stream (WebRTC signaling endpoint)
     */
    public function streamCamera(Request $request, int $id)
    {
        try {
            $token = $request->get('token');
            
            if (!$token) {
                return $this->errorResponse('Stream token required', null, 401);
            }

            $payload = $this->cameraService->validateStreamToken($token, $id);
            
            if (!$payload) {
                return $this->errorResponse('Invalid or expired stream token', null, 401);
            }

            // Here you would implement WebRTC signaling logic
            // For now, return the validated payload for client-side handling
            return $this->successResponse([
                'validated' => true,
                'camera_id' => $id,
                'expires_at' => $payload['expires_at'],
                'webrtc_config' => [
                    'iceServers' => [
                        ['urls' => 'stun:stun.l.google.com:19302'],
                        // Add TURN servers for production
                    ]
                ]
            ], 'Stream access validated');

        } catch (\Exception $e) {
            return $this->errorResponse('Stream access failed: ' . $e->getMessage(), null, 403);
        }
    }

    /**
     * End camera access session
     */
    public function endSession(Request $request)
    {
        try {
            $request->validate([
                'access_log_id' => 'required|integer',
            ]);

            $accessLog = $this->cameraService->endAccessSession($request->access_log_id);

            return $this->successResponse($accessLog, 'Camera session ended successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to end session: ' . $e->getMessage());
        }
    }

    /**
     * Public stream access with token validation
     * This endpoint handles the secure_stream_url generated by the model
     */
    public function publicStreamAccess(Request $request, int $id)
    {
        try {
            $token = $request->get('token');
            
            if (!$token) {
                return $this->errorResponse('Stream token required', null, 401);
            }

            // Validate the token using the camera service
            $payload = $this->cameraService->validateStreamToken($token, $id);
            
            if (!$payload) {
                return $this->errorResponse('Invalid or expired stream token', null, 401);
            }

            // Get camera with stream details
            $camera = SchoolCamera::with(['room', 'school'])->find($id);
            
            if (!$camera) {
                return $this->errorResponse('Camera not found', null, 404);
            }

            // Return stream viewer page or redirect to actual stream URL
            // For now, return a simple HTML page with stream player
            return $this->generateStreamViewer($camera, $payload);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to access camera stream: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Generate a simple HTML stream viewer page
     */
    private function generateStreamViewer($camera, $payload)
    {
        $html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camera Stream - ' . htmlspecialchars($camera->camera_name) . '</title>
    <style>
        body { 
            margin: 0; 
            padding: 20px; 
            font-family: Arial, sans-serif; 
            background: #f5f5f5; 
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 8px; 
            padding: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e5e5;
            padding-bottom: 15px;
        }
        .camera-info { 
            margin-bottom: 20px; 
        }
        .stream-container { 
            width: 100%; 
            height: 500px; 
            background: #000; 
            border-radius: 8px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: white; 
        }
        .status { 
            padding: 10px 20px; 
            border-radius: 5px; 
            margin-top: 15px; 
        }
        .status.success { 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb; 
        }
        .status.info { 
            background: #d1ecf1; 
            color: #0c5460; 
            border: 1px solid #bee5eb; 
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Live Camera Stream</h1>
            <h2>' . htmlspecialchars($camera->camera_name) . '</h2>
        </div>
        
        <div class="camera-info">
            <p><strong>Location:</strong> ' . htmlspecialchars($camera->location_description) . '</p>
            <p><strong>Room:</strong> ' . htmlspecialchars($camera->room->class_name ?? 'N/A') . '</p>
            <p><strong>Status:</strong> ' . ucfirst($camera->status) . '</p>
            <p><strong>Stream Token Valid Until:</strong> ' . date('Y-m-d H:i:s', $payload['expires_at']) . '</p>
        </div>

        <div class="stream-container">
            <div style="text-align: center;">
                <h3>🎥 Live Stream</h3>
                <p>Camera stream will appear here</p>
                <p><em>Stream URL: ' . htmlspecialchars($camera->stream_url ?? 'Not configured') . '</em></p>
            </div>
        </div>

        <div class="status success">
            ✅ Stream token validated successfully
        </div>

        <div class="status info">
            ℹ️ This is a demonstration page. In production, this would show the actual camera feed.
        </div>
    </div>

    <script>
        // Here you would implement actual stream viewing logic
        // For WebRTC streams, HLS streams, or RTMP streams
        console.log("Camera Stream Access - Token Valid");
        console.log("Camera ID:", ' . $camera->id . ');
        console.log("Expires at:", new Date(' . $payload['expires_at'] . ' * 1000));
    </script>
</body>
</html>';

        return response($html)->header('Content-Type', 'text/html');
    }
}