<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class AssessmentResultController extends Controller
{
    /**
     * Store a single assessment result.
     */
    public function store(Request $request, string $assessmentId): JsonResponse
    {
        try {
            // Verify assessment exists and belongs to school
            $assessment = Assessment::where('school_id', $request->school_id)
                ->where('id', $assessmentId)
                ->first();

            if (!$assessment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment not found'
                ], 404);
            }

            $validated = $request->validate([
                'student_id' => 'required|exists:students,id',
                'marks_obtained' => 'required|numeric|min:0|max:' . $assessment->total_marks,
                'attendance_status' => 'required|in:present,absent,late',
                'remarks' => 'nullable|string|max:500',
                'section_wise_marks' => 'nullable|array',
                'absence_reason' => 'nullable|string|max:255'
            ]);

            // Check if result already exists
            $existingResult = AssessmentResult::where('assessment_id', $assessmentId)
                ->where('student_id', $validated['student_id'])
                ->first();

            if ($existingResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Result already exists for this student',
                    'data' => ['existing_result_id' => $existingResult->id]
                ], 422);
            }

            // Calculate percentage and grade
            $percentage = ($validated['marks_obtained'] / $assessment->total_marks) * 100;
            $grade = $this->calculateGrade($percentage, $assessment);
            $resultStatus = $this->determineResultStatus($validated['attendance_status'], $validated['marks_obtained'], $assessment->passing_marks);

            $resultData = [
                'assessment_id' => $assessmentId,
                'student_id' => $validated['student_id'],
                'marks_obtained' => $validated['marks_obtained'],
                'percentage' => round($percentage, 2),
                'grade' => $grade,
                'result_status' => $resultStatus,
                'remarks' => $validated['remarks'],
                'section_wise_marks' => json_encode($validated['section_wise_marks'] ?? []),
                'is_absent' => $validated['attendance_status'] === 'absent',
                'absence_reason' => $validated['absence_reason'],
                'entered_by' => auth()->id()
            ];

            $result = AssessmentResult::create($resultData);
            $result->load(['student', 'assessment']);

            return response()->json([
                'success' => true,
                'message' => 'Assessment result created successfully',
                'data' => $result
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create assessment result',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store multiple assessment results in bulk.
     */
    public function bulkStore(Request $request, string $assessmentId): JsonResponse
    {
        try {
            // Verify assessment exists and belongs to school
            $assessment = Assessment::where('school_id', $request->school_id)
                ->where('id', $assessmentId)
                ->first();

            if (!$assessment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment not found'
                ], 404);
            }

            $validated = $request->validate([
                'results' => 'required|array|min:1',
                'results.*.student_id' => 'required|exists:students,id',
                'results.*.marks_obtained' => 'required|numeric|min:0|max:' . $assessment->total_marks,
                'results.*.attendance_status' => 'required|in:present,absent,late',
                'results.*.remarks' => 'nullable|string|max:500',
                'results.*.section_wise_marks' => 'nullable|array',
                'results.*.absence_reason' => 'nullable|string|max:255'
            ]);

            $results = [];
            $errors = [];
            $successCount = 0;

            DB::beginTransaction();

            try {
                foreach ($validated['results'] as $index => $resultData) {
                    // Check if result already exists
                    $existingResult = AssessmentResult::where('assessment_id', $assessmentId)
                        ->where('student_id', $resultData['student_id'])
                        ->first();

                    if ($existingResult) {
                        $errors[] = [
                            'index' => $index,
                            'student_id' => $resultData['student_id'],
                            'error' => 'Result already exists for this student'
                        ];
                        continue;
                    }

                    // Calculate percentage and grade
                    $percentage = ($resultData['marks_obtained'] / $assessment->total_marks) * 100;
                    $grade = $this->calculateGrade($percentage, $assessment);
                    $resultStatus = $this->determineResultStatus(
                        $resultData['attendance_status'],
                        $resultData['marks_obtained'],
                        $assessment->passing_marks
                    );

                    $resultRecord = [
                        'assessment_id' => $assessmentId,
                        'student_id' => $resultData['student_id'],
                        'marks_obtained' => $resultData['marks_obtained'],
                        'percentage' => round($percentage, 2),
                        'grade' => $grade,
                        'result_status' => $resultStatus,
                        'remarks' => $resultData['remarks'] ?? null,
                        'section_wise_marks' => json_encode($resultData['section_wise_marks'] ?? []),
                        'is_absent' => $resultData['attendance_status'] === 'absent',
                        'absence_reason' => $resultData['absence_reason'] ?? null,
                        'entered_by' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    $result = AssessmentResult::create($resultRecord);
                    $results[] = $result;
                    $successCount++;
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully created {$successCount} assessment results",
                    'data' => [
                        'created_results' => $results,
                        'success_count' => $successCount,
                        'error_count' => count($errors),
                        'errors' => $errors
                    ]
                ], 201);
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create assessment results',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified assessment result.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $result = AssessmentResult::with(['assessment.assessmentType', 'assessment.subject', 'student'])
                ->whereHas('assessment', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->where('id', $id)
                ->first();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment result not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Assessment result retrieved successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assessment result',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified assessment result.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $result = AssessmentResult::with('assessment')
                ->whereHas('assessment', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->where('id', $id)
                ->first();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment result not found'
                ], 404);
            }

            // Prevent updates if result is already published
            if ($result->result_published_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update published result'
                ], 422);
            }

            $validated = $request->validate([
                'marks_obtained' => 'sometimes|required|numeric|min:0|max:' . $result->assessment->total_marks,
                'attendance_status' => 'sometimes|required|in:present,absent,late',
                'remarks' => 'nullable|string|max:500',
                'section_wise_marks' => 'nullable|array',
                'absence_reason' => 'nullable|string|max:255'
            ]);

            // Recalculate percentage and grade if marks changed
            if (isset($validated['marks_obtained'])) {
                $percentage = ($validated['marks_obtained'] / $result->assessment->total_marks) * 100;
                $validated['percentage'] = round($percentage, 2);
                $validated['grade'] = $this->calculateGrade($percentage, $result->assessment);
            }

            // Update result status if attendance status changed
            if (isset($validated['attendance_status'])) {
                $marksObtained = $validated['marks_obtained'] ?? $result->marks_obtained;
                $validated['result_status'] = $this->determineResultStatus(
                    $validated['attendance_status'],
                    $marksObtained,
                    $result->assessment->passing_marks
                );
                $validated['is_absent'] = $validated['attendance_status'] === 'absent';
            }

            if (isset($validated['section_wise_marks'])) {
                $validated['section_wise_marks'] = json_encode($validated['section_wise_marks']);
            }

            $result->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Assessment result updated successfully',
                'data' => $result->fresh(['assessment', 'student'])
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update assessment result',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified assessment result.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $result = AssessmentResult::whereHas('assessment', function ($query) use ($request) {
                $query->where('school_id', $request->school_id);
            })
                ->where('id', $id)
                ->first();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment result not found'
                ], 404);
            }

            // Prevent deletion if result is published
            if ($result->result_published_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete published result'
                ], 422);
            }

            $result->delete();

            return response()->json([
                'success' => true,
                'message' => 'Assessment result deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete assessment result',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publish a specific assessment result.
     */
    public function publish(Request $request, string $id): JsonResponse
    {
        try {
            $result = AssessmentResult::whereHas('assessment', function ($query) use ($request) {
                $query->where('school_id', $request->school_id);
            })
                ->where('id', $id)
                ->first();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment result not found'
                ], 404);
            }

            if ($result->result_published_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Result is already published'
                ], 422);
            }

            $result->publish(auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Assessment result published successfully',
                'data' => $result->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to publish assessment result',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all assessment results for a student.
     */
    public function getStudentResults(Request $request, string $studentId): JsonResponse
    {
        try {
            $query = AssessmentResult::with(['assessment.assessmentType', 'assessment.subject'])
                ->whereHas('assessment', function ($query) use ($request) {
                    $query->where('school_id', $request->school_id);
                })
                ->where('student_id', $studentId)
                ->orderBy('created_at', 'desc');

            // Filter by subject if provided
            if ($request->has('subject_id') && $request->subject_id) {
                $query->whereHas('assessment', function ($q) use ($request) {
                    $q->where('subject_id', $request->subject_id);
                });
            }

            // Filter by assessment type if provided
            if ($request->has('assessment_type_id') && $request->assessment_type_id) {
                $query->whereHas('assessment', function ($q) use ($request) {
                    $q->where('assessment_type_id', $request->assessment_type_id);
                });
            }

            // Filter by year if provided
            if ($request->has('year') && $request->year) {
                $query->whereHas('assessment', function ($q) use ($request) {
                    $q->whereYear('assessment_date', $request->year);
                });
            }

            // Only show published results to students
            if ($request->user()->user_type === 'student') {
                $query->whereNotNull('result_published_at');
            }

            $results = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'Student assessment results retrieved successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student assessment results',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assessment results for a student in a specific subject.
     */
    public function getStudentSubjectResults(Request $request, string $studentId, string $subjectId): JsonResponse
    {
        try {
            $query = AssessmentResult::with(['assessment.assessmentType'])
                ->whereHas('assessment', function ($query) use ($request, $subjectId) {
                    $query->where('school_id', $request->school_id)
                        ->where('subject_id', $subjectId);
                })
                ->where('student_id', $studentId)
                ->orderBy('created_at', 'desc');

            // Only show published results to students
            if ($request->user()->user_type === 'student') {
                $query->whereNotNull('result_published_at');
            }

            $results = $query->get();

            // Calculate subject statistics
            $totalResults = $results->count();
            $averagePercentage = $results->avg('percentage');
            $highestPercentage = $results->max('percentage');
            $lowestPercentage = $results->min('percentage');

            $statistics = [
                'total_assessments' => $totalResults,
                'average_percentage' => round($averagePercentage, 2),
                'highest_percentage' => $highestPercentage,
                'lowest_percentage' => $lowestPercentage,
                'grade_distribution' => $results->groupBy('grade')->map->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Student subject assessment results retrieved successfully',
                'data' => [
                    'results' => $results,
                    'statistics' => $statistics
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student subject assessment results',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate grade based on percentage and assessment grading scale.
     */
    private function calculateGrade(float $percentage, Assessment $assessment): string
    {
        // Default grading scale if none specified in assessment
        $defaultGrading = [
            'A+' => ['min' => 95, 'max' => 100],
            'A' => ['min' => 90, 'max' => 94],
            'B+' => ['min' => 85, 'max' => 89],
            'B' => ['min' => 80, 'max' => 84],
            'C+' => ['min' => 75, 'max' => 79],
            'C' => ['min' => 70, 'max' => 74],
            'D+' => ['min' => 65, 'max' => 69],
            'D' => ['min' => 40, 'max' => 64],
            'F' => ['min' => 0, 'max' => 39]
        ];

        $gradingScale = $defaultGrading; // Use default for now

        foreach ($gradingScale as $grade => $range) {
            if ($percentage >= $range['min'] && $percentage <= $range['max']) {
                return $grade;
            }
        }

        return 'F'; // Default grade
    }

    /**
     * Determine result status based on attendance and marks.
     */
    private function determineResultStatus(string $attendanceStatus, float $marksObtained, int $passingMarks): string
    {
        if ($attendanceStatus === 'absent') {
            return 'absent';
        }

        return $marksObtained >= $passingMarks ? 'pass' : 'fail';
    }
}
