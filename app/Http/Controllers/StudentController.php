<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentService;
use App\Http\Resources\StudentResource;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\BaseController;

class StudentController extends BaseController
{
    protected $studentService;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            // Check if module access is required (uncomment if needed)
            if (!$this->checkModuleAccess('student-management')) {
                return $this->moduleAccessDenied();
            }

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
            $page = $request->get('page', 1);

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
            return $this->errorResponse($e->getMessage());
        }
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        try {
            $student = $this->studentService->createStudent($request->validated());

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
        try {
            // Get all request data
            $data = $request->all();

            // Remove middleware injected data if no other data present
            unset($data['school_id'], $data['created_by']);

            if (empty($data)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No update data provided'
                ], 422);
            }

            $student = $this->studentService->updateStudent($id, $request->validated());

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
}
