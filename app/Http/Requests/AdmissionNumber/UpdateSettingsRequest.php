<?php

namespace App\Http\Requests\AdmissionNumber;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
            'prefix' => 'sometimes|nullable|string|max:10',
            'format' => 'sometimes|in:sequential,year_sequential',
            'start_from' => 'sometimes|integer|min:1',
            'include_year' => 'sometimes|boolean',
            'year_format' => 'sometimes|in:YYYY,YY',
            'padding_length' => 'sometimes|integer|min:1|max:10'
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'prefix.string' => 'Prefix must be a string',
            'prefix.max' => 'Prefix cannot exceed 10 characters',
            'format.in' => 'Format must be either "sequential" or "year_sequential"',
            'start_from.integer' => 'Start from must be an integer',
            'start_from.min' => 'Start from must be at least 1',
            'include_year.boolean' => 'Include year must be true or false',
            'year_format.in' => 'Year format must be either "YYYY" or "YY"',
            'padding_length.integer' => 'Padding length must be an integer',
            'padding_length.min' => 'Padding length must be at least 1',
            'padding_length.max' => 'Padding length cannot exceed 10 digits'
        ];
    }

    /**
     * Get custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'prefix' => 'admission number prefix',
            'format' => 'numbering format',
            'start_from' => 'starting number',
            'include_year' => 'year inclusion setting',
            'year_format' => 'year display format',
            'padding_length' => 'number padding length'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If include_year is true, year_format should be provided
            if ($this->include_year === true && !$this->has('year_format')) {
                $validator->errors()->add(
                    'year_format',
                    'Year format is required when year inclusion is enabled'
                );
            }

            // If year_sequential format is chosen, suggest including year
            if ($this->format === 'year_sequential' && $this->include_year === false) {
                // This is just a warning, not an error
                // Could be logged or added as a notice
            }
        });
    }
}
