<?php

namespace App\Services\SuperAdmin;

use App\Models\School;
use App\Models\User;
use App\Models\SchoolAdmin;
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

            return $school->load('schoolAdmin.user');
        });
    }

    /**
     * Update school details
     */
    public function updateSchool($schoolId, array $data)
    {
        $school = School::findOrFail($schoolId);
        $school->update($data);

        return $school->load('schoolAdmin.user');
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
}
