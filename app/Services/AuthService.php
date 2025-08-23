<?php

namespace App\Services;

use App\Models\User;
use App\Services\ParentService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    protected ParentService $parentService;

    public function __construct(ParentService $parentService)
    {
        $this->parentService = $parentService;
    }
    public function login(array $credentials)
    {
        $user = User::where('email', $credentials['email'])
            ->where('user_type', $credentials['user_type'])
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account is inactive. Please contact the administrator.'],
            ]);
        }

        // Check if user's school is active (except for super_admin)
        if ($user->user_type !== 'super_admin') {
            $school = $this->getUserSchool($user);
            if ($school && !$school->is_active) {
                throw ValidationException::withMessages([
                    'school' => ['Your school has been deactivated. Please contact the administration for assistance.'],
                ]);
            }
        }

        // For parent users, get detailed student information efficiently
        $additionalData = [];
        if ($user->user_type === 'parent') {
            // Load only the parent relationship to get parent ID
            $user->load('parent');
            if ($user->parent) {
                $additionalData['students'] = $this->parentService->getParentChildren($user->parent->id);
            }
        } else {
            // Load the specific user type relationship for non-parent users
            $relation = $this->getUserRelation($user->user_type);
            if ($relation) {
                $user->load($relation);
            }
        }

        // Add school context for non-super-admin users
        if ($user->user_type !== 'super_admin') {
            $school = $this->getUserSchool($user);
            if ($school) {
                $additionalData['school'] = [
                    'id' => $school->id,
                    'name' => $school->name,
                    'email' => $school->email,
                    'subscription_plan' => $school->subscription_plan,
                    'is_active' => $school->is_active
                ];
            }
        }

        return [
            'user' => $user,
            'token' => $user->createToken('auth-token')->plainTextToken,
            'expires_at' => now()->addMinutes((int) config('sanctum.expiration', 1440))->toISOString(),
            ...$additionalData
        ];
    }

    protected function getUserRelation(string $userType)
    {
        return match ($userType) {
            'school_admin' => 'schoolAdmin.school',
            'super_admin' => 'superAdmin',
            'teacher' => 'teacher.school',
            'parent' => 'parent', // Load only parent profile, not students (we'll add detailed students separately)
            default => null,
        };
    }

    /**
     * Get the school associated with the user
     */
    protected function getUserSchool($user)
    {
        return match ($user->user_type) {
            'school_admin' => $user->schoolAdmin?->school,
            'teacher' => $user->teacher?->school,
            'parent' => $user->parent?->students?->first()?->school,
            'student' => $user->student?->school ?? null,
            default => null,
        };
    }

    public function logout($user)
    {
        if ($user) {
            $user->currentAccessToken()->delete();
        }
    }

    public function refreshToken($tokenString)
    {
        if (!$tokenString) {
            throw ValidationException::withMessages([
                'token' => ['Token not provided.'],
            ]);
        }

        // Parse the token to get the ID and token
        $tokenParts = explode('|', $tokenString);
        if (count($tokenParts) !== 2) {
            throw ValidationException::withMessages([
                'token' => ['Invalid token format.'],
            ]);
        }

        $tokenId = $tokenParts[0];
        $tokenValue = $tokenParts[1];

        // Find the token in the database
        $tokenModel = \Laravel\Sanctum\PersonalAccessToken::find($tokenId);

        if (!$tokenModel) {
            throw ValidationException::withMessages([
                'token' => ['Token not found or expired.'],
            ]);
        }

        // Verify the token value
        if (!hash_equals($tokenModel->token, hash('sha256', $tokenValue))) {
            throw ValidationException::withMessages([
                'token' => ['Invalid token.'],
            ]);
        }

        // Get the user from the token
        $user = $tokenModel->tokenable;

        if (!$user) {
            throw ValidationException::withMessages([
                'token' => ['User not found for this token.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'user' => ['Your account is inactive. Please contact the administrator.'],
            ]);
        }

        // Check if user's school is active (except for super_admin)
        if ($user->user_type !== 'super_admin') {
            $school = $this->getUserSchool($user);
            if ($school && !$school->is_active) {
                throw ValidationException::withMessages([
                    'school' => ['Your school has been deactivated. Please contact the administration for assistance.'],
                ]);
            }
        }

        // Create a new token with expiration FIRST
        $tokenResult = $user->createToken('auth-token');
        $token = $tokenResult->plainTextToken; // This is the actual token string

        // Only delete the old token AFTER successfully creating the new one
        $tokenModel->delete();

        // For parent users, get detailed student information efficiently
        $additionalData = [];
        if ($user->user_type === 'parent') {
            // Load only the parent relationship to get parent ID
            $user->load('parent');
            if ($user->parent) {
                $additionalData['students'] = $this->parentService->getParentChildren($user->parent->id);
            }
        } else {
            // Load the specific user type relationship for non-parent users
            $relation = $this->getUserRelation($user->user_type);
            if ($relation) {
                $user->load($relation);
            }
        }

        return [
            'user' => $user,
            'access_token' => $token, // Use 'access_token' as standard naming
            'token_type' => 'Bearer',
            'expires_in' => (int) config('sanctum.expiration', 1440) * 60, // Convert minutes to seconds
            'expires_at' => now()->addMinutes((int) config('sanctum.expiration', 1440))->toISOString(),
            ...$additionalData
        ];
    }
}
