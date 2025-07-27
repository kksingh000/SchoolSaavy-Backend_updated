<?php

namespace App\Http\Controllers;

use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BaseController extends Controller
{
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

    protected function successResponse($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
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
