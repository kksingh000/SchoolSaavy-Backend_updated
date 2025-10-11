<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\AssessmentResult;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AssessmentController extends Controller
{
    /**
     * Display a listing of assessments.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Assessment::with(['assessmentType', 'subject', 'class', 'teacher.user'])
                ->where('school_id', $request->school_id)
                ->orderBy('assessment_date', 'desc');

            // Apply filters
            if ($request->has('class_id') && $request->class_id) {
                $query->where('class_id', $request->class_id);
            }

            if ($request->has('subject_id') && $request->subject_id) {
                $query->where('subject_id', $request->subject_id);
            }

            if ($request->has('assessment_type_id') && $request->assessment_type_id) {
                $query->where('assessment_type_id', $request->assessment_type_id);
            }

            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('assessment_date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('assessment_date', '<=', $request->date_to);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $assessments = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Assessments retrieved successfully',
                'data' => $assessments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assessments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created assessment.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Check if academic year is active (injected by InjectSchoolData middleware)
            if (!$request->academic_year_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active academic year found. Please create and activate an academic year before creating assessments.',
                    'error' => 'missing_academic_year'
                ], 422);
            }

            $validated = $request->validate([
                'assessment_type_id' => 'required|exists:assessment_types,id',
                'title' => 'required|string|max:200',
                'code' => 'nullable|string|max:50',
                'description' => 'nullable|string',
                'subject_id' => 'required|exists:subjects,id',
                'class_id' => 'required|exists:classes,id',
                'teacher_id' => 'required|exists:teachers,id',
                'assessment_date' => 'required|date|after_or_equal:today',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'duration_minutes' => 'required|integer|min:1|max:480',
                'total_marks' => 'required|integer|min:1',
                'passing_marks' => 'required|integer|min:0|lte:total_marks',
                'marking_scheme' => 'nullable|array',
                'syllabus_covered' => 'nullable|string',
                'topics' => 'nullable|array',
                'instructions' => 'nullable|array',
                // academic_year is now optional - will use injected value
                'academic_year' => 'nullable|string|max:10'
            ]);

            // Verify assessment type belongs to the school
            $assessmentType = AssessmentType::where('id', $validated['assessment_type_id'])
                ->where('school_id', $request->school_id)
                ->first();

            if (!$assessmentType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid assessment type for this school'
                ], 422);
            }

            // Use injected values from middleware
            $validated['school_id'] = $request->school_id;
            $validated['academic_year_id'] = $request->academic_year_id;
            
            // Use current academic year label if not provided
            if (empty($validated['academic_year'])) {
                $validated['academic_year'] = $request->current_academic_year;
            }
            
            // No need to json_encode - Laravel model casts handle this automatically
            // marking_scheme, topics, instructions are cast to 'json' in the model
            
            // Generate code if not provided
            if (empty($validated['code'])) {
                $typeCode = $assessmentType->name;
                $subjectCode = substr($validated['subject_id'], 0, 3); // Simple subject code
                $year = date('Y');
                $validated['code'] = "{$typeCode}-{$subjectCode}-{$year}";
            }

            $assessment = Assessment::create($validated);
            $assessment->load(['assessmentType', 'subject', 'class', 'teacher.user']);

            return response()->json([
                'success' => true,
                'message' => 'Assessment created successfully',
                'data' => $assessment
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create assessment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified assessment.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $assessment = Assessment::with(['assessmentType', 'subject', 'class', 'teacher.user', 'results.student'])
                ->where('school_id', $request->school_id)
                ->where('id', $id)
                ->first();

            if (!$assessment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Assessment retrieved successfully',
                'data' => $assessment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assessment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified assessment.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $assessment = Assessment::where('school_id', $request->school_id)
                ->where('id', $id)
                ->first();

            if (!$assessment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment not found'
                ], 404);
            }

            // Prevent updates if assessment is completed or results are published
            if (in_array($assessment->status, ['completed', 'results_published'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update completed assessment or assessment with published results'
                ], 422);
            }

            $validated = $request->validate([
                'assessment_type_id' => 'sometimes|required|exists:assessment_types,id',
                'title' => 'sometimes|required|string|max:200',
                'code' => 'nullable|string|max:50',
                'description' => 'nullable|string',
                'subject_id' => 'sometimes|required|exists:subjects,id',
                'class_id' => 'sometimes|required|exists:classes,id',
                'teacher_id' => 'sometimes|required|exists:teachers,id',
                'assessment_date' => 'sometimes|required|date',
                'start_time' => 'sometimes|required|date_format:H:i',
                'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
                'duration_minutes' => 'sometimes|required|integer|min:1|max:480',
                'total_marks' => 'sometimes|required|integer|min:1',
                'passing_marks' => 'sometimes|required|integer|min:0',
                'marking_scheme' => 'nullable|array',
                'syllabus_covered' => 'nullable|string',
                'topics' => 'nullable|array',
                'instructions' => 'nullable|array',
                // academic_year is now optional - will use existing or injected value
                'academic_year' => 'nullable|string|max:10'
            ]);

            // Verify assessment type belongs to the school if provided
            if (isset($validated['assessment_type_id'])) {
                $assessmentType = AssessmentType::where('id', $validated['assessment_type_id'])
                    ->where('school_id', $request->school_id)
                    ->first();

                if (!$assessmentType) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid assessment type for this school'
                    ], 422);
                }
            }

            // Validate passing marks against total marks
            if (isset($validated['passing_marks']) && isset($validated['total_marks'])) {
                if ($validated['passing_marks'] > $validated['total_marks']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Passing marks cannot be greater than total marks'
                    ], 422);
                }
            }

            // No need to json_encode - Laravel model casts handle this automatically
            // marking_scheme, topics, instructions are cast to 'json' in the model

            $assessment->update($validated);
            $assessment->load(['assessmentType', 'subject', 'class', 'teacher.user']);

            return response()->json([
                'success' => true,
                'message' => 'Assessment updated successfully',
                'data' => $assessment
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
                'message' => 'Failed to update assessment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified assessment.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $assessment = Assessment::where('school_id', $request->school_id)
                ->where('id', $id)
                ->first();

            if (!$assessment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment not found'
                ], 404);
            }

            // Prevent deletion if results exist
            $resultCount = $assessment->results()->count();
            if ($resultCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete assessment as it has student results',
                    'data' => ['result_count' => $resultCount]
                ], 422);
            }

            $assessment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Assessment deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete assessment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming assessments.
     */
    public function upcoming(Request $request): JsonResponse
    {
        try {
            $query = Assessment::with(['assessmentType', 'subject', 'class', 'teacher.user'])
                ->where('school_id', $request->school_id)
                ->where('assessment_date', '>=', Carbon::today())
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->orderBy('assessment_date')
                ->orderBy('start_time');

            // Filter by class if provided
            if ($request->has('class_id') && $request->class_id) {
                $query->where('class_id', $request->class_id);
            }

            $assessments = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Upcoming assessments retrieved successfully',
                'data' => $assessments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve upcoming assessments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get completed assessments.
     */
    public function completed(Request $request): JsonResponse
    {
        try {
            $query = Assessment::with(['assessmentType', 'subject', 'class', 'teacher.user'])
                ->where('school_id', $request->school_id)
                ->whereIn('status', ['completed', 'results_published'])
                ->orderBy('assessment_date', 'desc');

            // Filter by class if provided
            if ($request->has('class_id') && $request->class_id) {
                $query->where('class_id', $request->class_id);
            }

            $assessments = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'Completed assessments retrieved successfully',
                'data' => $assessments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve completed assessments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assessment statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $schoolId = $request->school_id;

            $stats = [
                'total_assessments' => Assessment::where('school_id', $schoolId)->count(),
                'scheduled' => Assessment::where('school_id', $schoolId)
                    ->where('status', 'scheduled')->count(),
                'completed' => Assessment::where('school_id', $schoolId)
                    ->where('status', 'completed')->count(),
                'results_published' => Assessment::where('school_id', $schoolId)
                    ->where('status', 'results_published')->count(),
                'upcoming_this_week' => Assessment::where('school_id', $schoolId)
                    ->whereBetween('assessment_date', [
                        Carbon::now()->startOfWeek(),
                        Carbon::now()->endOfWeek()
                    ])
                    ->where('status', 'scheduled')
                    ->count(),
                'by_type' => AssessmentType::where('school_id', $schoolId)
                    ->withCount('assessments')
                    ->get(['name', 'display_name', 'assessments_count'])
            ];

            return response()->json([
                'success' => true,
                'message' => 'Assessment statistics retrieved successfully',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assessment statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update assessment status.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $assessment = Assessment::where('school_id', $request->school_id)
                ->where('id', $id)
                ->first();

            if (!$assessment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment not found'
                ], 404);
            }

            $validated = $request->validate([
                'status' => 'required|in:scheduled,in_progress,completed,results_published,cancelled'
            ]);

            $assessment->update(['status' => $validated['status']]);

            return response()->json([
                'success' => true,
                'message' => 'Assessment status updated successfully',
                'data' => $assessment
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
                'message' => 'Failed to update assessment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assessment results with statistics.
     */
    public function getResults(Request $request, string $id): JsonResponse
    {
        try {
            $assessment = Assessment::with(['assessmentType', 'subject', 'class'])
                ->where('school_id', $request->school_id)
                ->where('id', $id)
                ->first();

            if (!$assessment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment not found'
                ], 404);
            }

            $results = AssessmentResult::with(['student'])
                ->where('assessment_id', $id)
                ->orderBy('percentage', 'desc')
                ->get();

            // Calculate statistics
            $totalStudents = $results->count();
            $appeared = $results->where('result_status', '!=', 'absent')->count();
            $absent = $results->where('result_status', 'absent')->count();
            $passed = $results->where('result_status', 'pass')->count();
            $failed = $results->where('result_status', 'fail')->count();

            $scores = $results->where('result_status', '!=', 'absent')->pluck('marks_obtained');
            $averageMarks = $scores->avg();
            $highestMarks = $scores->max();
            $lowestMarks = $scores->min();

            $statistics = [
                'total_students' => $totalStudents,
                'appeared' => $appeared,
                'absent' => $absent,
                'passed' => $passed,
                'failed' => $failed,
                'pass_percentage' => $appeared > 0 ? round(($passed / $appeared) * 100, 2) : 0,
                'average_marks' => round($averageMarks, 2),
                'highest_marks' => $highestMarks,
                'lowest_marks' => $lowestMarks,
                'average_percentage' => $assessment->total_marks > 0 ?
                    round(($averageMarks / $assessment->total_marks) * 100, 2) : 0
            ];

            return response()->json([
                'success' => true,
                'message' => 'Assessment results retrieved successfully',
                'data' => [
                    'assessment' => $assessment,
                    'results' => $results,
                    'statistics' => $statistics
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assessment results',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publish assessment results.
     */
    public function publishResults(Request $request, string $id): JsonResponse
    {
        try {
            $assessment = Assessment::where('school_id', $request->school_id)
                ->where('id', $id)
                ->first();

            if (!$assessment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment not found'
                ], 404);
            }

            $validated = $request->validate([
                'publish_all' => 'boolean',
                'student_ids' => 'array|exists:students,id',
                'message' => 'nullable|string|max:500'
            ]);

            $publishAll = $validated['publish_all'] ?? false;
            $publishedBy = auth()->id();
            $publishedAt = now();

            if ($publishAll) {
                // Publish all results for this assessment
                AssessmentResult::where('assessment_id', $id)
                    ->whereNull('result_published_at')
                    ->update([
                        'result_published_at' => $publishedAt,
                        'published_by' => $publishedBy
                    ]);

                $publishedCount = AssessmentResult::where('assessment_id', $id)
                    ->whereNotNull('result_published_at')
                    ->count();
            } else {
                // Publish specific student results
                $studentIds = $validated['student_ids'] ?? [];
                AssessmentResult::where('assessment_id', $id)
                    ->whereIn('student_id', $studentIds)
                    ->whereNull('result_published_at')
                    ->update([
                        'result_published_at' => $publishedAt,
                        'published_by' => $publishedBy
                    ]);

                $publishedCount = count($studentIds);
            }

            // Update assessment status if all results are published
            $totalResults = AssessmentResult::where('assessment_id', $id)->count();
            $publishedResults = AssessmentResult::where('assessment_id', $id)
                ->whereNotNull('result_published_at')
                ->count();

            if ($totalResults > 0 && $totalResults === $publishedResults) {
                $assessment->update(['status' => 'results_published']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Assessment results published successfully',
                'data' => [
                    'published_count' => $publishedCount,
                    'assessment_status' => $assessment->fresh()->status
                ]
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
                'message' => 'Failed to publish assessment results',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assessments by class.
     */
    public function getByClass(Request $request, string $classId): JsonResponse
    {
        try {
            $assessments = Assessment::with(['assessmentType', 'subject', 'teacher.user'])
                ->where('school_id', $request->school_id)
                ->where('class_id', $classId)
                ->orderBy('assessment_date', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'Class assessments retrieved successfully',
                'data' => $assessments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve class assessments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assessments by subject.
     */
    public function getBySubject(Request $request, string $subjectId): JsonResponse
    {
        try {
            $assessments = Assessment::with(['assessmentType', 'class', 'teacher.user'])
                ->where('school_id', $request->school_id)
                ->where('subject_id', $subjectId)
                ->orderBy('assessment_date', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'Subject assessments retrieved successfully',
                'data' => $assessments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subject assessments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assessments by type.
     */
    public function getByType(Request $request, string $typeId): JsonResponse
    {
        try {
            $assessments = Assessment::with(['subject', 'class', 'teacher.user'])
                ->where('school_id', $request->school_id)
                ->where('assessment_type_id', $typeId)
                ->orderBy('assessment_date', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'Assessment type assessments retrieved successfully',
                'data' => $assessments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assessment type assessments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teacher dashboard data.
     */
    public function teacherDashboard(Request $request): JsonResponse
    {
        try {
            $teacherId = $request->teacher_id ?? auth()->user()->teacher->id ?? null;

            if (!$teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher ID not found'
                ], 422);
            }

            $upcomingAssessments = Assessment::with(['assessmentType', 'subject', 'class'])
                ->where('school_id', $request->school_id)
                ->where('teacher_id', $teacherId)
                ->where('assessment_date', '>=', Carbon::today())
                ->orderBy('assessment_date')
                ->take(5)
                ->get();

            $pendingResults = Assessment::where('school_id', $request->school_id)
                ->where('teacher_id', $teacherId)
                ->where('status', 'completed')
                ->whereDoesntHave('results')
                ->count();

            $recentAssessments = Assessment::with(['assessmentType', 'subject', 'class'])
                ->where('school_id', $request->school_id)
                ->where('teacher_id', $teacherId)
                ->where('assessment_date', '<=', Carbon::today())
                ->orderBy('assessment_date', 'desc')
                ->take(5)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Teacher dashboard data retrieved successfully',
                'data' => [
                    'upcoming_assessments' => $upcomingAssessments,
                    'pending_results_count' => $pendingResults,
                    'recent_assessments' => $recentAssessments
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve teacher dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
