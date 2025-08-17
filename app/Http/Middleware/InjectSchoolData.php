<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\AcademicYear;

class InjectSchoolData
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Get school_id based on user type
            $schoolId = match ($user->user_type) {
                'admin', 'school_admin' => $user->schoolAdmin->school_id ?? null,
                'teacher' => $user->teacher->school_id ?? null,
                'parent' => $user->parent->students->first()->school_id ?? null,
                default => null
            };

            // Get current academic year for the school
            $currentAcademicYear = null;
            $currentAcademicYearId = null;

            if ($schoolId) {
                $currentAcademicYear = AcademicYear::forSchool($schoolId)
                    ->current()
                    ->active()
                    ->first();

                $currentAcademicYearId = $currentAcademicYear?->id;
            }

            // Merge school_id, academic year, and created_by into request
            $request->merge([
                'school_id' => $schoolId,
                'academic_year_id' => $currentAcademicYearId,
                'current_academic_year' => $currentAcademicYear?->year_label,
                'created_by' => $user->id
            ]);

            // Store current academic year in request for easy access
            if ($currentAcademicYear) {
                $request->attributes->set('currentAcademicYearModel', $currentAcademicYear);
            }
        }
        return $next($request);
    }
}
