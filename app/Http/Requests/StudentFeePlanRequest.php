<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentFeePlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'student_id' => [
                'required',
                'exists:students,id',
                // Add unique validation to prevent duplicates
                Rule::unique('student_fee_plans')
                    ->where(function ($query) {
                        return $query->where('school_id', request()->input('school_id'))
                                    ->where('student_id', request()->input('student_id'))
                                    ->where('fee_structure_id', request()->input('fee_structure_id'));
                    })
                    ->ignore($this->route('id'))
            ],
            'fee_structure_id' => 'required|exists:fee_structures,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'components' => 'nullable|array',
            'components.*.component_id' => 'required|exists:fee_structure_components,id',
            'components.*.is_active' => 'boolean',
            'components.*.custom_amount' => 'nullable|numeric|min:0|max:999999.99',
        ];

        // For updates, make some fields optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['student_id'] = 'sometimes|exists:students,id';
            $rules['fee_structure_id'] = 'sometimes|exists:fee_structures,id';
        }

        return $rules;
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Student is required',
            'student_id.exists' => 'Selected student does not exist',
            'student_id.unique' => 'A fee plan already exists for this student with the same fee structure',
            'fee_structure_id.required' => 'Fee structure is required',
            'fee_structure_id.exists' => 'Selected fee structure does not exist',
            'start_date.date' => 'Start date must be a valid date',
            'end_date.date' => 'End date must be a valid date',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
            'components.array' => 'Components must be an array',
            'components.*.component_id.required' => 'Component ID is required',
            'components.*.component_id.exists' => 'Selected component does not exist',
            'components.*.is_active.boolean' => 'Is active flag must be true or false',
            'components.*.custom_amount.numeric' => 'Custom amount must be a valid number',
            'components.*.custom_amount.min' => 'Custom amount cannot be negative',
            'components.*.custom_amount.max' => 'Custom amount cannot exceed 999999.99',
        ];
    }

    /**
     * Additional validation checks
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $schoolId = request()->input('school_id');

            // Validate student belongs to school
            if ($this->has('student_id')) {
                $studentExists = \App\Models\Student::where('id', $this->student_id)
                    ->where('school_id', $schoolId)
                    ->exists();

                if (!$studentExists) {
                    $validator->errors()->add(
                        'student_id',
                        'Selected student does not belong to your school'
                    );
                }
            }

            // Validate fee structure belongs to school
            if ($this->has('fee_structure_id')) {
                $feeStructureExists = \App\Models\FeeStructure::where('id', $this->fee_structure_id)
                    ->where('school_id', $schoolId)
                    ->exists();

                if (!$feeStructureExists) {
                    $validator->errors()->add(
                        'fee_structure_id',
                        'Selected fee structure does not belong to your school'
                    );
                }
            }

            // Validate components belong to the fee structure
            if ($this->has('components') && $this->has('fee_structure_id')) {
                $componentIds = collect($this->components)->pluck('component_id')->toArray();
                
                if (!empty($componentIds)) {
                    $validComponentCount = \App\Models\FeeStructureComponent::whereIn('id', $componentIds)
                        ->where('fee_structure_id', $this->fee_structure_id)
                        ->count();
                    
                    if ($validComponentCount !== count($componentIds)) {
                        $validator->errors()->add(
                            'components',
                            'Some components do not belong to the selected fee structure'
                        );
                    }
                }
            }
        });
    }
}
