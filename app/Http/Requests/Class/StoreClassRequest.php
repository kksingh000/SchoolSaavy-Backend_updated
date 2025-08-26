<?php

namespace App\Http\Requests\Class;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'grade_level' => 'required|integer|min:1|max:12',
            'section' => 'nullable|string|max:10',
            'capacity' => 'required|integer|min:1',
            'teacher_id' => 'nullable|exists:teachers,id',
            'description' => 'nullable|string',
            'promotes_to_class_id' => 'nullable|exists:classes,id',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'The class name is required.',
            'grade_level.required' => 'The grade level is required.',
            'grade_level.min' => 'Grade level must be at least 1.',
            'grade_level.max' => 'Grade level cannot exceed 12.',
            'capacity.required' => 'The class capacity is required.',
            'capacity.min' => 'Capacity must be at least 1.',
            'teacher_id.exists' => 'The selected teacher does not exist.',
            'promotes_to_class_id.exists' => 'The selected promotion target class does not exist.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $promotesToClassId = $this->promotes_to_class_id;
            $gradeLevel = $this->grade_level;

            // If promotion target is set, validate it has higher grade level
            if ($promotesToClassId) {
                $targetClass = \App\Models\ClassRoom::find($promotesToClassId);
                if ($targetClass && $targetClass->grade_level <= $gradeLevel) {
                    $validator->errors()->add(
                        'promotes_to_class_id',
                        'The promotion target class must have a higher grade level than the current class.'
                    );
                }
            }
        });
    }
}
