<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseController;
use App\Services\SuperAdmin\SchoolManagementService;
use App\Services\SuperAdmin\SchoolModuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SchoolController extends BaseController
{
    protected SchoolManagementService $schoolService;
    protected SchoolModuleService $moduleService;

    public function __construct(SchoolManagementService $schoolService, SchoolModuleService $moduleService)
    {
        $this->schoolService = $schoolService;
        $this->moduleService = $moduleService;
    }

    /**
     * Get all schools with pagination and optional filtering
     * 
     * Query Parameters:
     * - status: active|inactive (filter by school status)
     * - search: string (search in school name, code, email)
     * - created_from: date (filter schools created from this date)
     * - created_to: date (filter schools created to this date)
     * - per_page: integer (1-100, default: 15)
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:active,inactive',
            'search' => 'nullable|string|max:255',
            'created_from' => 'nullable|date',
            'created_to' => 'nullable|date|after_or_equal:created_from',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $filters = $request->only(['status', 'search', 'created_from', 'created_to']);
        $perPage = $request->get('per_page', 15);

        // If no filters are provided, use getAllSchools for better performance
        if (empty(array_filter($filters))) {
            $schools = $this->schoolService->getAllSchools($perPage);
        } else {
            $schools = $this->schoolService->getFilteredSchools($filters, $perPage);
        }

        return $this->successResponse($schools, 'Schools retrieved successfully');
    }

    /**
     * Create a new school with admin user
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // School data
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:schools,code',
            'address' => 'required|string',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|unique:schools,email',
            'website' => 'nullable|url',
            'logo' => 'nullable|string|max:500',

            // Admin user data
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8',
            'admin_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $schoolData = $request->only(['name', 'code', 'address', 'phone', 'email', 'website', 'logo']);
            $schoolData['is_active'] = true;

            $adminData = [
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => $request->admin_password,
                'phone' => $request->admin_phone,
            ];

            $school = $this->schoolService->createSchoolWithAdmin($schoolData, $adminData);

            return $this->successResponse($school, 'School and admin created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create school: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get school details
     */
    public function show($schoolId)
    {
        try {
            $school = $this->schoolService->getSchoolDetails($schoolId);
            return $this->successResponse($school, 'School details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('School not found', null, 404);
        }
    }

    /**
     * Update school details
     */
    public function update(Request $request, $schoolId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50|unique:schools,code,' . $schoolId,
            'address' => 'sometimes|required|string',
            'phone' => 'sometimes|required|string|max:20',
            'email' => 'sometimes|required|email|unique:schools,email,' . $schoolId,
            'website' => 'nullable|url',
            'logo' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $data = $request->only(['name', 'code', 'address', 'phone', 'email', 'website', 'logo']);
            $school = $this->schoolService->updateSchool($schoolId, $data);

            return $this->successResponse($school, 'School updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update school: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Toggle school active status
     */
    public function toggleStatus($schoolId)
    {
        try {
            $school = $this->schoolService->toggleSchoolStatus($schoolId);
            return $this->successResponse($school, 'School status updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update school status', null, 500);
        }
    }

    /**
     * Delete school (soft delete)
     */
    public function destroy($schoolId)
    {
        try {
            $this->schoolService->deleteSchool($schoolId);
            return $this->successResponse(null, 'School deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete school: ' . $e->getMessage(), null, 500);
        }
    }

    // ===== SCHOOL MODULE MANAGEMENT METHODS =====

    /**
     * Get all available modules for assignment
     */
    public function getAvailableModules()
    {
        try {
            $modules = $this->moduleService->getAllModules();
            return $this->successResponse($modules, 'Available modules retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve modules', null, 500);
        }
    }

    /**
     * Get school's assigned modules
     */
    public function getSchoolModules($schoolId)
    {
        try {
            $modules = $this->moduleService->getSchoolModules($schoolId);
            return $this->successResponse($modules, 'School modules retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('School not found or failed to retrieve modules', null, 404);
        }
    }

    /**
     * Assign modules to school
     */
    public function assignModules(Request $request, $schoolId)
    {
        $validator = Validator::make($request->all(), [
            'module_ids' => 'required|array|min:1',
            'module_ids.*' => 'required|integer|exists:modules,id',
            'settings' => 'nullable|array',
            'settings.*.expires_at' => 'nullable|date|after:today',
            'settings.*.status' => 'nullable|in:active,inactive,trial',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $moduleIds = $request->module_ids;
            $settings = $request->settings ?? [];

            $assignedModules = $this->moduleService->assignModulesToSchool($schoolId, $moduleIds, $settings);

            return $this->successResponse([
                'assigned_modules' => $assignedModules,
                'total_assigned' => count($assignedModules)
            ], 'Modules assigned to school successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to assign modules: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove modules from school
     */
    public function removeModules(Request $request, $schoolId)
    {
        $validator = Validator::make($request->all(), [
            'module_ids' => 'required|array|min:1',
            'module_ids.*' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $moduleIds = $request->module_ids;
            $removedModules = $this->moduleService->removeModulesFromSchool($schoolId, $moduleIds);

            return $this->successResponse([
                'removed_modules' => $removedModules,
                'total_removed' => count($removedModules)
            ], 'Modules removed from school successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to remove modules: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update school module settings
     */
    public function updateModuleSettings(Request $request, $schoolId, $moduleId)
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.expires_at' => 'nullable|date|after:today',
            'settings.status' => 'nullable|in:active,inactive,expired,trial',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $updatedModule = $this->moduleService->updateSchoolModuleSettings(
                $schoolId,
                $moduleId,
                $request->settings
            );

            return $this->successResponse($updatedModule, 'Module settings updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update module settings: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Toggle school module status (active/inactive)
     */
    public function toggleModuleStatus($schoolId, $moduleId)
    {
        try {
            $module = $this->moduleService->toggleSchoolModuleStatus($schoolId, $moduleId);
            return $this->successResponse($module, 'Module status toggled successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to toggle module status: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get school module analytics
     */
    public function getSchoolModuleAnalytics($schoolId)
    {
        try {
            $analytics = $this->moduleService->getSchoolModuleAnalytics($schoolId);
            return $this->successResponse($analytics, 'School module analytics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve analytics', null, 500);
        }
    }

    /**
     * Bulk assign modules to multiple schools
     */
    public function bulkAssignModules(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_ids' => 'required|array|min:1',
            'school_ids.*' => 'required|integer|exists:schools,id',
            'module_ids' => 'required|array|min:1',
            'module_ids.*' => 'required|integer|exists:modules,id',
            'global_settings' => 'nullable|array',
            'global_settings.expires_at' => 'nullable|date|after:today',
            'global_settings.status' => 'nullable|in:active,inactive,trial',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $schoolIds = $request->school_ids;
            $moduleIds = $request->module_ids;
            $globalSettings = $request->global_settings ?? [];

            $results = $this->moduleService->bulkAssignModules($schoolIds, $moduleIds, $globalSettings);

            $successCount = count(array_filter($results, fn($result) => $result['success']));
            $totalCount = count($results);

            return $this->successResponse([
                'results' => $results,
                'summary' => [
                    'total_schools' => $totalCount,
                    'successful_assignments' => $successCount,
                    'failed_assignments' => $totalCount - $successCount
                ]
            ], "Bulk assignment completed: {$successCount}/{$totalCount} schools processed successfully");
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to bulk assign modules: ' . $e->getMessage(), null, 500);
        }
    }
}
