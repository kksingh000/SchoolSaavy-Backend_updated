<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FileUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $maxFileSize = config('upload.max_file_size'); // Gets from config/upload.php
        $maxFiles = config('upload.max_files_per_upload');

        // Convert bytes to KB for Laravel validation
        $maxFileSizeKB = $maxFileSize / 1024;

        $allowedTypes = array_merge(
            config('upload.allowed_image_types'),
            config('upload.allowed_video_types'),
            config('upload.allowed_document_types')
        );

        return [
            'files' => "required|array|max:{$maxFiles}",
            'files.*' => [
                'required',
                'file',
                "max:{$maxFileSizeKB}", // Max size in KB
                function ($attribute, $value, $fail) use ($allowedTypes) {
                    if (!in_array($value->getMimeType(), $allowedTypes)) {
                        $fail('The file type is not allowed.');
                    }
                },
            ],

            // For single file upload
            'file' => [
                'sometimes',
                'required',
                'file',
                "max:{$maxFileSizeKB}",
                function ($attribute, $value, $fail) use ($allowedTypes) {
                    if (!in_array($value->getMimeType(), $allowedTypes)) {
                        $fail('The file type is not allowed.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $maxFileSizeMB = config('upload.max_file_size') / (1024 * 1024);
        $maxFiles = config('upload.max_files_per_upload');

        return [
            'files.required' => 'Please select at least one file to upload.',
            'files.array' => 'Files must be provided as an array.',
            'files.max' => "You can upload a maximum of {$maxFiles} files at once.",
            'files.*.required' => 'Each file is required.',
            'files.*.file' => 'Each item must be a valid file.',
            'files.*.max' => "Each file must not exceed {$maxFileSizeMB}MB.",
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The uploaded item must be a valid file.',
            'file.max' => "The file must not exceed {$maxFileSizeMB}MB.",
        ];
    }
}
