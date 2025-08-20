<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Services\StudentService;
use App\Models\ClassRoom;
use App\Http\Requests\RollNumber\GenerateBulkRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RollNumberController extends BaseController
{
    protected StudentService $studentService;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    /**
     * Get next available roll number for a class
     */
    public function getNext(Request $request): JsonResponse
    {
        try {
            $classId = $request->get('class_id');

            if (!$classId) {
                return $this->errorResponse('Class ID is required', null, 422);
            }

            // Verify class exists and belongs to school
            $class = ClassRoom::where('id', $classId)
                ->where('school_id', request()->school_id)
                ->where('is_active', true)
                ->first();

            if (!$class) {
                return $this->errorResponse('Class not found or inactive', null, 404);
            }

            $stats = $this->studentService->getClassRollNumberStats($classId);

            return $this->successResponse([
                'class_id' => $classId,
                'class_name' => $class->name,
                'next_roll_number' => $stats['next_available'],
                'total_students' => $stats['total_students'],
                'statistics' => $stats
            ], 'Next roll number retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get available roll numbers for a class (including gaps)
     */
    public function getAvailable(Request $request): JsonResponse
    {
        try {
            $classId = $request->get('class_id');
            $limit = $request->get('limit', 10);

            if (!$classId) {
                return $this->errorResponse('Class ID is required', null, 422);
            }

            // Verify class exists and belongs to school
            $class = ClassRoom::where('id', $classId)
                ->where('school_id', request()->school_id)
                ->where('is_active', true)
                ->first();

            if (!$class) {
                return $this->errorResponse('Class not found or inactive', null, 404);
            }

            $availableNumbers = $this->studentService->getAvailableRollNumbers($classId, $limit);
            $stats = $this->studentService->getClassRollNumberStats($classId);

            return $this->successResponse([
                'class_id' => $classId,
                'class_name' => $class->name,
                'available_roll_numbers' => $availableNumbers,
                'statistics' => $stats,
                'recommendation' => [
                    'next_sequential' => $stats['next_available'],
                    'fill_gaps_first' => !empty($stats['available_gaps']),
                    'suggested_number' => !empty($stats['available_gaps']) ?
                        min($stats['available_gaps']) : $stats['next_available']
                ]
            ], 'Available roll numbers retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Check if a specific roll number is available in a class
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        try {
            $classId = $request->get('class_id');
            $rollNumber = $request->get('roll_number');

            if (!$classId || !$rollNumber) {
                return $this->errorResponse('Class ID and roll number are required', null, 422);
            }

            if (!is_numeric($rollNumber) || $rollNumber < 1) {
                return $this->errorResponse('Roll number must be a positive integer', null, 422);
            }

            // Verify class exists and belongs to school
            $class = ClassRoom::where('id', $classId)
                ->where('school_id', request()->school_id)
                ->where('is_active', true)
                ->first();

            if (!$class) {
                return $this->errorResponse('Class not found or inactive', null, 404);
            }

            // Check if roll number is taken
            $isAvailable = !\Illuminate\Support\Facades\DB::table('class_student')
                ->where('class_id', $classId)
                ->where('roll_number', $rollNumber)
                ->where('is_active', true)
                ->exists();

            return $this->successResponse([
                'class_id' => $classId,
                'class_name' => $class->name,
                'roll_number' => (int)$rollNumber,
                'is_available' => $isAvailable,
                'message' => $isAvailable ?
                    'Roll number is available' :
                    'Roll number is already taken'
            ], 'Roll number availability checked successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get roll number statistics for a class
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $classId = $request->get('class_id');

            if (!$classId) {
                return $this->errorResponse('Class ID is required', null, 422);
            }

            // Verify class exists and belongs to school
            $class = ClassRoom::where('id', $classId)
                ->where('school_id', request()->school_id)
                ->where('is_active', true)
                ->first();

            if (!$class) {
                return $this->errorResponse('Class not found or inactive', null, 404);
            }

            $stats = $this->studentService->getClassRollNumberStats($classId);

            // Get list of students with their roll numbers
            $students = \Illuminate\Support\Facades\DB::table('class_student')
                ->join('students', 'students.id', '=', 'class_student.student_id')
                ->where('class_student.class_id', $classId)
                ->where('class_student.is_active', true)
                ->select('students.first_name', 'students.last_name', 'students.admission_number', 'class_student.roll_number')
                ->orderBy('class_student.roll_number')
                ->get();

            return $this->successResponse([
                'class_id' => $classId,
                'class_name' => $class->name,
                'statistics' => $stats,
                'students_with_roll_numbers' => $students,
                'recommendations' => [
                    'next_student_should_get' => $stats['next_available'],
                    'gaps_to_fill' => $stats['available_gaps'],
                    'total_gaps' => count($stats['available_gaps']),
                    'efficiency' => $stats['total_students'] > 0 ?
                        round(($stats['total_students'] / ($stats['highest_roll_number'] ?: 1)) * 100, 1) : 100
                ]
            ], 'Class roll number statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Bulk generate roll numbers for multiple students
     */
    public function generateBulk(GenerateBulkRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $classId = $data['class_id'];
            $count = $data['count'];
            $fillGaps = $data['fill_gaps'];

            // Get class information (validation already done in FormRequest)
            $class = ClassRoom::where('id', $classId)
                ->where('school_id', request()->school_id)
                ->where('is_active', true)
                ->first();

            $rollNumbers = [];

            if ($fillGaps) {
                // Get available numbers including gaps
                $rollNumbers = $this->studentService->getAvailableRollNumbers($classId, $count);
            } else {
                // Generate sequential numbers from the next available
                $stats = $this->studentService->getClassRollNumberStats($classId);
                $startFrom = $stats['next_available'];

                for ($i = 0; $i < $count; $i++) {
                    $rollNumbers[] = $startFrom + $i;
                }
            }

            return $this->successResponse([
                'class_id' => $classId,
                'class_name' => $class->name,
                'generated_roll_numbers' => $rollNumbers,
                'count' => count($rollNumbers),
                'method' => $fillGaps ? 'fill_gaps_first' : 'sequential_only',
                'usage_note' => 'These numbers are reserved for immediate use. Assign students quickly to avoid conflicts.'
            ], 'Bulk roll numbers generated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
