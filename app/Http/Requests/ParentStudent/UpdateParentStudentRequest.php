<?php

namespace App\Http\Requests\ParentStudent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParentStudentRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'relationship' => 'nullable|string|in:father,mother,guardian',
            'is_primary' => 'nullable|boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'relationship.in' => 'Relationship must be father, mother, or guardian.',
        ];
    }
}
