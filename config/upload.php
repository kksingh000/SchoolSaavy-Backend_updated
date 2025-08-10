<?php

return [
    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure the file upload settings for your application.
    |
    */

    'max_file_size' => env('MAX_FILE_SIZE', 104857600), // 100MB in bytes (updated from 50MB)
    'max_files_per_upload' => env('MAX_FILES_PER_UPLOAD', 20), // Updated from 10 to 20

    'allowed_image_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp'
    ],

    'allowed_video_types' => [
        'video/mp4',
        'video/avi',
        'video/mov',
        'video/wmv',
        'video/flv',
        'video/webm'
    ],

    'allowed_document_types' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ],

    // Storage configuration
    'storage_disk' => env('UPLOAD_DISK', 's3'),
    'media_url' => env('MEDIA_URL', 'https://schoolsaavy.s3.ap-south-1.amazonaws.com')
];
