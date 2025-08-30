<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\UserDeviceToken;

class RegisterDeviceTokenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'device_id' => 'required|string|max:255',
            'firebase_token' => 'required|string|max:500',
            'device_type' => 'nullable|string|in:' . implode(',', array_keys(UserDeviceToken::getDeviceTypes())),
            'app_version' => 'nullable|string|max:50',
            'device_name' => 'nullable|string|max:255'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'device_id.required' => 'Device ID is required',
            'device_id.max' => 'Device ID cannot exceed 255 characters',
            'firebase_token.required' => 'Firebase token is required',
            'firebase_token.max' => 'Firebase token cannot exceed 500 characters',
            'device_type.in' => 'Invalid device type selected',
            'app_version.max' => 'App version cannot exceed 50 characters',
            'device_name.max' => 'Device name cannot exceed 255 characters'
        ];
    }
}
