<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\AcademicYear;
use App\Traits\CacheInvalidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    use CacheInvalidation;
    protected function checkModuleAccess(string $moduleSlug): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Get the school for any user type (admin, teacher, parent)
        $school = $user->getSchool();

        if (!$school) {
            return false;
        }

        return $school->modules()
            ->where('slug', $moduleSlug)
            ->wherePivot('status', 'active')
            ->exists();
    }

    protected function moduleAccessDenied(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Module not activated for your school. Please contact administration.',
            'code' => 'MODULE_ACCESS_DENIED'
        ], 403);
    }

    /**
     * Get current academic year ID from request (injected by middleware)
     */
    protected function getCurrentAcademicYearId(Request $request = null): ?int
    {
        $request = $request ?? request();
        return $request->input('academic_year_id');
    }

    /**
     * Get current academic year label from request (injected by middleware)
     */
    protected function getCurrentAcademicYear(Request $request = null): ?string
    {
        $request = $request ?? request();
        return $request->input('current_academic_year');
    }

    /**
     * Get current academic year model from request (injected by middleware)
     */
    protected function getCurrentAcademicYearModel(Request $request = null): ?AcademicYear
    {
        $request = $request ?? request();
        return $request->attributes->get('currentAcademicYearModel');
    }

    /**
     * Get school ID from request (injected by middleware)
     */
    protected function getSchoolId(Request $request = null): ?int
    {
        $request = $request ?? request();
        return $request->input('school_id');
    }

    protected function successResponse($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }
    
    /**
     * Return a paginated success response with meta data
     *
     * @param \Illuminate\Pagination\LengthAwarePaginator $paginator
     * @param mixed $resourceCollection
     * @param string $message
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function paginatedSuccessResponse($paginator, $resourceCollection, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $resourceCollection->response()->getData()->data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ], $code);
    }

    protected function errorResponse(string $message = 'Error', $errors = null, int $code = 500): JsonResponse
    {
        $response = [
            'status' => 'error',
            'message' => $message
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}
