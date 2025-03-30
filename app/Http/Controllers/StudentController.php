<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentService;
use App\Http\Resources\StudentResource;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    protected $studentService;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search', 'class_id', 'gender', 'blood_group', 
                'is_active', 'admission_date'
            ]);
            
            $students = $this->studentService->getAllStudents($filters);
            
            return response()->json([
                'status' => 'success',
                'data' => StudentResource::collection($students)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
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
            $student = $this->studentService->updateStudent($id, $request->validated());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Student updated successfully',
                'data' => new StudentResource($student)
            ]);
        } catch (\Exception $e) {
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