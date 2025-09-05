<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Notification;

class SendNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && in_array(auth()->user()->user_type, ['admin', 'teacher']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'required|string|in:' . implode(',', array_keys(Notification::getTypes())),
            'priority' => 'sometimes|string|in:' . implode(',', array_keys(Notification::getPriorities())),
            'target_type' => 'required|string|in:' . implode(',', array_keys(Notification::getTargetTypes())),
            'target_ids' => 'nullable|array|required_if:target_type,specific_users',
            'target_ids.*' => 'integer|exists:users,id',
            'target_classes' => 'nullable|array|required_if:target_type,class_parents,class_teachers',
            'target_classes.*' => 'integer|exists:classes,id',
            'scheduled_at' => 'nullable|date|after:now', // Made optional - if provided, will schedule; if not, sends immediately
            'data' => 'nullable|array',
            'data.*' => 'string|max:500'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Notification title is required',
            'title.max' => 'Notification title cannot exceed 255 characters',
            'message.required' => 'Notification message is required',
            'message.max' => 'Notification message cannot exceed 1000 characters',
            'type.required' => 'Notification type is required',
            'type.in' => 'Invalid notification type selected',
            'priority.in' => 'Invalid priority level selected',
            'target_type.required' => 'Target type is required',
            'target_type.in' => 'Invalid target type selected',
            'target_ids.required_if' => 'Target users are required when targeting specific users',
            'target_ids.*.exists' => 'One or more selected users do not exist',
            'target_classes.required_if' => 'Target classes are required when targeting class members',
            'target_classes.*.exists' => 'One or more selected classes do not exist',
            'scheduled_at.date' => 'Invalid schedule date format',
            'scheduled_at.after' => 'Schedule date must be in the future',
            'data.*.max' => 'Additional data values cannot exceed 500 characters'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $targetType = $this->input('target_type');

            // Validate target_ids for specific_users
            if ($targetType === 'specific_users') {
                $targetIds = $this->input('target_ids', []);
                if (empty($targetIds)) {
                    $validator->errors()->add('target_ids', 'At least one user must be selected');
                }
            }

            // Validate target_classes for class-based targeting
            if (in_array($targetType, ['class_parents', 'class_teachers'])) {
                $targetClasses = $this->input('target_classes', []);
                if (empty($targetClasses)) {
                    $validator->errors()->add('target_classes', 'At least one class must be selected');
                }
            }

            // Validate scheduled_at is not too far in future (max 1 year) when provided
            $scheduledAt = $this->input('scheduled_at');
            if ($scheduledAt && \Carbon\Carbon::parse($scheduledAt)->gt(\Carbon\Carbon::now()->addYear())) {
                $validator->errors()->add('scheduled_at', 'Schedule date cannot be more than 1 year in the future');
            }
        });
    }
}
