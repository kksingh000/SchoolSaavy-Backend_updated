<?php

namespace App\Http\Controllers;

use App\Services\CameraService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ParentCameraController - Handles camera access for parents
 * 
 * Provides API endpoints for parent mobile app to view live cameras
 * and manage camera access permissions
 */
class ParentCameraController extends BaseController
{
    protected CameraService $cameraService;

    public function __construct(CameraService $cameraService)
    {
        $this->cameraService = $cameraService;
    }

    /**
     * Get cameras accessible by parent (class-based access)
     */
    public function getAccessibleCameras(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'nullable|integer|exists:students,id',
            ]);

            $parentId = Auth::id();
            // Get school ID from authenticated parent user
            $parentUser = Auth::user();
            if ($parentUser->user_type !== 'parent' || !$parentUser->parent) {
                return $this->errorResponse('Invalid parent user', null, 403);
            }
            
            $firstChild = $parentUser->parent->students()->first();
            if (!$firstChild) {
                return $this->errorResponse('No children found for this parent', null, 404);
            }
            
            $schoolId = $firstChild->school_id;
            
            // Check if camera module is enabled
            $this->checkCameraModuleAccess($schoolId);
            
            $studentId = $request->student_id; // Optional filter by specific child

            $cameras = $this->cameraService->getAccessibleCamerasForParent($parentId, $schoolId, $studentId);

            return $this->successResponse($cameras, 'Class cameras retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve cameras: ' . $e->getMessage());
        }
    }

    /**
     * Check camera module access for school
     */
    private function checkCameraModuleAccess(int $schoolId)
    {
        // Check if camera-monitoring module is enabled for this school
        $school = \App\Models\School::find($schoolId);
        
        if (!$school) {
            throw new \Exception('School not found');
        }

        $hasModule = $school->modules()
            ->where('slug', 'camera-monitoring')
            ->wherePivot('status', 'active')
            ->exists();

        if (!$hasModule) {
            throw new \Exception('Camera monitoring module is not enabled for your school');
        }

        return true;
    }

    /**
     * Get live stream token for camera (class-based access)
     */
    public function getStreamToken(Request $request, int $cameraId)
    {
        try {
            $request->validate([
                'student_id' => 'nullable|integer|exists:students,id',
                'expires_in' => 'nullable|integer|min:60|max:14400', // 1 min to 4 hours
            ]);

            $parentId = Auth::id();
            
            // Get school ID from authenticated parent user
            $parentUser = Auth::user();
            if ($parentUser->user_type !== 'parent' || !$parentUser->parent) {
                return $this->errorResponse('Invalid parent user', null, 403);
            }
            
            $firstChild = $parentUser->parent->students()->first();
            if (!$firstChild) {
                return $this->errorResponse('No children found for this parent', null, 404);
            }
            
            $schoolId = $firstChild->school_id;
            
            // Check if camera module is enabled
            $this->checkCameraModuleAccess($schoolId);
            
            $studentId = $request->student_id;
            $expiresIn = $request->get('expires_in', 3600); // Default 1 hour

            // Check if parent can access this camera based on class
            if (!$this->cameraService->canParentAccessCamera($parentId, $cameraId, $schoolId, $studentId)) {
                return $this->errorResponse('Access denied. You can only view cameras in your child\'s classroom.', null, 403);
            }

            $tokenData = $this->cameraService->generateStreamToken($cameraId, $parentId, $studentId, $expiresIn);

            return $this->successResponse($tokenData, 'Stream token generated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate stream token: ' . $e->getMessage(), null, 403);
        }
    }

    /**
     * Get parent's children classes and camera access info
     */
    public function getClassroomAccess(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'nullable|integer|exists:students,id',
            ]);

            $parentId = Auth::id();
            $studentId = $request->student_id;

            $parent = Auth::user();
            if (!$parent || $parent->user_type !== 'parent' || !$parent->parent) {
                return $this->errorResponse('Invalid parent user', null, 403);
            }
            
            // Get school ID from parent's first child
            $firstChild = $parent->parent->students()->first();
            if (!$firstChild) {
                return $this->errorResponse('No children found for this parent', null, 404);
            }
            
            $schoolId = $firstChild->school_id;
            
            // Check if camera module is enabled
            $this->checkCameraModuleAccess($schoolId);

            $childrenQuery = $parent->parent->students()->where('school_id', $schoolId);
            if ($studentId) {
                $childrenQuery->where('id', $studentId);
            }

            $children = $childrenQuery->with(['currentClass.cameras' => function($query) {
                $query->active()->whereIn('privacy_level', ['public', 'restricted']);
            }])->get();

            $classroomAccess = $children->map(function($student) {
                $currentClass = $student->currentClass->first(); // Get the first (and should be only) current class
                return [
                    'student' => [
                        'id' => $student->id,
                        'name' => $student->name,
                        'admission_number' => $student->admission_number,
                    ],
                    'class' => [
                        'id' => $currentClass->id ?? null,
                        'name' => $currentClass->name ?? null,
                        'section' => $currentClass->section ?? null,
                    ],
                    'available_cameras' => $currentClass->cameras ?? [],
                    'access_granted' => true, // Class-based access is automatic
                ];
            });

            return $this->successResponse($classroomAccess, 'Classroom camera access retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve classroom access: ' . $e->getMessage());
        }
    }

    /**
     * End current camera session
     */
    public function endSession(Request $request)
    {
        try {
            $request->validate([
                'access_log_id' => 'required|integer',
            ]);

            $parentId = Auth::id();
            
            // Verify the access log belongs to the parent
            $accessLog = \App\Models\CameraAccessLog::where('id', $request->access_log_id)
                ->where('parent_id', $parentId)
                ->whereNull('access_end_time')
                ->first();

            if (!$accessLog) {
                return $this->errorResponse('Active session not found', null, 404);
            }

            $accessLog = $this->cameraService->endAccessSession($request->access_log_id);

            return $this->successResponse($accessLog, 'Camera session ended successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to end session: ' . $e->getMessage());
        }
    }
}