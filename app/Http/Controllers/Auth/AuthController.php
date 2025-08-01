<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());
            
            return response()->json([
                'message' => 'Login successful',
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                'expires_at' => $result['expires_at']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout($request->user());
            
            return response()->json([
                'message' => 'Successfully logged out'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'user' => new UserResource($request->user()->load([
                    'schoolAdmin.school',
                    'teacher.school',
                    'parent.students'
                ]))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch user details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function refresh(Request $request): JsonResponse
    {
        try {
            // Extract token from Authorization header
            $token = $request->bearerToken();
            
            if (!$token) {
                return response()->json([
                    'message' => 'Token not provided',
                    'error' => 'Authorization header missing'
                ], 401);
            }
            
            $result = $this->authService->refreshToken($token);
            
            return response()->json([
                'message' => 'Token refreshed successfully',
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                'expires_at' => $result['expires_at']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Token refresh failed',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    public function checkToken(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $token = $user->currentAccessToken();
            
            // Calculate expiration based on token creation time and configured expiration
            $expirationMinutes = (int) config('sanctum.expiration', 1440);
            $tokenCreatedAt = $token->created_at;
            $tokenExpiresAt = $tokenCreatedAt->copy()->addMinutes($expirationMinutes);
            $now = now();
            
            // Check if token is expired
            $isExpired = $now->greaterThan($tokenExpiresAt);
            $minutesUntilExpiry = $isExpired ? 0 : $now->diffInMinutes($tokenExpiresAt);
            
            return response()->json([
                'valid' => !$isExpired,
                'user' => new UserResource($user),
                'token_created_at' => $tokenCreatedAt->toISOString(),
                'token_expires_at' => $tokenExpiresAt->toISOString(),
                'expires_in_minutes' => $minutesUntilExpiry,
                'is_expired' => $isExpired,
                'current_time' => $now->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid token',
                'error' => $e->getMessage()
            ], 401);
        }
    }
} 