<?php

namespace App\Http\Requests\AdmissionNumber;

use Illuminate\Foundation\Http\FormRequest;

class GenerateBatchRequest extends FormRequest
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
            'count' => 'required|integer|min:1|max:100'
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'count.required' => 'Count is required',
            'count.integer' => 'Count must be an integer',
            'count.min' => 'Count must be at least 1',
            'count.max' => 'Count cannot exceed 100 admission numbers at once'
        ];
    }

    /**
     * Get custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'count' => 'number of admission numbers'
        ];
    }
}
