<?php

namespace App\Http\Requests\ParentStudent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreParentRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'string', Password::min(6)],
            'phone' => 'required|string|max:20',
            'alternate_phone' => 'nullable|string|max:20',
            'gender' => 'nullable|string|in:male,female,other',
            'occupation' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'relationship' => 'required|string|in:father,mother,guardian'
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
            'password.required' => 'Password is required.',
            'phone.required' => 'Phone number is required.',
            'gender.in' => 'Gender must be male, female, or other.',
        ];
    }
}
