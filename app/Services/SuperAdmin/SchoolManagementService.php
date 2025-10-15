<?php

namespace App\Services\SuperAdmin;

use App\Models\School;
use App\Models\User;
use App\Models\SchoolAdmin;
use App\Models\SchoolSetting;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SchoolManagementService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = School::class;
    }

    /**
     * Get all schools with pagination and basic stats
     */
    public function getAllSchools($perPage = 15)
    {
        return School::withCount([
            'students',
            'teachers',
            'parents' => function ($query) {
                $query->whereHas('students');
            }
        ])
            ->with(['schoolAdmin.user:id,name,email'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Create a new school with admin user
     */
    public function createSchoolWithAdmin(array $schoolData, array $adminData)
    {
        return DB::transaction(function () use ($schoolData, $adminData) {
            // Create school
            $school = School::create($schoolData);

            // Create admin user
            $adminUser = User::create([
                'name' => $adminData['name'],
                'email' => $adminData['email'],
                'password' => Hash::make($adminData['password']),
                'user_type' => 'school_admin',
                'is_active' => true,
            ]);

            // Create school admin record
            SchoolAdmin::create([
                'user_id' => $adminUser->id,
                'school_id' => $school->id,
                'phone' => $adminData['phone'] ?? null,
                'permissions' => [
                    'manage_staff' => true,
                    'manage_students' => true,
                    'manage_finances' => true,
                    'manage_settings' => true,
                ],
            ]);

            // Apply default settings for the school
            $this->applyDefaultSettings($school->id);

            return $school->load('schoolAdmin.user');
        });
    }

    /**
     * Update school details and optionally admin details
     * 
     * @param int $schoolId The ID of the school to update
     * @param array $schoolData School data to update
     * @param array|null $adminData Admin data to update (optional)
     * @return School Updated school with admin details
     */
    public function updateSchool($schoolId, array $schoolData, ?array $adminData = null)
    {
        return DB::transaction(function () use ($schoolId, $schoolData, $adminData) {
            $school = School::findOrFail($schoolId);
            $school->update($schoolData);
            
            // Update admin details if provided
            if ($adminData && $school->schoolAdmin) {
                $adminUser = $school->schoolAdmin->user;
                
                // Update admin user details
                $userUpdateData = [];
                
                if (isset($adminData['name'])) {
                    $userUpdateData['name'] = $adminData['name'];
                }
                
                if (isset($adminData['email'])) {
                    $userUpdateData['email'] = $adminData['email'];
                }
                
                if (!empty($adminData['password'])) {
                    $userUpdateData['password'] = Hash::make($adminData['password']);
                }
                
                if (!empty($userUpdateData)) {
                    $adminUser->update($userUpdateData);
                }
                
                // Update admin profile details
                if (isset($adminData['phone'])) {
                    $school->schoolAdmin->update([
                        'phone' => $adminData['phone']
                    ]);
                }
            }
            
            return $school->load('schoolAdmin.user');
        });
    }

    /**
     * Toggle school active status
     */
    public function toggleSchoolStatus($schoolId)
    {
        $school = School::findOrFail($schoolId);
        $school->update(['is_active' => !$school->is_active]);

        return $school;
    }

    /**
     * Get school details with admin info
     */
    public function getSchoolDetails($schoolId)
    {
        return School::with([
            'schoolAdmin.user:id,name,email,is_active',
            'modules'
        ])
            ->withCount(['students', 'teachers'])
            ->findOrFail($schoolId);
    }

    /**
     * Delete school (soft delete)
     */
    public function deleteSchool($schoolId)
    {
        $school = School::findOrFail($schoolId);

        return DB::transaction(function () use ($school) {
            // Deactivate admin user
            if ($school->schoolAdmin) {
                $school->schoolAdmin->user->update(['is_active' => false]);
            }

            // Soft delete school
            $school->delete();

            return true;
        });
    }

    /**
     * Get schools with filters
     */
    public function getFilteredSchools(array $filters = [], $perPage = 15)
    {
        $query = School::with(['schoolAdmin.user:id,name,email'])
            ->withCount(['students', 'teachers']);

        if (!empty($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (!empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Apply default settings for a newly created school
     *
     * @param int $schoolId
     * @return void
     */
    private function applyDefaultSettings(int $schoolId): void
    {
        $defaultSettings = [
            // Admission Number Settings
            [
                'key' => 'admission_number_prefix',
                'value' => 'STU',
                'type' => 'string',
                'category' => 'admission',
                'description' => 'Prefix for admission numbers (e.g., STU, SCH)'
            ],
            [
                'key' => 'admission_number_format',
                'value' => 'sequential',
                'type' => 'string',
                'category' => 'admission',
                'description' => 'Format: sequential (continuous) or year_sequential (resets yearly)'
            ],
            [
                'key' => 'admission_number_start_from',
                'value' => 1,
                'type' => 'integer',
                'category' => 'admission',
                'description' => 'Starting number for sequence'
            ],
            [
                'key' => 'admission_number_include_year',
                'value' => true,
                'type' => 'boolean',
                'category' => 'admission',
                'description' => 'Include current year in admission number'
            ],
            [
                'key' => 'admission_number_year_format',
                'value' => 'YYYY',
                'type' => 'string',
                'category' => 'admission',
                'description' => 'Year format: YYYY (2025) or YY (25)'
            ],
            [
                'key' => 'admission_number_padding',
                'value' => 4,
                'type' => 'integer',
                'category' => 'admission',
                'description' => 'Number of digits for sequential part (with zero padding)'
            ],

            // General School Settings
            [
                'key' => 'school_name_display',
                'value' => '',
                'type' => 'string',
                'category' => 'general',
                'description' => 'Display name for the school (if different from database name)'
            ],
            [
                'key' => 'school_logo_url',
                'value' => '',
                'type' => 'file_url',
                'category' => 'branding',
                'description' => 'URL to school logo image'
            ],
            [
                'key' => 'app_banner_url',
                'value' => '',
                'type' => 'file_url',
                'category' => 'branding',
                'description' => 'URL to app banner image for mobile applications'
            ],
            [
                'key' => 'primary_color',
                'value' => '#3B82F6',
                'type' => 'string',
                'category' => 'branding',
                'description' => 'Primary brand color for the school'
            ],
            [
                'key' => 'secondary_color',
                'value' => '#10B981',
                'type' => 'string',
                'category' => 'branding',
                'description' => 'Secondary brand color for the school'
            ],

            // Academic Settings
            [
                'key' => 'academic_year_start_month',
                'value' => 4,
                'type' => 'integer',
                'category' => 'academic',
                'description' => 'Month when academic year starts (1-12)'
            ],
            [
                'key' => 'attendance_required',
                'value' => true,
                'type' => 'boolean',
                'category' => 'academic',
                'description' => 'Whether attendance marking is mandatory'
            ],
            [
                'key' => 'minimum_attendance_percentage',
                'value' => 75,
                'type' => 'integer',
                'category' => 'academic',
                'description' => 'Minimum attendance percentage required for students'
            ],

            // Notification Settings
            [
                'key' => 'parent_notification_enabled',
                'value' => true,
                'type' => 'boolean',
                'category' => 'notifications',
                'description' => 'Enable notifications for parents'
            ],
            [
                'key' => 'teacher_notification_enabled',
                'value' => true,
                'type' => 'boolean',
                'category' => 'notifications',
                'description' => 'Enable notifications for teachers'
            ],

            // Module Settings
            [
                'key' => 'assignment_auto_grade',
                'value' => false,
                'type' => 'boolean',
                'category' => 'modules',
                'description' => 'Enable automatic grading for assignments'
            ],
            [
                'key' => 'assessment_result_auto_publish',
                'value' => false,
                'type' => 'boolean',
                'category' => 'modules',
                'description' => 'Automatically publish assessment results'
            ],
        ];

        foreach ($defaultSettings as $setting) {
            SchoolSetting::create([
                'school_id' => $schoolId,
                'key' => $setting['key'],
                'value' => $setting['value'],
                'type' => $setting['type'],
                'category' => $setting['category'],
                'description' => $setting['description'],
                'is_active' => true
            ]);
        }
    }
}
