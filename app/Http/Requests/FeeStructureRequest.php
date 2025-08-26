<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeeStructureRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Authorization handled by middleware
    }

    public function rules()
    {
        $schoolId = $this->input('school_id') ?? request()->input('school_id');
        $feeStructureId = $this->route('id');

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('fee_structures')->where(function ($query) use ($schoolId) {
                    return $query->where('school_id', $schoolId);
                })->ignore($feeStructureId)
            ],
            'class_id' => 'nullable|exists:classes,id',
            'fee_components' => 'required|array|min:1',
            'fee_components.*.type' => 'required|string|max:100',
            'fee_components.*.name' => 'required|string|max:255',
            'fee_components.*.amount' => 'required|numeric|min:0|max:999999.99',
            'fee_components.*.due_date' => 'nullable|date|after_or_equal:today',
            'fee_components.*.is_mandatory' => 'sometimes|boolean',
            'fee_components.*.description' => 'nullable|string|max:500',
            'total_amount' => 'required|numeric|min:0|max:999999.99',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string|max:1000',
        ];

        // For updates, make some fields optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = str_replace('required|', 'sometimes|required|', $rules['name']);
            $rules['fee_components'] = 'sometimes|' . $rules['fee_components'];
            $rules['total_amount'] = 'sometimes|' . $rules['total_amount'];
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'name.required' => 'Fee structure name is required',
            'name.unique' => 'Fee structure name already exists for this school',
            'name.max' => 'Fee structure name cannot exceed 255 characters',
            'class_id.exists' => 'Selected class does not exist',
            'fee_components.required' => 'At least one fee component is required',
            'fee_components.array' => 'Fee components must be an array',
            'fee_components.min' => 'At least one fee component is required',
            'fee_components.*.type.required' => 'Fee component type is required',
            'fee_components.*.type.max' => 'Fee component type cannot exceed 100 characters',
            'fee_components.*.name.required' => 'Fee component name is required',
            'fee_components.*.name.max' => 'Fee component name cannot exceed 255 characters',
            'fee_components.*.amount.required' => 'Fee component amount is required',
            'fee_components.*.amount.numeric' => 'Fee component amount must be a valid number',
            'fee_components.*.amount.min' => 'Fee component amount cannot be negative',
            'fee_components.*.amount.max' => 'Fee component amount cannot exceed 999999.99',
            'fee_components.*.due_date.date' => 'Due date must be a valid date',
            'fee_components.*.due_date.after_or_equal' => 'Due date cannot be in the past',
            'fee_components.*.description.max' => 'Fee component description cannot exceed 500 characters',
            'total_amount.required' => 'Total amount is required',
            'total_amount.numeric' => 'Total amount must be a valid number',
            'total_amount.min' => 'Total amount cannot be negative',
            'total_amount.max' => 'Total amount cannot exceed 999999.99',
            'is_active.boolean' => 'Active status must be true or false',
            'description.max' => 'Description cannot exceed 1000 characters',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'fee structure name',
            'class_id' => 'class',
            'fee_components' => 'fee components',
            'fee_components.*.type' => 'fee component type',
            'fee_components.*.name' => 'fee component name',
            'fee_components.*.amount' => 'fee component amount',
            'fee_components.*.due_date' => 'fee component due date',
            'fee_components.*.description' => 'fee component description',
            'total_amount' => 'total amount',
            'is_active' => 'active status',
            'description' => 'description',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that total_amount equals sum of fee_components amounts
            if ($this->has('fee_components') && $this->has('total_amount')) {
                $componentsTotal = collect($this->fee_components)->sum('amount');
                $totalAmount = (float) $this->total_amount;

                if (abs($componentsTotal - $totalAmount) > 0.01) {
                    $validator->errors()->add(
                        'total_amount',
                        'Total amount must equal the sum of all fee component amounts'
                    );
                }
            }

            // Validate class belongs to the same school
            if ($this->has('class_id') && $this->class_id) {
                $schoolId = $this->input('school_id') ?? request()->input('school_id');
                $classExists = \App\Models\ClassRoom::where('id', $this->class_id)
                    ->where('school_id', $schoolId)
                    ->exists();

                if (!$classExists) {
                    $validator->errors()->add(
                        'class_id',
                        'Selected class does not belong to your school'
                    );
                }
            }
        });
    }
}
