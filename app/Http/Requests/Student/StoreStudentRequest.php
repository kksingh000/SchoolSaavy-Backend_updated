<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'school_id' => 'required|exists:schools,id',
            'admission_number' => 'required|string|unique:students',
            'roll_number' => 'required|string',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:male,female,other',
            'admission_date' => 'required|date',
            'blood_group' => 'nullable|string|in:A+,A-,B+,B-,O+,O-,AB+,AB-',
            'address' => 'required|string',
            'phone' => 'nullable|string',
            'parent_id' => 'required|exists:parents,id',
            'relationship' => 'required|string|in:father,mother,guardian',
            'is_primary' => 'boolean',
            'profile_photo' => 'nullable|string|max:500' // Expecting S3 path string from upload API
        ];
    }
}
