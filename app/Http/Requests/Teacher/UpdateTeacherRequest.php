<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        // Get teacher ID from route parameter 'id'
        $teacherId = $this->route('id');

        return [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($this->getTeacherUserId($teacherId))
            ],
            'password' => 'sometimes|string|min:8',
            'employee_id' => [
                'sometimes',
                'string',
                Rule::unique('teachers', 'employee_id')->where(function ($query) {
                    return $query->where('school_id', request()->school_id);
                })->ignore($teacherId)
            ],
            'phone' => 'sometimes|string|max:20',
            'date_of_birth' => 'sometimes|date|before:today',
            'joining_date' => 'sometimes|date',
            'gender' => 'sometimes|in:male,female,other',
            'qualification' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'specializations' => 'sometimes|nullable|array',
            'specializations.*' => 'string|max:100',
            'profile_photo' => 'sometimes|nullable|string|max:500', // Expecting S3 path string from upload API
        ];
    }

    public function messages()
    {
        return [
            'email.unique' => 'This email address is already registered',
            'employee_id.unique' => 'This employee ID already exists',
            'date_of_birth.before' => 'Date of birth must be before today',
        ];
    }

    /**
     * Get the user ID for the teacher being updated
     */
    private function getTeacherUserId($teacherId)
    {
        if (!$teacherId) {
            return null;
        }

        $teacher = \App\Models\Teacher::find($teacherId);
        return $teacher ? $teacher->user_id : null;
    }
}
