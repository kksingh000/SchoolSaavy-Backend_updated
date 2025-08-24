<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentService;
use App\Services\StudentImportService;
use App\Http\Resources\StudentResource;
use App\Http\Resources\StudentImportResource;
use App\Http\Resources\StudentImportErrorResource;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Requests\Student\ImportStudentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\BaseController;
use App\Traits\CacheInvalidation;

class StudentController extends BaseController
{
    use CacheInvalidation;

    protected $studentService;
    protected $importService;

    public function __construct(StudentService $studentService, StudentImportService $importService)
    {
        $this->studentService = $studentService;
        $this->importService = $importService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            // Check if module access is required
            if (!$this->checkModuleAccess('student-management')) {
                return $this->moduleAccessDenied();
            }

            // Get filters from request
            $filters = $request->only([
                'search',
                'class_id',
                'gender',
                'blood_group',
                'is_active',
                'admission_date'
            ]);

            // Get pagination parameters
            $perPage = $request->get('per_page', 15); // Default 15 items per page

            // Validate per_page parameter
            $perPage = max(1, min(100, (int)$perPage)); // Between 1 and 100

            $students = $this->studentService->getAllStudents($filters, $perPage);

            return $this->successResponse([
                'data' => StudentResource::collection($students->items()),
                'pagination' => [
                    'current_page' => $students->currentPage(),
                    'last_page' => $students->lastPage(),
                    'per_page' => $students->perPage(),
                    'total' => $students->total(),
                    'from' => $students->firstItem(),
                    'to' => $students->lastItem(),
                    'has_more_pages' => $students->hasMorePages(),
                    'prev_page_url' => $students->previousPageUrl(),
                    'next_page_url' => $students->nextPageUrl(),
                ]
            ], 'Students retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve students', [
                'error' => $e->getMessage(),
                'school_id' => request()->school_id,
                'filters' => $filters ?? []
            ]);

            return $this->errorResponse('Failed to retrieve students: ' . $e->getMessage(), null, 500);
        }
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        try {
            $student = $this->studentService->createStudent($request->validated());

            // Invalidate related caches
            $this->invalidateCache('create', 'students', $student->toArray());

            return response()->json([
                'status' => 'success',
                'message' => 'Student created successfully',
                'data' => new StudentResource($student)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $student = $this->studentService->getStudentById($id);

            return response()->json([
                'status' => 'success',
                'data' => new StudentResource($student)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function update(UpdateStudentRequest $request, $id): JsonResponse
    {
        $data = $request->validated();
        try {
            if (empty($data)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No update data provided'
                ], 422);
            }

            $student = $this->studentService->updateStudent($id, $data);

            // Invalidate related caches
            $this->invalidateCache('update', 'students', $student->toArray());

            return response()->json([
                'status' => 'success',
                'message' => 'Student updated successfully',
                'data' => new StudentResource($student)
            ]);
        } catch (\Exception $e) {
            Log::error('Student Update Error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $this->studentService->deleteStudent($id);

            // Invalidate related caches
            $this->invalidateCache('delete', 'students', ['id' => $id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Student deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getAttendanceReport(Request $request, $id)
    {
        $report = $this->studentService->getAttendanceReport(
            $id,
            $request->start_date,
            $request->end_date
        );
        return response()->json(['data' => $report]);
    }

    public function getFeeStatus($id)
    {
        $feeStatus = $this->studentService->getFeeStatus($id);
        return response()->json(['data' => $feeStatus]);
    }

    // ========== BULK IMPORT METHODS ==========

    /**
     * Download CSV template for student import
     */
    public function downloadTemplate()
    {
        try {
            if (!$this->checkModuleAccess('student-management')) {
                return $this->moduleAccessDenied();
            }

            return $this->importService->downloadTemplate();
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Import students from CSV file
     * Note: File should be uploaded first using the FileUploadController
     */
    public function import(ImportStudentRequest $request): JsonResponse
    {
        try {
            if (!$this->checkModuleAccess('student-management')) {
                return $this->moduleAccessDenied();
            }

            $studentImport = $this->importService->initiateImport(
                $request->input('file_path'),
                $request->input('file_name')
            );

            return $this->successResponse(
                new StudentImportResource($studentImport),
                'Student import initiated successfully. Processing in background.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get import history
     */
    public function getImports(Request $request): JsonResponse
    {
        try {
            if (!$this->checkModuleAccess('student-management')) {
                return $this->moduleAccessDenied();
            }

            $perPage = $request->get('per_page', 15);
            $imports = $this->importService->getImportHistory($perPage);

            return $this->successResponse([
                'data' => StudentImportResource::collection($imports->items()),
                'pagination' => [
                    'current_page' => $imports->currentPage(),
                    'last_page' => $imports->lastPage(),
                    'per_page' => $imports->perPage(),
                    'total' => $imports->total(),
                    'from' => $imports->firstItem(),
                    'to' => $imports->lastItem(),
                    'has_more_pages' => $imports->hasMorePages(),
                ]
            ], 'Import history retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get specific import details
     */
    public function getImport($id): JsonResponse
    {
        try {
            if (!$this->checkModuleAccess('student-management')) {
                return $this->moduleAccessDenied();
            }

            $import = $this->importService->getImportById($id);

            return $this->successResponse(
                new StudentImportResource($import),
                'Import details retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }
    }

    /**
     * Get import errors
     */
    public function getImportErrors(Request $request, $id): JsonResponse
    {
        try {
            if (!$this->checkModuleAccess('student-management')) {
                return $this->moduleAccessDenied();
            }

            $perPage = $request->get('per_page', 50);
            $errors = $this->importService->getImportErrors($id, $perPage);

            return $this->successResponse([
                'data' => StudentImportErrorResource::collection($errors->items()),
                'pagination' => [
                    'current_page' => $errors->currentPage(),
                    'last_page' => $errors->lastPage(),
                    'per_page' => $errors->perPage(),
                    'total' => $errors->total(),
                    'from' => $errors->firstItem(),
                    'to' => $errors->lastItem(),
                    'has_more_pages' => $errors->hasMorePages(),
                ]
            ], 'Import errors retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Cancel pending/processing import
     */
    public function cancelImport($id): JsonResponse
    {
        try {
            if (!$this->checkModuleAccess('student-management')) {
                return $this->moduleAccessDenied();
            }

            $this->importService->cancelImport($id);

            return $this->successResponse(null, 'Import cancelled successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Delete import and associated file
     */
    public function deleteImport($id): JsonResponse
    {
        try {
            if (!$this->checkModuleAccess('student-management')) {
                return $this->moduleAccessDenied();
            }

            $this->importService->deleteImport($id);

            return $this->successResponse(null, 'Import deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
