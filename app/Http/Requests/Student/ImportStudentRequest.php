<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class ImportStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file_path' => [
                'required',
                'string',
            ],
            'file_name' => [
                'required',
                'string',
            ]
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'file_path.required' => 'File path is required. Please upload the file first using the file upload API.',
            'file_path.string' => 'File path must be a valid string.',
            'file_name.required' => 'File name is required.',
            'file_name.string' => 'File name must be a valid string.',
        ];
    }
}
