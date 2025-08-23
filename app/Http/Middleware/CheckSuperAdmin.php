<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSuperAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required.',
            ], 401);
        }

        $user = Auth::user();

        // Check if user is a super admin
        if ($user->user_type !== 'super_admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied. Super admin privileges required.',
            ], 403);
        }

        // Check if super admin profile exists
        if (!$user->superAdmin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Super admin profile not found.',
            ], 403);
        }

        return $next($request);
    }
}
