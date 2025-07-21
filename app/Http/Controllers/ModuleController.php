<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Http\Resources\ModuleResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ModuleController extends BaseController
{
    public function index(): JsonResponse
    {
        try {
            $modules = Module::where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            return $this->successResponse(
                ModuleResource::collection($modules),
                'Modules retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $module = Module::findOrFail($id);

            return $this->successResponse(
                new ModuleResource($module),
                'Module retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }
    }

    public function getSchoolModules(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->schoolAdmin) {
                return $this->errorResponse('Unauthorized access', null, 401);
            }

            $school = $user->schoolAdmin->school;
            $schoolModules = $school->modules()
                ->withPivot(['activated_at', 'expires_at', 'status', 'settings'])
                ->get();

            return $this->successResponse(
                ModuleResource::collection($schoolModules),
                'School modules retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function activateModule(Request $request, $moduleId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->schoolAdmin) {
                return $this->errorResponse('Unauthorized access', null, 401);
            }

            $request->validate([
                'subscription_type' => 'required|in:monthly,yearly',
                'expires_at' => 'nullable|date|after:today',
            ]);

            $module = Module::findOrFail($moduleId);
            $school = $user->schoolAdmin->school;

            // Check if module is already activated
            $existingSubscription = $school->modules()->where('module_id', $moduleId)->first();

            if ($existingSubscription && $existingSubscription->pivot->status === 'active') {
                return $this->errorResponse('Module is already activated for this school');
            }

            // Calculate expiry date based on subscription type
            $expiresAt = $request->expires_at ?? now()->addMonths($request->subscription_type === 'monthly' ? 1 : 12);

            // Activate or update module subscription
            $school->modules()->syncWithoutDetaching([
                $moduleId => [
                    'activated_at' => now(),
                    'expires_at' => $expiresAt,
                    'status' => 'active',
                    'settings' => json_encode($request->settings ?? []),
                ]
            ]);

            return $this->successResponse(
                null,
                'Module activated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function deactivateModule($moduleId): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->schoolAdmin) {
                return $this->errorResponse('Unauthorized access', null, 401);
            }

            $school = $user->schoolAdmin->school;

            // Update module status to inactive
            $school->modules()->updateExistingPivot($moduleId, [
                'status' => 'inactive',
                'updated_at' => now(),
            ]);

            return $this->successResponse(
                null,
                'Module deactivated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function getModulePricing(): JsonResponse
    {
        try {
            $modules = Module::where('is_active', true)
                ->select(['id', 'name', 'slug', 'description', 'monthly_price', 'yearly_price', 'features'])
                ->orderBy('sort_order')
                ->get();

            return $this->successResponse(
                $modules,
                'Module pricing retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
