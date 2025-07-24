<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectSchoolData
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();

            // Get school_id based on user type
            $schoolId = match ($user->user_type) {
                'admin', 'school_admin' => $user->schoolAdmin->school_id ?? null,
                'teacher' => $user->teacher->school_id ?? null,
                'parent' => $user->parent->students->first()->school_id ?? null,
                default => null
            };

            // Merge school_id and created_by into request
            $request->merge([
                'school_id' => $schoolId,
                'created_by' => $user->id
            ]);
        }
        return $next($request);
    }
}
