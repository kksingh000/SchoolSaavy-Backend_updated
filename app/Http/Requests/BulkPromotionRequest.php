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
            'target_class_ids' => 'sometimes|nullable|array',
            'target_class_ids.*' => 'exists:classes,id',
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
            'class_ids.array' => 'Source classes must be provided as an array',
            'class_ids.*.exists' => 'One or more selected source classes do not exist',
            'target_class_ids.array' => 'Target classes must be provided as an array',
            'target_class_ids.*.exists' => 'One or more selected target classes do not exist',
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
            'class_ids' => 'source classes',
            'target_class_ids' => 'target classes',
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
            $sourceClassIds = $this->class_ids;
            $targetClassIds = $this->target_class_ids;

            // If source classes are provided but no target classes specified
            if (!empty($sourceClassIds) && empty($targetClassIds)) {
                // Check if all source classes have predefined promotion paths
                $sourceClasses = \App\Models\ClassRoom::whereIn('id', $sourceClassIds)->get();
                $classesWithoutPromotionPath = $sourceClasses->filter(function ($class) {
                    return !$class->promotes_to_class_id;
                });

                if ($classesWithoutPromotionPath->isNotEmpty()) {
                    $classNames = $classesWithoutPromotionPath->pluck('name')->implode(', ');
                    $validator->errors()->add(
                        'target_class_ids',
                        "Target classes are required because the following classes don't have predefined promotion paths: {$classNames}"
                    );
                }
            }

            // Validate class promotion logic when both are provided
            if (!empty($sourceClassIds) && !empty($targetClassIds)) {
                // Check that target classes are not the same as source classes
                $overlap = array_intersect($sourceClassIds, $targetClassIds);
                if (!empty($overlap)) {
                    $validator->errors()->add('target_class_ids', 'Target classes cannot be the same as source classes');
                }

                // Validate that we have proper promotion mapping
                $sourceClasses = \App\Models\ClassRoom::whereIn('id', $sourceClassIds)->get();
                $targetClasses = \App\Models\ClassRoom::whereIn('id', $targetClassIds)->get();

                // Check that target classes have higher grade levels
                foreach ($sourceClasses as $sourceClass) {
                    $hasValidTarget = false;
                    foreach ($targetClasses as $targetClass) {
                        if ($targetClass->grade_level > $sourceClass->grade_level) {
                            $hasValidTarget = true;
                            break;
                        }
                    }
                    if (!$hasValidTarget) {
                        $validator->errors()->add('target_class_ids', "No valid promotion target found for class {$sourceClass->name}. Target classes should have higher grade levels.");
                        break;
                    }
                }
            }
        });
    }
}
