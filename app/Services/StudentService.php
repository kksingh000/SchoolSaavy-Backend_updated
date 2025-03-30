<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudentService
{
    public function getAllStudents($filters = [])
    {
        $query = Student::with(['school', 'parents'])
            ->where('school_id', request()->school_id);

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('admission_number', 'like', "%{$search}%");
            });
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (isset($filters['blood_group'])) {
            $query->where('blood_group', $filters['blood_group']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['admission_date'])) {
            $query->whereDate('admission_date', $filters['admission_date']);
        }

        return $query->paginate(10);
    }

    public function createStudent($data)
    {
        DB::beginTransaction();
        try {
            // Handle profile photo upload if present
            if (isset($data['profile_photo'])) {
                $data['profile_photo'] = $this->uploadProfilePhoto($data['profile_photo']);
            }

            // school_id and created_by are already in $data from middleware
            $student = Student::create($data);

            // Create parent-student relationship
            if (isset($data['parent_id'])) {
                $student->parents()->attach($data['parent_id'], [
                    'relationship' => $data['relationship'],
                    'is_primary' => $data['is_primary'] ?? true
                ]);
            }

            DB::commit();
            return $student->load(['school', 'parents']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getStudentById($id)
    {
        return Student::with(['school', 'parents'])
            ->where('school_id', request()->school_id)
            ->findOrFail($id);
    }

    public function updateStudent($id, $data)
    {
        DB::beginTransaction();
        try {
            $student = Student::where('school_id', request()->school_id)
                ->findOrFail($id);

            if (isset($data['profile_photo'])) {
                if ($student->profile_photo) {
                    Storage::delete($student->profile_photo);
                }
                $data['profile_photo'] = $this->uploadProfilePhoto($data['profile_photo']);
            }

            $student->update($data);

            DB::commit();
            return $student->load(['school', 'parents']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteStudent($id)
    {
        DB::beginTransaction();
        try {
            $student = Student::where('school_id', request()->school_id)
                ->findOrFail($id);
            
            if ($student->profile_photo) {
                Storage::delete($student->profile_photo);
            }

            $student->delete();
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function uploadProfilePhoto($photo)
    {
        return $photo->store('student-photos', 'public');
    }
} 