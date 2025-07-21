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
            'token' => $user->createToken('auth-token')->plainTextToken
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
}
