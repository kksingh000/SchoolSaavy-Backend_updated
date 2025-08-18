<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Parents;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ParentStudentService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = Parents::class;
    }

    /**
     * Get all parents of a specific student
     */
    public function getStudentParents($studentId)
    {
        // Get student with school verification
        $student = Student::where('id', $studentId)
            ->where('school_id', request()->input('school_id'))
            ->firstOrFail();

        return $student->parents()
            ->with('user:id,name,email')
            ->select('parents.id', 'parents.user_id', 'parents.phone', 'parents.occupation', 'parents.relationship')
            ->get()
            ->map(function ($parent) {
                return [
                    'id' => $parent->id,
                    'name' => $parent->user->name,
                    'email' => $parent->user->email,
                    'phone' => $parent->phone,
                    'occupation' => $parent->occupation,
                    'relationship' => $parent->pivot->relationship,
                    'is_primary' => $parent->pivot->is_primary,
                    'assigned_at' => $parent->pivot->created_at->format('Y-m-d H:i:s')
                ];
            });
    }

    /**
     * Assign existing parent to student
     */
    public function assignParentToStudent($studentId, $parentId, $relationship, $isPrimary = false)
    {
        return DB::transaction(function () use ($studentId, $parentId, $relationship, $isPrimary) {
            $schoolId = request()->input('school_id');

            // Verify student belongs to school
            $student = Student::where('id', $studentId)
                ->where('school_id', $schoolId)
                ->firstOrFail();

            // Verify parent exists
            $parent = Parents::findOrFail($parentId);

            // Check if relationship already exists
            if ($student->parents()->where('parent_id', $parentId)->exists()) {
                throw new \Exception('This parent is already assigned to this student.');
            }

            // If this is primary, remove primary status from other parents with same relationship
            if ($isPrimary) {
                $this->updatePrimaryStatus($studentId, $relationship);
            }

            // Assign parent to student
            $student->parents()->attach($parentId, [
                'relationship' => $relationship,
                'is_primary' => $isPrimary,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Return the assignment details
            return [
                'student_id' => $studentId,
                'parent_id' => $parentId,
                'parent_name' => $parent->user->name,
                'parent_email' => $parent->user->email,
                'relationship' => $relationship,
                'is_primary' => $isPrimary,
                'assigned_at' => now()->format('Y-m-d H:i:s')
            ];
        });
    }

    /**
     * Create new parent and assign to student
     */
    public function createAndAssignParent($studentId, $data)
    {
        return DB::transaction(function () use ($studentId, $data) {
            $schoolId = request()->input('school_id');

            // Verify student belongs to school
            $student = Student::where('id', $studentId)
                ->where('school_id', $schoolId)
                ->firstOrFail();

            // Create user account for parent
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'user_type' => 'parent',
                'is_active' => true,
                'email_verified_at' => now()
            ]);

            // Create parent profile
            $parent = Parents::create([
                'user_id' => $user->id,
                'phone' => $data['phone'],
                'alternate_phone' => $data['alternate_phone'] ?? null,
                'gender' => $data['gender'] ?? null,
                'occupation' => $data['occupation'] ?? null,
                'address' => $data['address'] ?? null,
                'relationship' => $data['relationship']
            ]);

            // If this is primary, remove primary status from other parents with same relationship
            if ($data['is_primary'] ?? false) {
                $this->updatePrimaryStatus($studentId, $data['relationship']);
            }

            // Assign parent to student
            $student->parents()->attach($parent->id, [
                'relationship' => $data['relationship'],
                'is_primary' => $data['is_primary'] ?? false,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return [
                'parent' => [
                    'id' => $parent->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $parent->phone,
                    'occupation' => $parent->occupation,
                    'relationship' => $data['relationship'],
                    'is_primary' => $data['is_primary'] ?? false
                ],
                'student' => [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'admission_number' => $student->admission_number
                ],
                'assigned_at' => now()->format('Y-m-d H:i:s')
            ];
        });
    }

    /**
     * Update parent-student relationship
     */
    public function updateParentStudentRelationship($studentId, $parentId, $data)
    {
        return DB::transaction(function () use ($studentId, $parentId, $data) {
            $schoolId = request()->input('school_id');

            // Verify student belongs to school
            $student = Student::where('id', $studentId)
                ->where('school_id', $schoolId)
                ->firstOrFail();

            // Check if relationship exists
            if (!$student->parents()->where('parent_id', $parentId)->exists()) {
                throw new \Exception('This parent is not assigned to this student.');
            }

            // If making this primary, remove primary status from other parents with same relationship
            if (isset($data['is_primary']) && $data['is_primary']) {
                $this->updatePrimaryStatus($studentId, $data['relationship'] ?? null, $parentId);
            }

            // Update the pivot relationship
            $updateData = [];
            if (isset($data['relationship'])) {
                $updateData['relationship'] = $data['relationship'];
            }
            if (isset($data['is_primary'])) {
                $updateData['is_primary'] = $data['is_primary'];
            }
            $updateData['updated_at'] = now();

            $student->parents()->updateExistingPivot($parentId, $updateData);

            // Get updated relationship details
            $parent = Parents::with('user:id,name,email')->findOrFail($parentId);
            $pivotData = $student->parents()->where('parent_id', $parentId)->first()->pivot;

            return [
                'student_id' => $studentId,
                'parent_id' => $parentId,
                'parent_name' => $parent->user->name,
                'parent_email' => $parent->user->email,
                'relationship' => $pivotData->relationship,
                'is_primary' => $pivotData->is_primary,
                'updated_at' => $pivotData->updated_at->format('Y-m-d H:i:s')
            ];
        });
    }

    /**
     * Remove parent from student
     */
    public function removeParentFromStudent($studentId, $parentId)
    {
        $schoolId = request()->input('school_id');

        // Verify student belongs to school
        $student = Student::where('id', $studentId)
            ->where('school_id', $schoolId)
            ->firstOrFail();

        // Check if relationship exists
        if (!$student->parents()->where('parent_id', $parentId)->exists()) {
            throw new \Exception('This parent is not assigned to this student.');
        }

        // Remove the relationship
        $student->parents()->detach($parentId);

        return true;
    }

    /**
     * Get all parents for selection/dropdown
     */
    public function getAllParents($search = null, $perPage = 15)
    {
        $query = Parents::with('user:id,name,email')
            ->select('parents.id', 'parents.user_id', 'parents.phone', 'parents.occupation', 'parents.relationship');

        // Apply search if provided
        if ($search) {
            $search = trim($search);
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            })
                ->orWhere('phone', 'LIKE', "%{$search}%")
                ->orWhere('occupation', 'LIKE', "%{$search}%");
        }

        $parents = $query->orderBy('parents.id', 'desc')->paginate($perPage);

        // Transform the data
        $parents->getCollection()->transform(function ($parent) {
            return [
                'id' => $parent->id,
                'name' => $parent->user->name,
                'email' => $parent->user->email,
                'phone' => $parent->phone,
                'occupation' => $parent->occupation,
                'relationship' => $parent->relationship
            ];
        });

        return $parents;
    }

    /**
     * Get parent details with children
     */
    public function getParentDetails($parentId)
    {
        $parent = Parents::with([
            'user:id,name,email,is_active',
            'students' => function ($query) {
                $query->where('school_id', request()->input('school_id'))
                    ->select(
                        'students.id',
                        'students.first_name',
                        'students.last_name',
                        'students.admission_number',
                        'students.is_active'
                    );
            }
        ])->findOrFail($parentId);

        return [
            'id' => $parent->id,
            'name' => $parent->user->name,
            'email' => $parent->user->email,
            'phone' => $parent->phone,
            'alternate_phone' => $parent->alternate_phone,
            'gender' => $parent->gender,
            'occupation' => $parent->occupation,
            'address' => $parent->address,
            'relationship' => $parent->relationship,
            'is_active' => $parent->user->is_active,
            'children' => $parent->students->map(function ($student) {
                return [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'admission_number' => $student->admission_number,
                    'relationship' => $student->pivot->relationship,
                    'is_primary' => $student->pivot->is_primary,
                    'is_active' => $student->is_active,
                    'assigned_at' => $student->pivot->created_at->format('Y-m-d H:i:s')
                ];
            })
        ];
    }

    /**
     * Create standalone parent (without student assignment)
     */
    public function createParent($data)
    {
        return DB::transaction(function () use ($data) {
            // Create user account for parent
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'user_type' => 'parent',
                'is_active' => true,
                'email_verified_at' => now()
            ]);

            // Create parent profile
            $parent = Parents::create([
                'user_id' => $user->id,
                'phone' => $data['phone'],
                'alternate_phone' => $data['alternate_phone'] ?? null,
                'relationship' => $data['relationship'],
                'gender' => $data['gender'] ?? null,
                'occupation' => $data['occupation'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            return [
                'id' => $parent->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $parent->phone,
                'alternate_phone' => $parent->alternate_phone,
                'gender' => $parent->gender,
                'occupation' => $parent->occupation,
                'address' => $parent->address,
                'is_active' => true,
                'created_at' => $parent->created_at->format('Y-m-d H:i:s')
            ];
        });
    }

    /**
     * Update primary status for parent relationships
     */
    private function updatePrimaryStatus($studentId, $relationship, $excludeParentId = null)
    {
        $query = DB::table('parent_student')
            ->where('student_id', $studentId);

        if ($relationship) {
            $query->where('relationship', $relationship);
        }

        if ($excludeParentId) {
            $query->where('parent_id', '!=', $excludeParentId);
        }

        $query->update(['is_primary' => false]);
    }
}
