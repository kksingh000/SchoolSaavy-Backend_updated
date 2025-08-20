<?php

namespace App\Http\Requests\SchoolSetting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBulkSchoolSettingRequest extends FormRequest
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
            'settings' => 'required|array|min:1',
            'settings.*.key' => 'required|string|max:255',
            'settings.*.value' => 'required',
            'settings.*.type' => 'sometimes|in:string,integer,boolean,json,file_url',
            'settings.*.category' => 'sometimes|string|max:255',
            'settings.*.description' => 'sometimes|nullable|string|max:500'
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'settings.required' => 'Settings array is required',
            'settings.array' => 'Settings must be an array',
            'settings.min' => 'At least one setting is required',
            'settings.*.key.required' => 'Setting key is required for each setting',
            'settings.*.key.string' => 'Setting key must be a string',
            'settings.*.key.max' => 'Setting key cannot exceed 255 characters',
            'settings.*.value.required' => 'Setting value is required for each setting',
            'settings.*.type.in' => 'Setting type must be one of: string, integer, boolean, json, file_url',
            'settings.*.category.string' => 'Category must be a string',
            'settings.*.category.max' => 'Category cannot exceed 255 characters',
            'settings.*.description.string' => 'Description must be a string',
            'settings.*.description.max' => 'Description cannot exceed 500 characters'
        ];
    }

    /**
     * Get custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'settings' => 'settings',
            'settings.*.key' => 'setting key',
            'settings.*.value' => 'setting value',
            'settings.*.type' => 'data type',
            'settings.*.category' => 'category',
            'settings.*.description' => 'description'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values for each setting if not provided
        if ($this->has('settings') && is_array($this->settings)) {
            $settings = $this->settings;

            foreach ($settings as $index => $setting) {
                $settings[$index]['type'] = $setting['type'] ?? 'string';
                $settings[$index]['category'] = $setting['category'] ?? 'general';
            }

            $this->merge(['settings' => $settings]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for duplicate keys in the same request
            if ($this->has('settings') && is_array($this->settings)) {
                $keys = collect($this->settings)->pluck('key')->filter();
                $duplicates = $keys->duplicates();

                if ($duplicates->isNotEmpty()) {
                    $validator->errors()->add(
                        'settings',
                        'Duplicate keys found in request: ' . $duplicates->implode(', ')
                    );
                }
            }
        });
    }
}
