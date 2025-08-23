<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateSchoolRequest extends FormRequest
{
    public function authorize()
    {
        return Auth::check() && Auth::user()->user_type === 'super_admin';
    }

    public function rules()
    {
        return [
            // School data
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:schools,code',
            'address' => 'required|string',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|unique:schools,email',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|string|max:500',

            // Admin user data
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8|confirmed',
            'admin_password_confirmation' => 'required|string|min:8',
            'admin_phone' => 'nullable|string|max:20',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'School name is required.',
            'code.required' => 'School code is required.',
            'code.unique' => 'School code must be unique.',
            'address.required' => 'School address is required.',
            'phone.required' => 'School phone number is required.',
            'email.required' => 'School email is required.',
            'email.unique' => 'School email must be unique.',
            'admin_name.required' => 'Admin name is required.',
            'admin_email.required' => 'Admin email is required.',
            'admin_email.unique' => 'Admin email must be unique.',
            'admin_password.required' => 'Admin password is required.',
            'admin_password.min' => 'Admin password must be at least 8 characters.',
            'admin_password.confirmed' => 'Admin password confirmation does not match.',
        ];
    }
}
