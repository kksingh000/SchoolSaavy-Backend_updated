<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'employee_id' => 'required|string|unique:teachers,employee_id',
            'phone' => 'required|string|max:20',
            'date_of_birth' => 'required|date|before:today',
            'joining_date' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'qualification' => 'required|string|max:255',
            'address' => 'required|string',
            'specializations' => 'nullable|array',
            'specializations.*' => 'string|max:100',
            'profile_photo' => 'nullable|string|max:500', // Expecting S3 path string from upload API
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Teacher name is required',
            'email.required' => 'Email address is required',
            'email.unique' => 'This email address is already registered',
            'employee_id.required' => 'Employee ID is required',
            'employee_id.unique' => 'This employee ID already exists',
            'phone.required' => 'Phone number is required',
            'date_of_birth.required' => 'Date of birth is required',
            'date_of_birth.before' => 'Date of birth must be before today',
            'joining_date.required' => 'Joining date is required',
            'gender.required' => 'Gender is required',
            'qualification.required' => 'Qualification is required',
            'address.required' => 'Address is required',
        ];
    }
}
