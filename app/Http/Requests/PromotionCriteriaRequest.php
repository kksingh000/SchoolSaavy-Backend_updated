<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromotionCriteriaRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Authorization handled by middleware
    }

    public function rules()
    {
        return [
            'academic_year_id' => 'required|exists:academic_years,id',
            'from_class_id' => 'required|exists:classes,id',
            'to_class_id' => 'sometimes|nullable|exists:classes,id',
            'minimum_attendance_percentage' => 'sometimes|numeric|min:0|max:100',
            'minimum_assignment_average' => 'sometimes|numeric|min:0|max:100',
            'minimum_assessment_average' => 'sometimes|numeric|min:0|max:100',
            'minimum_overall_percentage' => 'sometimes|numeric|min:0|max:100',
            'promotion_weightages' => 'sometimes|array',
            'promotion_weightages.attendance' => 'sometimes|numeric|min:0|max:100',
            'promotion_weightages.assignments' => 'sometimes|numeric|min:0|max:100',
            'promotion_weightages.assessments' => 'sometimes|numeric|min:0|max:100',
            'minimum_attendance_days' => 'sometimes|nullable|integer|min:0',
            'maximum_disciplinary_actions' => 'sometimes|integer|min:0',
            'require_parent_meeting' => 'sometimes|boolean',
            'grace_marks_allowed' => 'sometimes|numeric|min:0|max:10',
            'allow_conditional_promotion' => 'sometimes|boolean',
            'has_remedial_option' => 'sometimes|boolean',
            'remedial_subjects' => 'sometimes|nullable|array',
            'remedial_subjects.*' => 'string'
        ];
    }

    public function messages()
    {
        return [
            'academic_year_id.required' => 'Academic year is required',
            'academic_year_id.exists' => 'Selected academic year does not exist',
            'from_class_id.required' => 'Source class is required',
            'from_class_id.exists' => 'Selected source class does not exist',
            'to_class_id.exists' => 'Selected target class does not exist',
            'minimum_attendance_percentage.numeric' => 'Minimum attendance percentage must be a number',
            'minimum_attendance_percentage.min' => 'Minimum attendance percentage cannot be less than 0',
            'minimum_attendance_percentage.max' => 'Minimum attendance percentage cannot be more than 100',
            'minimum_assignment_average.numeric' => 'Minimum assignment average must be a number',
            'minimum_assignment_average.min' => 'Minimum assignment average cannot be less than 0',
            'minimum_assignment_average.max' => 'Minimum assignment average cannot be more than 100',
            'minimum_assessment_average.numeric' => 'Minimum assessment average must be a number',
            'minimum_assessment_average.min' => 'Minimum assessment average cannot be less than 0',
            'minimum_assessment_average.max' => 'Minimum assessment average cannot be more than 100',
            'minimum_overall_percentage.numeric' => 'Minimum overall percentage must be a number',
            'minimum_overall_percentage.min' => 'Minimum overall percentage cannot be less than 0',
            'minimum_overall_percentage.max' => 'Minimum overall percentage cannot be more than 100',
            'promotion_weightages.array' => 'Promotion weightages must be provided as an array',
            'grace_marks_allowed.numeric' => 'Grace marks must be a number',
            'grace_marks_allowed.max' => 'Grace marks cannot exceed 10'
        ];
    }

    public function attributes()
    {
        return [
            'academic_year_id' => 'academic year',
            'from_class_id' => 'source class',
            'to_class_id' => 'target class',
            'minimum_attendance_percentage' => 'minimum attendance percentage',
            'minimum_assignment_average' => 'minimum assignment average',
            'minimum_assessment_average' => 'minimum assessment average',
            'minimum_overall_percentage' => 'minimum overall percentage',
            'promotion_weightages' => 'promotion weightages',
            'minimum_attendance_days' => 'minimum attendance days',
            'maximum_disciplinary_actions' => 'maximum disciplinary actions',
            'require_parent_meeting' => 'require parent meeting',
            'grace_marks_allowed' => 'grace marks allowed',
            'allow_conditional_promotion' => 'allow conditional promotion',
            'has_remedial_option' => 'has remedial option',
            'remedial_subjects' => 'remedial subjects'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that promotion weightages add up to 100
            if ($this->has('promotion_weightages')) {
                $weightages = $this->promotion_weightages;
                $total = array_sum($weightages);

                if ($total != 100) {
                    $validator->errors()->add('promotion_weightages', 'Promotion weightages must add up to 100%');
                }
            }

            // Validate that to_class has higher grade level than from_class
            if ($this->has('from_class_id') && $this->has('to_class_id') && $this->to_class_id) {
                $fromClass = \App\Models\ClassRoom::find($this->from_class_id);
                $toClass = \App\Models\ClassRoom::find($this->to_class_id);

                if ($fromClass && $toClass && $toClass->grade_level <= $fromClass->grade_level) {
                    $validator->errors()->add('to_class_id', 'Target class must have a higher grade level than source class');
                }
            }
        });
    }
}
