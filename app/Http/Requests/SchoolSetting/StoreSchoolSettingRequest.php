<?php

namespace App\Http\Requests\SchoolSetting;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolSettingRequest extends FormRequest
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
            'key' => 'required|string|max:255',
            'value' => 'required',
            'type' => 'sometimes|in:string,integer,boolean,json,file_url',
            'category' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:500'
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'key.required' => 'Setting key is required',
            'key.string' => 'Setting key must be a string',
            'key.max' => 'Setting key cannot exceed 255 characters',
            'value.required' => 'Setting value is required',
            'type.in' => 'Setting type must be one of: string, integer, boolean, json, file_url',
            'category.string' => 'Category must be a string',
            'category.max' => 'Category cannot exceed 255 characters',
            'description.string' => 'Description must be a string',
            'description.max' => 'Description cannot exceed 500 characters'
        ];
    }

    /**
     * Get custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'key' => 'setting key',
            'value' => 'setting value',
            'type' => 'data type',
            'category' => 'category',
            'description' => 'description'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values if not provided
        $this->merge([
            'type' => $this->type ?? 'string',
            'category' => $this->category ?? 'general',
        ]);
    }
}
