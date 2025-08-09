<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGalleryAlbumRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Handle authorization in controller or middleware
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'event_date' => 'required|date',
            'is_public' => 'boolean',
            'status' => 'in:active,inactive,archived',
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'Album title is required.',
            'title.max' => 'Album title cannot exceed 255 characters.',
            'event_date.required' => 'Event date is required.',
            'event_date.date' => 'Please provide a valid event date.',
            'status.in' => 'Status must be one of: active, inactive, archived.',
        ];
    }
}
