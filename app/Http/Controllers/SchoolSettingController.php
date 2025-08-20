<?php

namespace App\Http\Controllers;

use App\Models\SchoolSetting;
use App\Http\Controllers\BaseController;
use App\Http\Requests\SchoolSetting\StoreSchoolSettingRequest;
use App\Http\Requests\SchoolSetting\UpdateSchoolSettingRequest;
use App\Http\Requests\SchoolSetting\UpdateBulkSchoolSettingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SchoolSettingController extends BaseController
{
    /**
     * Get all settings for the school
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $schoolId = request()->school_id;
            $category = $request->get('category');

            $query = SchoolSetting::forSchool($schoolId)->active();

            if ($category) {
                $query->byCategory($category);
            }

            $settings = $query->get()->groupBy('category');

            return $this->successResponse($settings, 'Settings retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get a specific setting
     */
    public function show($key): JsonResponse
    {
        try {
            $schoolId = request()->school_id;

            $setting = SchoolSetting::forSchool($schoolId)
                ->where('key', $key)
                ->active()
                ->first();

            if (!$setting) {
                return $this->errorResponse('Setting not found', null, 404);
            }

            return $this->successResponse($setting);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Create or update a setting
     */
    public function store(StoreSchoolSettingRequest $request): JsonResponse
    {
        try {
            $schoolId = request()->school_id;
            $data = $request->validated();

            $setting = SchoolSetting::setSetting(
                $schoolId,
                $data['key'],
                $data['value'],
                $data['type'],
                $data['category'],
                $data['description'] ?? null
            );

            return $this->successResponse($setting, 'Setting saved successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Update a specific setting by key
     */
    public function update(UpdateSchoolSettingRequest $request, $key): JsonResponse
    {
        try {
            $schoolId = request()->school_id;

            // Check if setting exists
            $existingSetting = SchoolSetting::forSchool($schoolId)
                ->where('key', $key)
                ->first();

            if (!$existingSetting) {
                return $this->errorResponse('Setting not found', null, 404);
            }

            $data = $request->validated();

            $setting = SchoolSetting::setSetting(
                $schoolId,
                $key,
                $data['value'],
                $data['type'],
                $data['category'],
                $data['description'] ?? $existingSetting->description
            );

            return $this->successResponse($setting, 'Setting updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Update multiple settings at once
     */
    public function updateBulk(UpdateBulkSchoolSettingRequest $request): JsonResponse
    {
        try {
            $schoolId = request()->school_id;
            $data = $request->validated();
            $settings = $data['settings'];
            $updatedSettings = [];

            foreach ($settings as $settingData) {
                $setting = SchoolSetting::setSetting(
                    $schoolId,
                    $settingData['key'],
                    $settingData['value'],
                    $settingData['type'],
                    $settingData['category'],
                    $settingData['description'] ?? null
                );

                $updatedSettings[] = $setting;
            }

            return $this->successResponse($updatedSettings, 'Settings updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Delete a setting
     */
    public function destroy($key): JsonResponse
    {
        try {
            $schoolId = request()->school_id;

            $setting = SchoolSetting::forSchool($schoolId)
                ->where('key', $key)
                ->first();

            if (!$setting) {
                return $this->errorResponse('Setting not found', null, 404);
            }

            $setting->delete();

            return $this->successResponse(null, 'Setting deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get settings by category
     */
    public function getByCategory($category): JsonResponse
    {
        try {
            $schoolId = request()->school_id;

            $settings = SchoolSetting::getSettingsByCategory($schoolId, $category);

            return $this->successResponse($settings, "Settings for category '{$category}' retrieved successfully");
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
