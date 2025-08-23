<?php

namespace App\Services\SuperAdmin;

use App\Models\School;
use App\Models\Module;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SchoolModuleService
{
    /**
     * Get all available modules
     */
    public function getAllModules()
    {
        return Module::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'monthly_price', 'yearly_price', 'features']);
    }

    /**
     * Get school's assigned modules
     */
    public function getSchoolModules($schoolId)
    {
        $school = School::findOrFail($schoolId);

        return $school->modules()
            ->select([
                'modules.id',
                'modules.name',
                'modules.slug',
                'modules.description',
                'modules.monthly_price',
                'modules.yearly_price',
                'school_modules.activated_at',
                'school_modules.expires_at',
                'school_modules.status',
                'school_modules.settings',
                'school_modules.created_at as assigned_at'
            ])
            ->orderBy('modules.name')
            ->get();
    }

    /**
     * Assign modules to school
     */
    public function assignModulesToSchool($schoolId, array $moduleIds, array $moduleSettings = [])
    {
        $school = School::findOrFail($schoolId);

        return DB::transaction(function () use ($school, $moduleIds, $moduleSettings) {
            $assignedModules = [];

            foreach ($moduleIds as $moduleId) {
                // Check if module exists and is active
                $module = Module::where('id', $moduleId)
                    ->where('is_active', true)
                    ->first();

                if (!$module) {
                    continue; // Skip invalid modules
                }

                // Check if already assigned
                $existingAssignment = $school->modules()->where('module_id', $moduleId)->first();

                if ($existingAssignment) {
                    continue; // Skip already assigned modules
                }

                // Prepare module assignment data
                $assignmentData = [
                    'activated_at' => Carbon::now(),
                    'status' => 'active',
                    'settings' => isset($moduleSettings[$moduleId]) ? json_encode($moduleSettings[$moduleId]) : null,
                ];

                // Set expiration if specified in settings
                if (isset($moduleSettings[$moduleId]['expires_at'])) {
                    $assignmentData['expires_at'] = Carbon::parse($moduleSettings[$moduleId]['expires_at']);
                }

                // Assign module to school
                $school->modules()->attach($moduleId, $assignmentData);

                $assignedModules[] = [
                    'module_id' => $moduleId,
                    'module_name' => $module->name,
                    'module_slug' => $module->slug,
                    'assigned_at' => $assignmentData['activated_at'],
                    'status' => $assignmentData['status']
                ];
            }

            return $assignedModules;
        });
    }

    /**
     * Remove modules from school
     */
    public function removeModulesFromSchool($schoolId, array $moduleIds)
    {
        $school = School::findOrFail($schoolId);

        return DB::transaction(function () use ($school, $moduleIds) {
            $removedModules = [];

            foreach ($moduleIds as $moduleId) {
                $module = $school->modules()->where('module_id', $moduleId)->first();

                if ($module) {
                    $removedModules[] = [
                        'module_id' => $moduleId,
                        'module_name' => $module->name,
                        'module_slug' => $module->slug,
                        'removed_at' => Carbon::now()
                    ];

                    // Remove the module assignment
                    $school->modules()->detach($moduleId);
                }
            }

            return $removedModules;
        });
    }

    /**
     * Update school module settings
     */
    public function updateSchoolModuleSettings($schoolId, $moduleId, array $settings)
    {
        $school = School::findOrFail($schoolId);

        // Check if school has this module assigned
        $moduleAssignment = $school->modules()->where('module_id', $moduleId)->first();

        if (!$moduleAssignment) {
            throw new \Exception('Module not assigned to this school');
        }

        // Update the pivot settings
        $updateData = ['settings' => json_encode($settings)];

        // Update expiration if provided
        if (isset($settings['expires_at'])) {
            $updateData['expires_at'] = Carbon::parse($settings['expires_at']);
        }

        // Update status if provided
        if (isset($settings['status']) && in_array($settings['status'], ['active', 'inactive', 'expired', 'trial'])) {
            $updateData['status'] = $settings['status'];
        }

        $school->modules()->updateExistingPivot($moduleId, $updateData);

        return $school->modules()->where('module_id', $moduleId)->first();
    }

    /**
     * Toggle school module status
     */
    public function toggleSchoolModuleStatus($schoolId, $moduleId)
    {
        $school = School::findOrFail($schoolId);

        $moduleAssignment = $school->modules()->where('module_id', $moduleId)->first();

        if (!$moduleAssignment) {
            throw new \Exception('Module not assigned to this school');
        }

        $newStatus = $moduleAssignment->pivot->status === 'active' ? 'inactive' : 'active';

        $school->modules()->updateExistingPivot($moduleId, [
            'status' => $newStatus
        ]);

        return $school->modules()->where('module_id', $moduleId)->first();
    }

    /**
     * Get school module usage analytics
     */
    public function getSchoolModuleAnalytics($schoolId)
    {
        $school = School::findOrFail($schoolId);

        $modules = $school->modules()
            ->select([
                'modules.id',
                'modules.name',
                'modules.slug',
                'school_modules.activated_at',
                'school_modules.expires_at',
                'school_modules.status',
                'school_modules.created_at as assigned_at'
            ])
            ->get();

        return [
            'school_id' => $schoolId,
            'school_name' => $school->name,
            'total_assigned_modules' => $modules->count(),
            'active_modules' => $modules->where('pivot.status', 'active')->count(),
            'inactive_modules' => $modules->where('pivot.status', 'inactive')->count(),
            'trial_modules' => $modules->where('pivot.status', 'trial')->count(),
            'expired_modules' => $modules->where('pivot.status', 'expired')->count(),
            'modules' => $modules->map(function ($module) {
                return [
                    'id' => $module->id,
                    'name' => $module->name,
                    'slug' => $module->slug,
                    'status' => $module->pivot->status,
                    'assigned_at' => $module->pivot->assigned_at,
                    'activated_at' => $module->pivot->activated_at,
                    'expires_at' => $module->pivot->expires_at,
                    'days_since_activation' => $module->pivot->activated_at
                        ? Carbon::parse($module->pivot->activated_at)->diffInDays(Carbon::now())
                        : null,
                    'days_until_expiry' => $module->pivot->expires_at
                        ? Carbon::now()->diffInDays(Carbon::parse($module->pivot->expires_at), false)
                        : null,
                ];
            })
        ];
    }

    /**
     * Bulk assign modules to multiple schools
     */
    public function bulkAssignModules(array $schoolIds, array $moduleIds, array $globalSettings = [])
    {
        return DB::transaction(function () use ($schoolIds, $moduleIds, $globalSettings) {
            $results = [];

            foreach ($schoolIds as $schoolId) {
                try {
                    $assigned = $this->assignModulesToSchool($schoolId, $moduleIds, $globalSettings);
                    $results[$schoolId] = [
                        'success' => true,
                        'assigned_modules' => $assigned
                    ];
                } catch (\Exception $e) {
                    $results[$schoolId] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return $results;
        });
    }
}
