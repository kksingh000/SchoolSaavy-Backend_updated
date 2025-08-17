<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkPromotionRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Authorization handled by middleware
    }

    public function rules()
    {
        return [
            'academic_year_id' => 'required|exists:academic_years,id',
            'class_ids' => 'sometimes|nullable|array',
            'class_ids.*' => 'exists:classes,id',
            'batch_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:500',
            'notify_parents' => 'sometimes|boolean',
            'auto_apply_promotions' => 'sometimes|boolean'
        ];
    }

    public function messages()
    {
        return [
            'academic_year_id.required' => 'Academic year is required',
            'academic_year_id.exists' => 'Selected academic year does not exist',
            'class_ids.array' => 'Classes must be provided as an array',
            'class_ids.*.exists' => 'One or more selected classes do not exist',
            'batch_name.string' => 'Batch name must be a string',
            'batch_name.max' => 'Batch name cannot exceed 255 characters',
            'description.string' => 'Description must be a string',
            'description.max' => 'Description cannot exceed 500 characters',
            'notify_parents.boolean' => 'Notify parents must be true or false',
            'auto_apply_promotions.boolean' => 'Auto apply promotions must be true or false'
        ];
    }

    public function attributes()
    {
        return [
            'academic_year_id' => 'academic year',
            'class_ids' => 'classes',
            'batch_name' => 'batch name',
            'description' => 'description',
            'notify_parents' => 'notify parents',
            'auto_apply_promotions' => 'auto apply promotions'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that selected classes belong to the current school
            if ($this->has('class_ids') && is_array($this->class_ids)) {
                $schoolId = auth()->user()->getSchool()->id;
                $validClasses = \App\Models\ClassRoom::whereIn('id', $this->class_ids)
                    ->where('school_id', $schoolId)
                    ->pluck('id')
                    ->toArray();

                $invalidClasses = array_diff($this->class_ids, $validClasses);

                if (!empty($invalidClasses)) {
                    $validator->errors()->add('class_ids', 'Some selected classes do not belong to your school');
                }
            }

            // Validate that academic year belongs to current school
            if ($this->has('academic_year_id')) {
                $schoolId = auth()->user()->getSchool()->id;
                $academicYear = \App\Models\AcademicYear::find($this->academic_year_id);

                if ($academicYear && $academicYear->school_id != $schoolId) {
                    $validator->errors()->add('academic_year_id', 'Selected academic year does not belong to your school');
                }
            }
        });
    }
}
