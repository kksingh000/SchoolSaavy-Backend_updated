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
        // Get school_id from middleware payload, not from user input
        $schoolId = request()->input('school_id');
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
            'class_id' => 'required|exists:classes,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'components' => 'required|array|min:1',
            'components.*.master_component_id' => [
                'required',
                'exists:master_fee_components,id',
                function ($attribute, $value, $fail) {
                    // Check for duplicates within the same request
                    $allComponentIds = collect($this->input('components', []))
                        ->pluck('master_component_id')
                        ->filter()
                        ->toArray();
                    
                    $duplicates = array_diff_assoc($allComponentIds, array_unique($allComponentIds));
                    
                    if (in_array($value, $duplicates)) {
                        $fail('Each master component can only be used once per fee structure.');
                    }
                }
            ],
            'components.*.custom_name' => 'nullable|string|max:255',
            'components.*.amount' => 'required|numeric|min:0|max:999999.99',
            'components.*.frequency' => ['required', 'string', Rule::in(['Monthly', 'Quarterly', 'Yearly', 'One-Time'])],
            'components.*.required' => 'required|boolean',
            'description' => 'nullable|string|max:1000',
        ];

        // For updates, make some fields optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = str_replace('required|', 'sometimes|required|', $rules['name']);
            $rules['class_id'] = str_replace('required|', 'sometimes|required|', $rules['class_id']);
            $rules['academic_year_id'] = str_replace('required|', 'sometimes|required|', $rules['academic_year_id']);
            $rules['components'] = 'sometimes|' . $rules['components'];
            // Keep is_required as required when components are provided
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'name.required' => 'Fee structure name is required',
            'name.unique' => 'Fee structure name already exists for this school',
            'name.max' => 'Fee structure name cannot exceed 255 characters',
            'class_id.required' => 'Class is required',
            'class_id.exists' => 'Selected class does not exist',
            'academic_year_id.required' => 'Academic year is required',
            'academic_year_id.exists' => 'Selected academic year does not exist',
            'components.required' => 'At least one fee component is required',
            'components.array' => 'Fee components must be an array',
            'components.min' => 'At least one fee component is required',
            'components.*.master_component_id.required' => 'Master component ID is required',
            'components.*.master_component_id.exists' => 'Selected master component does not exist',
            'components.*.custom_name.max' => 'Custom component name cannot exceed 255 characters',
            'components.*.amount.required' => 'Fee component amount is required',
            'components.*.amount.numeric' => 'Fee component amount must be a valid number',
            'components.*.amount.min' => 'Fee component amount cannot be negative',
            'components.*.amount.max' => 'Fee component amount cannot exceed 999999.99',
            'components.*.frequency.required' => 'Fee component frequency is required',
            'components.*.frequency.in' => 'Fee component frequency must be one of: Monthly, Quarterly, Yearly, One-Time',
            'components.*.required.required' => 'Component requirement status is required',
            'components.*.required.boolean' => 'Component requirement status must be true or false',
            'description.max' => 'Description cannot exceed 1000 characters',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate class belongs to the same school
            if ($this->has('class_id') && $this->class_id) {
                // Get school_id from middleware payload, not from user input
                $schoolId = request()->input('school_id');
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

            // Validate academic year belongs to the same school
            if ($this->has('academic_year_id') && $this->academic_year_id) {
                $schoolId = request()->input('school_id');
                $academicYearExists = \App\Models\AcademicYear::where('id', $this->academic_year_id)
                    ->where('school_id', $schoolId)
                    ->exists();

                if (!$academicYearExists) {
                    $validator->errors()->add(
                        'academic_year_id',
                        'Selected academic year does not belong to your school'
                    );
                }
            }

            // Validate master components are active and no duplicates
            if ($this->has('components') && is_array($this->components)) {
                $masterComponentIds = [];
                
                foreach ($this->components as $index => $component) {
                    if (isset($component['master_component_id'])) {
                        $masterComponentId = $component['master_component_id'];
                        
                        // Check for duplicates
                        if (in_array($masterComponentId, $masterComponentIds)) {
                            $validator->errors()->add(
                                "components.{$index}.master_component_id",
                                'Duplicate master component detected. Each component can only be used once per fee structure.'
                            );
                        } else {
                            $masterComponentIds[] = $masterComponentId;
                        }
                        
                        // Check if master component is active
                        $masterComponent = \App\Models\MasterFeeComponent::where('id', $masterComponentId)
                            ->where('is_active', true)
                            ->first();

                        if (!$masterComponent) {
                            $validator->errors()->add(
                                "components.{$index}.master_component_id",
                                'Selected master component is not active or does not exist'
                            );
                        }
                    }
                }
            }
        });
    }
}
