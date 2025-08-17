<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcademicYearRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Authorization handled by middleware
    }

    public function rules()
    {
        $rules = [
            'year_label' => 'required|string|max:20|regex:/^\d{4}-\d{2}$/',
            'display_name' => 'sometimes|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'promotion_start_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'promotion_end_date' => 'sometimes|nullable|date|after_or_equal:promotion_start_date',
            'is_current' => 'sometimes|boolean',
            'status' => 'sometimes|in:upcoming,active,promotion_period,completed',
            'settings' => 'sometimes|array'
        ];

        // For updates, make some fields optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['year_label'] = 'sometimes|' . $rules['year_label'];
            $rules['start_date'] = 'sometimes|' . $rules['start_date'];
            $rules['end_date'] = 'sometimes|' . $rules['end_date'];
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'year_label.required' => 'Academic year label is required',
            'year_label.regex' => 'Academic year must be in format YYYY-YY (e.g., 2024-25)',
            'start_date.required' => 'Academic year start date is required',
            'end_date.required' => 'Academic year end date is required',
            'end_date.after' => 'End date must be after start date',
            'promotion_start_date.after_or_equal' => 'Promotion start date must be after or equal to academic year start date',
            'promotion_end_date.after_or_equal' => 'Promotion end date must be after or equal to promotion start date',
            'status.in' => 'Status must be one of: upcoming, active, promotion_period, completed'
        ];
    }

    public function attributes()
    {
        return [
            'year_label' => 'academic year label',
            'display_name' => 'display name',
            'start_date' => 'start date',
            'end_date' => 'end date',
            'promotion_start_date' => 'promotion start date',
            'promotion_end_date' => 'promotion end date',
            'is_current' => 'current status',
            'status' => 'academic year status'
        ];
    }
}
