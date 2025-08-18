<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Traits\GeneratesFileUrls;

class TeacherService extends BaseService
{
    use GeneratesFileUrls;

    protected function initializeModel()
    {
        $this->model = Teacher::class;
    }

    /**
     * Get all teachers for a school with filters and pagination
     */
    public function getAllTeachers($filters = [], $perPage = 15)
    {
        $query = Teacher::with(['user', 'school', 'classes'])
            ->where('school_id', request()->school_id);

        // Apply filters
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('employee_id', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        }

        if (isset($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (isset($filters['qualification'])) {
            $query->where('qualification', 'like', "%{$filters['qualification']}%");
        }

        if (isset($filters['specialization'])) {
            $query->whereJsonContains('specializations', $filters['specialization']);
        }

        if (isset($filters['joining_date_from'])) {
            $query->where('joining_date', '>=', $filters['joining_date_from']);
        }

        if (isset($filters['joining_date_to'])) {
            $query->where('joining_date', '<=', $filters['joining_date_to']);
        }

        // Add ordering for consistent pagination
        $query->orderBy('joining_date', 'desc')
            ->orderBy('id');

        return $query->paginate($perPage);
    }

    /**
     * Create a new teacher with user account
     */
    public function createTeacher($data)
    {
        DB::beginTransaction();
        try {
            // Validate profile photo path if present
            if (isset($data['profile_photo']) && !empty($data['profile_photo'])) {
                if (!str_starts_with($data['profile_photo'], 'uploads/')) {
                    throw new \Exception('Invalid profile photo path format');
                }
            }

            // Create user account first
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'user_type' => 'teacher',
            ];

            $user = User::create($userData);

            // Create teacher profile
            $teacherData = [
                'user_id' => $user->id,
                'school_id' => request()->school_id, // From middleware
                'employee_id' => $data['employee_id'],
                'phone' => $data['phone'],
                'date_of_birth' => $data['date_of_birth'],
                'joining_date' => $data['joining_date'],
                'gender' => $data['gender'],
                'qualification' => $data['qualification'],
                'address' => $data['address'],
                'specializations' => $data['specializations'] ?? null,
                'profile_photo' => $data['profile_photo'] ?? null,
            ];

            $teacher = Teacher::create($teacherData);

            DB::commit();
            return $teacher->load(['user', 'school']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Teacher creation failed:', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Get teacher by ID with relationships
     */
    public function getTeacherById($id)
    {
        return Teacher::with(['user', 'school', 'classes.subjects'])
            ->where('school_id', request()->school_id)
            ->findOrFail($id);
    }

    /**
     * Update teacher information
     */
    public function updateTeacher($id, array $data)
    {
        DB::beginTransaction();
        try {
            $teacher = Teacher::where('school_id', request()->school_id)
                ->findOrFail($id);

            // Handle profile photo if provided
            if (isset($data['profile_photo']) && !empty($data['profile_photo'])) {
                if (!str_starts_with($data['profile_photo'], 'uploads/')) {
                    throw new \Exception('Invalid profile photo path format');
                }
            } elseif (array_key_exists('profile_photo', $data) && is_null($data['profile_photo'])) {
                // If profile_photo is explicitly set to null, remove the photo
                $data['profile_photo'] = null;
            }

            // Update user information if provided
            if (isset($data['name']) || isset($data['email'])) {
                $userData = [];
                if (isset($data['name'])) {
                    $userData['name'] = $data['name'];
                    unset($data['name']);
                }
                if (isset($data['email'])) {
                    $userData['email'] = $data['email'];
                    unset($data['email']);
                }
                if (isset($data['password'])) {
                    $userData['password'] = Hash::make($data['password']);
                    unset($data['password']);
                }

                $teacher->user()->update($userData);
            }

            // Update teacher information
            $teacher->update($data);

            DB::commit();

            Log::info('Teacher updated successfully:', ['id' => $teacher->id]);

            return $teacher->fresh()->load(['user', 'school', 'classes']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Teacher update failed:', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Delete teacher (soft delete)
     */
    public function deleteTeacher($id)
    {
        DB::beginTransaction();
        try {
            $teacher = Teacher::where('school_id', request()->school_id)
                ->findOrFail($id);

            // Check if teacher has active assignments or classes
            $activeAssignments = $teacher->assignments()->where('status', '!=', 'completed')->count();
            $activeClasses = $teacher->classes()->count();

            if ($activeAssignments > 0 || $activeClasses > 0) {
                throw new \Exception('Cannot delete teacher with active assignments or classes');
            }

            // Soft delete teacher
            $teacher->delete();

            // Optionally soft delete the user account as well
            $teacher->user()->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Teacher deletion failed:', [
                'error' => $e->getMessage(),
                'teacher_id' => $id
            ]);
            throw $e;
        }
    }

    /**
     * Get teacher's classes with students count
     */
    public function getTeacherClasses($teacherId)
    {
        $teacher = Teacher::where('school_id', request()->school_id)
            ->findOrFail($teacherId);

        return $teacher->classes()
            ->withCount('students')
            ->with(['subjects'])
            ->get();
    }

    /**
     * Get teacher's assignments with statistics
     */
    public function getTeacherAssignments($teacherId, $status = null, $perPage = 15)
    {
        $teacher = Teacher::where('school_id', request()->school_id)
            ->findOrFail($teacherId);

        $query = $teacher->assignments()
            ->with(['class', 'subject'])
            ->withCount(['submissions', 'submissions as graded_submissions_count' => function ($q) {
                $q->whereNotNull('marks_obtained');
            }]);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('due_date', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get teacher dashboard statistics
     */
    public function getTeacherDashboardStats($teacherId)
    {
        $teacher = Teacher::where('school_id', request()->school_id)
            ->findOrFail($teacherId);

        $stats = [
            'total_classes' => $teacher->classes()->count(),
            'total_students' => $teacher->classes()->withCount('students')->get()->sum('students_count'),
            'active_assignments' => $teacher->assignments()
                ->whereIn('status', ['published', 'completed'])
                ->count(),
            'pending_gradings' => $teacher->assignments()
                ->whereHas('submissions', function ($q) {
                    $q->whereNull('marks_obtained');
                })
                ->count(),
            'recent_assignments' => $teacher->assignments()
                ->with(['class', 'subject'])
                ->latest()
                ->take(5)
                ->get(),
        ];

        return $stats;
    }

    /**
     * Search teachers by various criteria
     */
    public function searchTeachers($searchTerm, $perPage = 15)
    {
        return Teacher::with(['user', 'school'])
            ->where('school_id', request()->school_id)
            ->where(function ($query) use ($searchTerm) {
                $query->whereHas('user', function ($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('email', 'like', "%{$searchTerm}%");
                })
                    ->orWhere('employee_id', 'like', "%{$searchTerm}%")
                    ->orWhere('phone', 'like', "%{$searchTerm}%")
                    ->orWhere('qualification', 'like', "%{$searchTerm}%")
                    ->orWhereJsonContains('specializations', $searchTerm);
            })
            ->paginate($perPage);
    }

    /**
     * Generate unique employee ID for school
     */
    public function generateEmployeeId($schoolId)
    {
        $prefix = 'TCH';
        $year = date('y');

        // Get the last employee ID for this school
        $lastTeacher = Teacher::where('school_id', $schoolId)
            ->where('employee_id', 'like', "{$prefix}{$year}%")
            ->orderBy('employee_id', 'desc')
            ->first();

        if ($lastTeacher) {
            $lastNumber = (int) substr($lastTeacher->employee_id, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $year . $newNumber; // e.g., TCH250001
    }
}
