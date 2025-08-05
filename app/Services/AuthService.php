<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
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

        // Load the specific user type relationship
        $relation = $this->getUserRelation($user->user_type);
        if ($relation) {
            $user->load($relation);
        }

        return [
            'user' => $user,
            'token' => $user->createToken('auth-token')->plainTextToken,
            'expires_at' => now()->addMinutes((int) config('sanctum.expiration', 1440))->toISOString()
        ];
    }

    protected function getUserRelation(string $userType)
    {
        return match ($userType) {
            'admin', 'school_admin' => 'schoolAdmin.school',
            'teacher' => 'teacher.school',
            'parent' => 'parent.students',
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

        // Load the specific user type relationship
        $relation = $this->getUserRelation($user->user_type);
        if ($relation) {
            $user->load($relation);
        }

        // Create a new token with expiration FIRST
        $tokenResult = $user->createToken('auth-token');
        $token = $tokenResult->plainTextToken; // This is the actual token string

        // Only delete the old token AFTER successfully creating the new one
        $tokenModel->delete();

        return [
            'user' => $user,
            'access_token' => $token, // Use 'access_token' as standard naming
            'token_type' => 'Bearer',
            'expires_in' => (int) config('sanctum.expiration', 1440) * 60, // Convert minutes to seconds
            'expires_at' => now()->addMinutes((int) config('sanctum.expiration', 1440))->toISOString()
        ];
    }
}
