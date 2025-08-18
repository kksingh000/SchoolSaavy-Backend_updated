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
            'status' => 'sometimes|string|in:active,inactive,archived',
            'media_files' => 'required|array|min:1|max:20', // Limit to 20 files per upload
            'media_files.*.file_path' => 'required|string', // S3 path to the uploaded file
            'media_files.*.original_name' => 'required|string|max:255', // Original filename for display
            'media_files.*.mime_type' => 'required|string', // MIME type of the file
            'media_files.*.file_size' => 'required|integer|min:1', // File size in bytes
            'media_files.*.type' => 'sometimes|string|in:photo,video', // Media type (auto-determined if not provided)
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $mediaFiles = $this->input('media_files', []);

        foreach ($mediaFiles as $index => $file) {
            // Auto-determine type if not provided
            if (!isset($file['type']) && isset($file['mime_type'])) {
                $mediaFiles[$index]['type'] = $this->determineMediaType($file['mime_type']);
            }
        }

        $this->merge(['media_files' => $mediaFiles]);
    }

    /**
     * Determine media type from MIME type
     */
    private function determineMediaType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'photo';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        // Default to photo for unknown types
        return 'photo';
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
            'status.in' => 'Status must be one of: active, inactive, archived.',
            'media_files.required' => 'Please provide at least one media file.',
            'media_files.array' => 'Media files must be provided as an array.',
            'media_files.min' => 'Please provide at least one media file.',
            'media_files.max' => 'You can add maximum 20 files at once.',
            'media_files.*.file_path.required' => 'File path is required for each media file.',
            'media_files.*.file_path.string' => 'File path must be a valid string.',
            'media_files.*.original_name.required' => 'Original filename is required for each media file.',
            'media_files.*.original_name.string' => 'Original filename must be a valid string.',
            'media_files.*.original_name.max' => 'Original filename cannot exceed 255 characters.',
            'media_files.*.mime_type.required' => 'MIME type is required for each media file.',
            'media_files.*.mime_type.string' => 'MIME type must be a valid string.',
            'media_files.*.file_size.required' => 'File size is required for each media file.',
            'media_files.*.file_size.integer' => 'File size must be a valid number.',
            'media_files.*.file_size.min' => 'File size must be greater than 0.',
            'media_files.*.type.required' => 'Media type is required for each file.',
            'media_files.*.type.string' => 'Media type must be a valid string.',
            'media_files.*.type.in' => 'Media type must be either "photo" or "video".',
        ];
    }
}
