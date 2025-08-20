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
            'class_id' => 'nullable|exists:classes,id',
            'class_roll_number' => 'nullable|string',
            'parent_id' => 'required|exists:parents,id',
            'relationship' => 'required|string|in:father,mother,guardian',
            'is_primary' => 'boolean',
            'profile_photo' => 'nullable|string|max:500' // Expecting S3 path string from upload API
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that class belongs to the same school if class_id is provided
            if ($this->filled('class_id')) {
                $class = \App\Models\ClassRoom::find($this->class_id);
                if ($class && $class->school_id != $this->school_id) {
                    $validator->errors()->add('class_id', 'The selected class does not belong to your school.');
                }

                // Validate that class is active
                if ($class && !$class->is_active) {
                    $validator->errors()->add('class_id', 'The selected class is not active.');
                }
            }

            // Validate that parent belongs to the same school (if we have school context)
            if ($this->filled('parent_id') && request()->has('school_id')) {
                $parent = \App\Models\Parents::with('user')->find($this->parent_id);
                // We'll need to add school_id to parents table or check through relationships
                // For now, we'll skip this validation as parents might be shared across schools
            }
        });
    }
}
