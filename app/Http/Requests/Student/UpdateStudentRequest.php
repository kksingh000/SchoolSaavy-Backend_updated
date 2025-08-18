<?php

namespace App\Http\Requests\Student;

use Illuminate\Http\Request;

class UpdateStudentRequest extends Request
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        // Log the incoming request for debugging
        \Log::info('Update Request Content:', [
            'all' => $this->all(),
            'input' => $this->input(),
            'json' => $this->json()->all(),
            'isJson' => $this->isJson(),
        ]);

        return [
            'admission_number' => 'sometimes|string|unique:students,admission_number,' . $this->route('student'),
            'roll_number' => 'sometimes|string',
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'date_of_birth' => 'sometimes|date|before:today',
            'gender' => 'sometimes|in:male,female,other',
            'admission_date' => 'sometimes|date',
            'blood_group' => 'sometimes|nullable|string|in:A+,A-,B+,B-,O+,O-,AB+,AB-',
            'address' => 'sometimes|string',
            'phone' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
            'profile_photo' => 'sometimes|nullable|string|max:500' // Expecting S3 path string from upload API
        ];
    }

    protected function prepareForValidation()
    {
        // Log the request method and content type
        \Log::info('Request Method and Content:', [
            'method' => $this->method(),
            'contentType' => $this->header('Content-Type'),
            'raw' => $this->getContent()
        ]);
    }
}
