<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGalleryAlbumRequest extends FormRequest
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
            'class_id' => 'required|exists:classes,id',
            'event_id' => 'nullable|exists:events,id',
            'event_date' => 'required|date',
            'is_public' => 'boolean',
            'media_files' => 'required|array|min:1|max:20', // Limit to 20 files per upload
            'media_files.*' => 'required|file|mimes:jpeg,jpg,png,gif,mp4,mov,avi,wmv|max:20480', // 20MB max per file
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'Album title is required.',
            'title.max' => 'Album title cannot exceed 255 characters.',
            'class_id.required' => 'Please select a class.',
            'class_id.exists' => 'Selected class is invalid.',
            'event_id.exists' => 'Selected event is invalid.',
            'event_date.required' => 'Event date is required.',
            'event_date.date' => 'Please provide a valid event date.',
            'media_files.required' => 'Please select at least one media file.',
            'media_files.array' => 'Media files must be provided as an array.',
            'media_files.min' => 'Please select at least one media file.',
            'media_files.max' => 'You can upload maximum 20 files at once.',
            'media_files.*.required' => 'Each media file is required.',
            'media_files.*.file' => 'Each upload must be a valid file.',
            'media_files.*.mimes' => 'Only JPEG, JPG, PNG, GIF, MP4, MOV, AVI, and WMV files are allowed.',
            'media_files.*.max' => 'Each file cannot exceed 20MB.',
        ];
    }
}
