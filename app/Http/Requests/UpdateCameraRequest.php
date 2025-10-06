<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCameraRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'camera_name' => 'sometimes|required|string|max:255',
            'camera_type' => 'sometimes|required|in:classroom,playground,library,cafeteria,laboratory,auditorium,entrance,other',
            'description' => 'nullable|string|max:1000',
            'stream_url' => 'sometimes|required|url|max:500',
            'rtmp_url' => 'nullable|url|max:500',
            'thumbnail_url' => 'nullable|url|max:500',
            'status' => 'sometimes|required|in:active,inactive,maintenance,offline',
            'privacy_level' => 'sometimes|required|in:public,restricted,private,disabled',
            'room_id' => 'nullable|exists:classes,id',
            'location_description' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'settings' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'camera_name.required' => 'Camera name is required.',
            'camera_name.max' => 'Camera name must not exceed 255 characters.',
            'camera_type.required' => 'Camera type is required.',
            'camera_type.in' => 'Invalid camera type selected.',
            'stream_url.required' => 'Stream URL is required.',
            'stream_url.url' => 'Stream URL must be a valid URL.',
            'privacy_level.required' => 'Privacy level is required.',
            'privacy_level.in' => 'Invalid privacy level selected.',
            'room_id.exists' => 'Selected room does not exist.',
            'latitude.between' => 'Latitude must be between -90 and 90.',
            'longitude.between' => 'Longitude must be between -180 and 180.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that room belongs to the same school
            if ($this->room_id) {
                $room = \App\Models\ClassRoom::find($this->room_id);
                if ($room && $room->school_id !== $this->school_id) {
                    $validator->errors()->add('room_id', 'Selected room does not belong to your school.');
                }
            }

            // Validate stream URL accessibility (basic check)
            if ($this->stream_url) {
                $parsedUrl = parse_url($this->stream_url);
                if (!$parsedUrl || !isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], ['http', 'https', 'rtsp', 'rtmp', 'rtmps'])) {
                    $validator->errors()->add('stream_url', 'Stream URL must use a supported protocol (http, https, rtsp, rtmp, rtmps).');
                }
            }
        });
    }
}