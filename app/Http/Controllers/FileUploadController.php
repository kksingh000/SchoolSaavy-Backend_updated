<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FileUploadController extends BaseController
{
    /**
     * Upload single file
     */
    public function uploadSingle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'type' => 'required|string|in:assignment,profile,communication,event,general',
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
            return $this->errorResponse('File upload failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Upload multiple files
     */
    public function uploadMultiple(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array|max:5', // Max 5 files at once
            'files.*' => 'file|max:10240', // 10MB per file
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

            if (Storage::disk('public')->exists($cleanPath)) {
                Storage::disk('public')->delete($cleanPath);
                return $this->successResponse(null, 'File deleted successfully');
            } else {
                return $this->errorResponse('File not found', null, 404);
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

        // Store file
        $path = $file->storeAs($directory, $filename, 'public');

        // Generate public URL
        $url = asset('storage/' . $path);

        return [
            'name' => $originalName,
            'filename' => $filename,
            'url' => $url,
            'path' => '/' . $path,
            'type' => $extension,
            'mime_type' => $mimeType,
            'size' => $size,
            'size_human' => $this->formatBytes($size),
            'uploaded_at' => Carbon::now()->toISOString()
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
     * Generate unique filename
     */
    private function generateUniqueFilename(string $originalName, string $extension): string
    {
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $name = Str::slug($name); // Convert to URL-friendly format
        $uuid = Str::uuid()->toString();

        return $uuid . '_' . $name . '.' . $extension;
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
