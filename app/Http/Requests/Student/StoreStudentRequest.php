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
            'name' => 'required|string|max:255',
            'gender' => 'required|in:male,female,other',
            'date_of_birth' => 'required|date',
            'admission_date' => 'required|date',
            'roll_number' => 'required|string|unique:students',
            'class_id' => 'required|exists:class_rooms,id',
            'parent_details' => 'required|array',
            'parent_details.name' => 'required|string',
            'parent_details.contact' => 'required|string',
            'contact_number' => 'required|string',
            'email' => 'required|email|unique:students',
            'address' => 'required|array',
            'blood_group' => 'nullable|string',
            'profile_photo' => 'nullable|image|max:2048',
        ];
    }
} 