<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddGalleryMediaRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Handle authorization in controller or middleware
    }

    public function rules()
    {
        return [
            'media_files' => 'required|array|min:1|max:10', // Limit to 10 files when adding to existing album
            'media_files.*' => 'required|file|mimes:jpeg,jpg,png,gif,mp4,mov,avi,wmv|max:20480', // 20MB max per file
        ];
    }

    public function messages()
    {
        return [
            'media_files.required' => 'Please select at least one media file.',
            'media_files.array' => 'Media files must be provided as an array.',
            'media_files.min' => 'Please select at least one media file.',
            'media_files.max' => 'You can upload maximum 10 files at once.',
            'media_files.*.required' => 'Each media file is required.',
            'media_files.*.file' => 'Each upload must be a valid file.',
            'media_files.*.mimes' => 'Only JPEG, JPG, PNG, GIF, MP4, MOV, AVI, and WMV files are allowed.',
            'media_files.*.max' => 'Each file cannot exceed 20MB.',
        ];
    }
}
