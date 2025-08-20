<?php

namespace App\Http\Requests\SchoolSetting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolSettingRequest extends FormRequest
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
        // Auto-detect type if not provided
        if (!$this->has('type')) {
            $value = $this->input('value');
            $detectedType = $this->detectValueType($value);
            $this->merge(['type' => $detectedType]);
        }

        // Set default category if not provided
        $this->merge([
            'category' => $this->category ?? 'general',
        ]);
    }

    /**
     * Auto-detect the type of a value
     */
    private function detectValueType($value): string
    {
        if (is_bool($value) || $value === 'true' || $value === 'false') {
            return 'boolean';
        }

        if (is_numeric($value) && is_int($value + 0)) {
            return 'integer';
        }

        if (is_array($value) || (is_string($value) && json_decode($value) !== null)) {
            return 'json';
        }

        if (is_string($value) && (str_starts_with($value, 'http') || str_starts_with($value, 'uploads/'))) {
            return 'file_url';
        }

        return 'string';
    }
}
