<?php

namespace App\Http\Requests\ParentStudent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateParentRequest extends FormRequest
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
        $parentId = $this->route('parentId');
        
        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $this->getUserIdFromParent($parentId),
            'password' => ['nullable', 'string', Password::min(6)],
            'phone' => 'sometimes|required|string|max:20',
            'alternate_phone' => 'nullable|string|max:20',
            'gender' => 'nullable|string|in:male,female,other',
            'occupation' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'relationship' => 'sometimes|required|string|in:father,mother,guardian',
            'is_active' => 'nullable|boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Parent name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'phone.required' => 'Phone number is required.',
            'gender.in' => 'Gender must be male, female, or other.',
            'relationship.in' => 'Relationship must be father, mother, or guardian.',
        ];
    }

    /**
     * Get user ID from parent ID for unique email validation
     */
    private function getUserIdFromParent($parentId)
    {
        $parent = \App\Models\Parents::find($parentId);
        return $parent ? $parent->user_id : null;
    }
}
