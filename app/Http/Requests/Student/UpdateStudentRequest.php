<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'school_id' => 'exists:schools,id',
            'admission_number' => 'string|unique:students,admission_number,' . $this->student,
            'roll_number' => 'string',
            'first_name' => 'string|max:255',
            'last_name' => 'string|max:255',
            'date_of_birth' => 'date|before:today',
            'gender' => 'in:male,female,other',
            'admission_date' => 'date',
            'blood_group' => 'nullable|string|in:A+,A-,B+,B-,O+,O-,AB+,AB-',
            'address' => 'string',
            'phone' => 'nullable|string',
            'is_active' => 'boolean',
            'profile_photo' => 'nullable|image|max:2048'
        ];
    }
} 