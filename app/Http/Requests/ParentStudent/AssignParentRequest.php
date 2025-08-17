<?php

namespace App\Http\Requests\ParentStudent;

use Illuminate\Foundation\Http\FormRequest;

class AssignParentRequest extends FormRequest
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
            'parent_id' => 'required|exists:parents,id',
            'relationship' => 'required|string|in:father,mother,guardian',
            'is_primary' => 'boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'parent_id.required' => 'Parent selection is required.',
            'parent_id.exists' => 'Selected parent does not exist.',
            'relationship.required' => 'Relationship type is required.',
            'relationship.in' => 'Relationship must be father, mother, or guardian.',
        ];
    }
}
