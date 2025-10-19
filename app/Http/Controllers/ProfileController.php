<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\UserResource;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\ClassRoom;
use App\Models\Parents;
use Illuminate\Http\JsonResponse;

class ProfileController extends BaseController
{
    /**
     * Get current user profile with school statistics
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Load appropriate relationships based on user type
            $relationships = match ($user->user_type) {
                'super_admin' => ['superAdmin'],
                'school_admin' => ['schoolAdmin.school'],
                'teacher' => ['teacher.school'],
                'parent' => ['parent.students'],
                'student' => ['student.school'],
                default => []
            };

            if (!empty($relationships)) {
                $user->load($relationships);
            }

            // Get school statistics
            $statistics = null;
            $school = $user->getSchool();
            
            if ($school) {
                // Count parents through student relationships
                $parentCount = Parents::whereHas('students', function($query) use ($school) {
                    $query->where('school_id', $school->id);
                })->distinct()->count('id');
                
                $statistics = [
                    'student_count' => Student::where('school_id', $school->id)->count(),
                    'teacher_count' => Teacher::where('school_id', $school->id)->count(),
                    'class_count' => ClassRoom::where('school_id', $school->id)->count(),
                    'parent_count' => $parentCount,
                ];
            }

            return $this->successResponse([
                'user' => new UserResource($user),
                'statistics' => $statistics,
            ], 'Profile retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Update user profile
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|max:20|nullable',
                'profile_photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
            ]);
            
            // Update user name
            if (isset($validated['name'])) {
                $user->name = $validated['name'];
                $user->save();
            }
            
            // Update profile-specific fields
            $profile = match ($user->user_type) {
                'super_admin' => $user->superAdmin,
                'school_admin' => $user->schoolAdmin,
                'teacher' => $user->teacher,
                'parent' => $user->parent,
                'student' => $user->student,
                default => null,
            };
            
            if ($profile) {
                // Update phone
                if (isset($validated['phone'])) {
                    $profile->phone = $validated['phone'];
                }
                
                // Handle profile photo upload
                if ($request->hasFile('profile_photo')) {
                    // Delete old photo if exists
                    if ($profile->profile_photo && Storage::disk('public')->exists($profile->profile_photo)) {
                        Storage::disk('public')->delete($profile->profile_photo);
                    }
                    
                    $path = $request->file('profile_photo')->store('profiles', 'public');
                    $profile->profile_photo = $path;
                }
                
                $profile->save();
            }

            // Reload relationships
            $relationships = match ($user->user_type) {
                'super_admin' => ['superAdmin'],
                'school_admin' => ['schoolAdmin.school'],
                'teacher' => ['teacher.school'],
                'parent' => ['parent.students'],
                'student' => ['student.school'],
                default => []
            };

            if (!empty($relationships)) {
                $user->load($relationships);
            }
            
            return $this->successResponse(
                new UserResource($user->fresh()),
                'Profile updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
    
    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);
            
            $user = $request->user();
            
            // Verify current password
            if (!Hash::check($validated['current_password'], $user->password)) {
                return $this->errorResponse(
                    'Current password is incorrect',
                    ['current_password' => ['The provided password is incorrect']],
                    401
                );
            }
            
            // Update password
            $user->password = Hash::make($validated['new_password']);
            $user->save();
            
            // Log the password change activity (if activity log package is installed)
            // activity()
            //     ->causedBy($user)
            //     ->performedOn($user)
            //     ->event('password_changed')
            //     ->log('Password changed successfully');
            
            return $this->successResponse(
                null,
                'Password changed successfully'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
    
    /**
     * Get school statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $school = $user->getSchool();
            
            if (!$school) {
                return $this->errorResponse('School not found', null, 404);
            }
            
            // Count parents through student relationships
            $parentCount = Parents::whereHas('students', function($query) use ($school) {
                $query->where('school_id', $school->id);
            })->distinct()->count('id');
            
            $statistics = [
                'student_count' => Student::where('school_id', $school->id)->count(),
                'teacher_count' => Teacher::where('school_id', $school->id)->count(),
                'class_count' => ClassRoom::where('school_id', $school->id)->count(),
                'parent_count' => $parentCount,
            ];
            
            return $this->successResponse(
                $statistics,
                'Statistics retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Upload profile photo separately
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'profile_photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $user = $request->user();
            $profile = match ($user->user_type) {
                'super_admin' => $user->superAdmin,
                'school_admin' => $user->schoolAdmin,
                'teacher' => $user->teacher,
                'parent' => $user->parent,
                'student' => $user->student,
                default => null,
            };

            if (!$profile) {
                return $this->errorResponse('Profile not found', null, 404);
            }

            // Delete old photo if exists
            if ($profile->profile_photo && Storage::disk('public')->exists($profile->profile_photo)) {
                Storage::disk('public')->delete($profile->profile_photo);
            }

            // Store new photo
            $path = $request->file('profile_photo')->store('profiles', 'public');
            $profile->profile_photo = $path;
            $profile->save();

            return $this->successResponse([
                'profile_photo' => $path,
                'profile_photo_url' => asset('storage/' . $path),
            ], 'Profile photo uploaded successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}

