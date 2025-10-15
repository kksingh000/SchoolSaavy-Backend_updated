<?php

namespace App\Http\Middleware;

use App\Models\AcademicYear;
use Closure;
use Illuminate\Http\Request;

class CheckAcademicYear
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Only process for school_admin users
        if ($request->user() && $request->user()->user_type === 'school_admin') {
            // Get the school ID from the request (injected by inject.school middleware)
            $schoolId = $request->school_id;
            
            // Check if any academic years exist for this school
            $hasAcademicYears = AcademicYear::where('school_id', $schoolId)->exists();
            
            // Add a flag to the request that can be used in the response
            $request->merge(['school_has_academic_years' => $hasAcademicYears]);
        }

        return $next($request);
    }
}