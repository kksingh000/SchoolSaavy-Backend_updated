<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\School;

class CheckSchoolStatus
{
    /**
     * Handle an incoming request.
     * Check if the user's school is active before allowing access
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip check for super admin as they don't belong to any school
        if (Auth::check() && Auth::user()->user_type === 'super_admin') {
            return $next($request);
        }

        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required.',
            ], 401);
        }

        $user = Auth::user();
        $school = null;

        // Get school based on user type
        try {
            switch ($user->user_type) {
                case 'school_admin':
                    $school = $user->schoolAdmin?->school;
                    break;

                case 'teacher':
                    $school = $user->teacher?->school;
                    break;

                case 'parent':
                    // Get school from first student
                    $school = $user->parent?->students?->first()?->school;
                    break;

                case 'student':
                    $school = $user->student?->school ?? null;
                    break;
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to verify school association.',
            ], 500);
        }

        // Check if school exists
        if (!$school) {
            return response()->json([
                'status' => 'error',
                'message' => 'No school associated with your account. Please contact support.',
            ], 403);
        }

        // Check if school is active
        if (!$school->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your school has been deactivated. Please contact the administration for assistance.',
                'error_code' => 'SCHOOL_DEACTIVATED'
            ], 403);
        }

        return $next($request);
    }
}
