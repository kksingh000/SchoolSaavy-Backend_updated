<?php

namespace App\Http\Requests\RollNumber;

use Illuminate\Foundation\Http\FormRequest;

class GenerateBulkRequest extends FormRequest
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
            'class_id' => 'required|integer|exists:classes,id',
            'count' => 'required|integer|min:1|max:100',
            'fill_gaps' => 'boolean'
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'class_id.required' => 'Class ID is required',
            'class_id.integer' => 'Class ID must be an integer',
            'class_id.exists' => 'The selected class does not exist',
            'count.required' => 'Count is required',
            'count.integer' => 'Count must be an integer',
            'count.min' => 'Count must be at least 1',
            'count.max' => 'Count cannot exceed 100 roll numbers at once',
            'fill_gaps.boolean' => 'Fill gaps must be true or false'
        ];
    }

    /**
     * Get custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'class_id' => 'class',
            'count' => 'number of roll numbers',
            'fill_gaps' => 'gap filling option'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that the class belongs to the current school
            if ($this->class_id) {
                $class = \App\Models\ClassRoom::where('id', $this->class_id)
                    ->where('school_id', request()->school_id)
                    ->where('is_active', true)
                    ->first();

                if (!$class) {
                    $validator->errors()->add(
                        'class_id',
                        'The selected class does not belong to your school or is inactive'
                    );
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default value for fill_gaps
        $this->merge([
            'fill_gaps' => $this->fill_gaps ?? true,
        ]);
    }
}
