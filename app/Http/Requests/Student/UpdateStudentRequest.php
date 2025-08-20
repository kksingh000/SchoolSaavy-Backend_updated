<?php

namespace App\Http\Requests\Student;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UpdateStudentRequest extends Request
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        // Log the incoming request for debugging
        Log::info('Update Request Content:', [
            'all' => $this->all(),
            'input' => $this->input(),
            'json' => $this->json()->all(),
            'isJson' => $this->isJson(),
        ]);

        return [
            'admission_number' => 'sometimes|string|unique:students,admission_number,' . $this->route('student'),
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'date_of_birth' => 'sometimes|date|before:today',
            'gender' => 'sometimes|in:male,female,other',
            'admission_date' => 'sometimes|date',
            'blood_group' => 'sometimes|nullable|string|in:A+,A-,B+,B-,O+,O-,AB+,AB-',
            'address' => 'sometimes|string',
            'phone' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
            'class_id' => 'sometimes|nullable|exists:classes,id',
            'class_roll_number' => 'sometimes|nullable|numeric', // Changed to accept numeric values
            'profile_photo' => 'sometimes|nullable|string|max:500' // Expecting S3 path string from upload API
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that class belongs to the same school if class_id is provided
            if ($this->filled('class_id')) {
                $class = \App\Models\ClassRoom::find($this->class_id);
                if ($class && $class->school_id != request()->school_id) {
                    $validator->errors()->add('class_id', 'The selected class does not belong to your school.');
                }

                // Validate that class is active
                if ($class && !$class->is_active) {
                    $validator->errors()->add('class_id', 'The selected class is not active.');
                }
            }
        });
    }

    protected function prepareForValidation()
    {
        // Log the request method and content type
        Log::info('Request Method and Content:', [
            'method' => $this->method(),
            'contentType' => $this->header('Content-Type'),
            'raw' => $this->getContent()
        ]);

        // Convert class_roll_number to string if it's numeric
        if ($this->has('class_roll_number') && is_numeric($this->class_roll_number)) {
            $this->merge([
                'class_roll_number' => (string) $this->class_roll_number
            ]);
        }
    }
}
