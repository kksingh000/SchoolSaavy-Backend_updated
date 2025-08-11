<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\ThumbnailService;

class FileUploadController extends BaseController
{
    protected ThumbnailService $thumbnailService;

    public function __construct(ThumbnailService $thumbnailService)
    {
        $this->thumbnailService = $thumbnailService;
    }
    /**
     * Set runtime PHP limits for large file uploads
     */
    private function setUploadLimits(): void
    {
        // Set memory limit
        ini_set('memory_limit', '512M');

        // Set execution time limit (5 minutes)
        ini_set('max_execution_time', '300');

        // Set post max size (should be larger than max file size)
        ini_set('post_max_size', '120M');

        // Set upload max filesize
        ini_set('upload_max_filesize', '100M');

        // Set max file uploads
        ini_set('max_file_uploads', '20');
    }

    /**
     * Generate S3 URL for a given path
     */
    private function generateS3Url(string $path): string
    {
        // Always use standard AWS S3 URL format
        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');
        return "https://{$bucket}.s3.{$region}.amazonaws.com/{$path}";
    }

    /**
     * Upload single file
     */
    public function uploadSingle(Request $request): JsonResponse
    {
        // Set runtime limits for large file uploads
        $this->setUploadLimits();

        $maxFileSizeKB = config('upload.max_file_size') / 1024; // Convert bytes to KB for validation

        $validator = Validator::make($request->all(), [
            'file' => "required|file|max:{$maxFileSizeKB}", // Use config value
            'type' => 'required|string|in:assignment,profile,communication,event,general,media',
            'allowed_extensions' => 'sometimes|string', // Custom allowed extensions
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $file = $request->file('file');
            $type = $request->input('type');
            $customExtensions = $request->input('allowed_extensions');

            // Validate file type
            if (!$this->isAllowedFileType($file, $type, $customExtensions)) {
                return $this->errorResponse('File type not allowed', null, 422);
            }

            // Upload file
            $fileData = $this->uploadFile($file, $type);

            return $this->successResponse($fileData, 'File uploaded successfully');
        } catch (\Exception $e) {
            Log::error('File upload error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('File upload failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Upload multiple files
     */
    public function uploadMultiple(Request $request): JsonResponse
    {
        // Set runtime limits for large file uploads
        $this->setUploadLimits();

        $maxFiles = config('upload.max_files_per_upload');
        $maxFileSizeKB = config('upload.max_file_size') / 1024; // Convert bytes to KB for validation

        $validator = Validator::make($request->all(), [
            'files' => "required|array|max:{$maxFiles}", // Use config value
            'files.*' => "file|max:{$maxFileSizeKB}", // Use config value
            'type' => 'required|string|in:assignment,profile,communication,event,general',
            'allowed_extensions' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $files = $request->file('files');
            $type = $request->input('type');
            $customExtensions = $request->input('allowed_extensions');
            $uploadedFiles = [];
            $errors = [];

            foreach ($files as $index => $file) {
                try {
                    // Validate file type
                    if (!$this->isAllowedFileType($file, $type, $customExtensions)) {
                        $errors[] = "File {$index}: File type not allowed";
                        continue;
                    }

                    // Upload file
                    $fileData = $this->uploadFile($file, $type);
                    $uploadedFiles[] = $fileData;
                } catch (\Exception $e) {
                    $errors[] = "File {$index}: " . $e->getMessage();
                }
            }

            if (empty($uploadedFiles) && !empty($errors)) {
                return $this->errorResponse('All files failed to upload', $errors, 422);
            }

            $response = [
                'uploaded_files' => $uploadedFiles,
                'total_uploaded' => count($uploadedFiles),
                'total_failed' => count($errors)
            ];

            if (!empty($errors)) {
                $response['errors'] = $errors;
            }

            return $this->successResponse($response, 'Files processed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Upload failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Delete uploaded file
     */
    public function deleteFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $filePath = $request->input('file_path');

            // Security check: ensure file path is within uploads directory
            if (!str_starts_with($filePath, '/uploads/') && !str_starts_with($filePath, 'uploads/')) {
                return $this->errorResponse('Invalid file path', null, 422);
            }

            // Remove leading slash if present
            $cleanPath = ltrim($filePath, '/');

            // Get upload disk (S3 or local)
            $uploadDisk = config('filesystems.gallery_disk', 'public');

            if ($uploadDisk === 's3') {
                // Delete from S3
                if (Storage::disk('s3')->exists($cleanPath)) {
                    Storage::disk('s3')->delete($cleanPath);
                    return $this->successResponse(null, 'File deleted successfully');
                } else {
                    return $this->errorResponse('File not found', null, 404);
                }
            } else {
                // Delete from local storage
                if (Storage::disk('public')->exists($cleanPath)) {
                    Storage::disk('public')->delete($cleanPath);
                    return $this->successResponse(null, 'File deleted successfully');
                } else {
                    return $this->errorResponse('File not found', null, 404);
                }
            }
        } catch (\Exception $e) {
            return $this->errorResponse('File deletion failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get file info
     */
    public function getFileInfo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $filePath = $request->input('file_path');

            // Security check
            if (!str_starts_with($filePath, '/uploads/') && !str_starts_with($filePath, 'uploads/')) {
                return $this->errorResponse('Invalid file path', null, 422);
            }

            $cleanPath = ltrim($filePath, '/');
            $uploadDisk = config('filesystems.gallery_disk', 'public');

            if ($uploadDisk === 's3') {
                // Get info from S3
                if (Storage::disk('s3')->exists($cleanPath)) {
                    $size = Storage::disk('s3')->size($cleanPath);
                    $lastModified = Storage::disk('s3')->lastModified($cleanPath);
                    $url = $this->generateS3Url($cleanPath);

                    $fileInfo = [
                        'path' => $filePath,
                        'size' => $size,
                        'size_human' => $this->formatBytes($size),
                        'last_modified' => Carbon::createFromTimestamp($lastModified)->toISOString(),
                        'url' => $url
                    ];

                    return $this->successResponse($fileInfo, 'File info retrieved successfully');
                } else {
                    return $this->errorResponse('File not found', null, 404);
                }
            } else {
                // Get info from local storage
                if (Storage::disk('public')->exists($cleanPath)) {
                    $size = Storage::disk('public')->size($cleanPath);
                    $lastModified = Storage::disk('public')->lastModified($cleanPath);

                    $fileInfo = [
                        'path' => $filePath,
                        'size' => $size,
                        'size_human' => $this->formatBytes($size),
                        'last_modified' => Carbon::createFromTimestamp($lastModified)->toISOString(),
                        'url' => asset('storage/' . $cleanPath)
                    ];

                    return $this->successResponse($fileInfo, 'File info retrieved successfully');
                } else {
                    return $this->errorResponse('File not found', null, 404);
                }
            }
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get file info: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Upload file and return file data
     */
    private function uploadFile($file, string $type): array
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Generate unique filename
        $filename = $this->generateUniqueFilename($originalName, $extension);

        // Create directory path based on type and date
        $directory = $this->getUploadDirectory($type);

        // Get upload disk (S3 or local)
        $uploadDisk = config('filesystems.gallery_disk', 'public');

        // Debug logging
        Log::info('Upload Debug:', [
            'uploadDisk' => $uploadDisk,
            'directory' => $directory,
            'filename' => $filename,
            'file_size' => $size
        ]);

        // Store file
        if ($uploadDisk === 's3') {
            // Upload to S3
            Log::info('Attempting S3 upload to path: ' . $directory . '/' . $filename);

            try {
                $path = $file->storeAs($directory, $filename, $uploadDisk);
                Log::info('S3 upload result path: ' . ($path ?: 'FAILED'));

                if (!$path) {
                    throw new \Exception('S3 upload failed - storeAs returned false/null');
                }

                // Verify the file was actually uploaded
                $exists = Storage::disk($uploadDisk)->exists($path);
                Log::info('S3 file exists verification: ' . ($exists ? 'YES' : 'NO') . ' for path: ' . $path);

                if (!$exists) {
                    throw new \Exception('S3 upload verification failed - file does not exist on S3');
                }
            } catch (\Exception $e) {
                Log::error('S3 upload exception: ' . $e->getMessage());
                throw $e;
            }

            // Generate S3 URL
            $url = $this->generateS3Url($path);
            $storedPath = $path; // Store the full S3 path
        } else {
            // Upload to local storage
            Log::info('Attempting local upload');
            $path = $file->storeAs($directory, $filename, 'public');
            $url = asset('storage/' . $path);
            $storedPath = $path; // Store the full local path
        }

        return [
            'name' => $originalName,
            'filename' => $filename,
            'url' => $url,
            'path' => $storedPath,
            'type' => $extension,
            'mime_type' => $mimeType,
            'size' => $size,
            'size_human' => $this->formatBytes($size),
            'uploaded_at' => Carbon::now()->toISOString(),
            'is_image' => $this->thumbnailService->isImageFile($extension),
            'thumbnail_queued' => $this->queueThumbnailIfImage($storedPath, $filename, $extension, $uploadDisk)
        ];
    }

    /**
     * Check if file type is allowed
     */
    private function isAllowedFileType($file, string $type, ?string $customExtensions = null): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());

        // If custom extensions provided, use those
        if ($customExtensions) {
            $allowedExtensions = array_map('trim', explode(',', strtolower($customExtensions)));
            return in_array($extension, $allowedExtensions);
        }

        // Default allowed extensions by type
        $allowedExtensions = match ($type) {
            'assignment' => ['pdf', 'doc', 'docx', 'txt', 'rtf', 'jpg', 'jpeg', 'png', 'gif', 'xls', 'xlsx', 'ppt', 'pptx'],
            'profile' => ['jpg', 'jpeg', 'png', 'gif'],
            'communication' => ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif'],
            'event' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
            'general' => ['pdf', 'doc', 'docx', 'txt', 'rtf', 'jpg', 'jpeg', 'png', 'gif', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar'],
            default => ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png']
        };

        return in_array($extension, $allowedExtensions);
    }

    /**
     * Generate secure unique filename without using user input
     */
    private function generateUniqueFilename(string $originalName, string $extension): string
    {
        // Generate a completely safe filename using only:
        // 1. Timestamp for uniqueness and easy sorting
        // 2. Random string for additional uniqueness
        // 3. Validated file extension

        $timestamp = Carbon::now()->format('YmdHis'); // 20250810123045
        $randomString = Str::random(8); // 8 character random string

        // Sanitize extension: only allow alphanumeric characters, remove any dangerous chars
        $safeExtension = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($extension));

        // Limit extension length to prevent abuse
        $safeExtension = substr($safeExtension, 0, 10);

        // Final format: 20250810123045_a3B8kL9m.jpg
        return $timestamp . '_' . $randomString . '.' . $safeExtension;
    }

    /**
     * Get upload directory based on type
     */
    private function getUploadDirectory(string $type): string
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $schoolId = $user->getSchoolId();
        $year = Carbon::now()->year;
        $month = Carbon::now()->format('m');

        return "uploads/{$type}/{$schoolId}/{$year}/{$month}";
    }

    /**
     * Regenerate thumbnails for an image
     */
    public function regenerateThumbnails(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_path' => 'required|string',
            'sizes' => 'sometimes|array',
            'sizes.*' => 'integer|min:50|max:2000'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $filePath = $request->input('file_path');
            $customSizes = $request->input('sizes');

            // Security check: ensure file path is within uploads directory
            if (!str_starts_with($filePath, '/uploads/') && !str_starts_with($filePath, 'uploads/')) {
                return $this->errorResponse('Invalid file path', null, 422);
            }

            // Remove leading slash if present
            $cleanPath = ltrim($filePath, '/');

            // Get upload disk (S3 or local)
            $uploadDisk = config('filesystems.gallery_disk', 'public');

            // Check if original file exists
            if (!Storage::disk($uploadDisk)->exists($cleanPath)) {
                return $this->errorResponse('Original file not found', null, 404);
            }

            // Get file extension and filename
            $extension = pathinfo($cleanPath, PATHINFO_EXTENSION);
            $filename = pathinfo($cleanPath, PATHINFO_FILENAME);

            // Check if it's an image file
            if (!$this->thumbnailService->isImageFile($extension)) {
                return $this->errorResponse('File is not an image', null, 422);
            }

            // Delete existing thumbnails first
            $this->thumbnailService->deleteThumbnails($cleanPath, $uploadDisk);

            // Prepare thumbnail sizes
            $thumbnailSizes = null;
            if ($customSizes) {
                $thumbnailSizes = [];
                foreach ($customSizes as $size) {
                    $thumbnailSizes["custom_{$size}"] = $size;
                }
            }

            // Queue thumbnail generation
            $queued = $this->thumbnailService->queueThumbnailGeneration(
                $cleanPath,
                $filename,
                $thumbnailSizes,
                $uploadDisk
            );

            if ($queued) {
                return $this->successResponse([
                    'file_path' => $filePath,
                    'thumbnail_generation_queued' => true,
                    'custom_sizes' => $thumbnailSizes ?? $this->thumbnailService->getDefaultThumbnailSizes()
                ], 'Thumbnail regeneration queued successfully');
            } else {
                return $this->errorResponse('Failed to queue thumbnail regeneration', null, 500);
            }
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to regenerate thumbnails: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Queue thumbnail generation if the uploaded file is an image
     */
    private function queueThumbnailIfImage(string $filePath, string $filename, string $extension, string $uploadDisk): bool
    {
        // Check if the file is an image
        if (!$this->thumbnailService->isImageFile($extension)) {
            return false;
        }

        // Queue thumbnail generation
        return $this->thumbnailService->queueThumbnailGeneration($filePath, $filename, null, $uploadDisk);
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
