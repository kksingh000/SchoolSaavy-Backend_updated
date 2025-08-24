<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use App\Services\ClassService;
use App\Http\Resources\ClassResource;
use App\Http\Requests\Class\StoreClassRequest;
use App\Http\Requests\Class\UpdateClassRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Traits\CacheInvalidation;

class ClassController extends BaseController
{
    use CacheInvalidation;

    protected $classService;

    public function __construct(ClassService $classService)
    {
        $this->classService = $classService;
    }

    public function index(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            // Get pagination parameters
            $perPage = $request->get('per_page', 15); // Default 15 items per page
            $page = $request->get('page', 1);

            // Validate per_page parameter
            $perPage = max(1, min(100, (int)$perPage)); // Between 1 and 100

            // Get filters including search
            $filters = $request->only(['search', 'grade_level', 'is_active', 'class_teacher_id']);

            $classes = $this->classService->getAll($filters, ['classTeacher.user', 'students'], $perPage);

            return $this->successResponse([
                'data' => ClassResource::collection($classes->items()),
                'pagination' => [
                    'current_page' => $classes->currentPage(),
                    'last_page' => $classes->lastPage(),
                    'per_page' => $classes->perPage(),
                    'total' => $classes->total(),
                    'from' => $classes->firstItem(),
                    'to' => $classes->lastItem(),
                    'has_more_pages' => $classes->hasMorePages(),
                    'prev_page_url' => $classes->previousPageUrl(),
                    'next_page_url' => $classes->nextPageUrl(),
                ]
            ], 'Classes retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function myClasses(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $startTime = microtime(true);
            $user = Auth::user();

            // Check if user is a teacher
            if ($user->user_type !== 'teacher') {
                return $this->errorResponse('This endpoint is only available for teachers.', null, 403);
            }

            // Get teacher ID from the user's teacher relationship
            $teacher = $user->teacher;
            if (!$teacher) {
                return $this->errorResponse('Teacher profile not found.', null, 404);
            }

            // Apply additional filters from request
            $filters = $request->only(['grade_level', 'is_active']);

            // Build optimized query with single database call using subqueries
            $query = \App\Models\ClassRoom::where(function ($query) use ($teacher) {
                // Class teacher condition
                $query->where('class_teacher_id', $teacher->id)
                    // OR subject teacher condition
                    ->orWhereExists(function ($subQuery) use ($teacher) {
                        $subQuery->select(DB::raw(1))
                            ->from('class_schedules')
                            ->whereColumn('class_schedules.class_id', 'classes.id')
                            ->where('class_schedules.teacher_id', $teacher->id)
                            ->where('class_schedules.is_active', true);
                    });
            });

            // Apply filters efficiently at database level
            foreach ($filters as $field => $value) {
                if ($value !== null && $value !== '') {
                    $query->where($field, $value);
                }
            }

            // Load only essential relationships with optimized selects
            $classes = $query->with([
                'classTeacher.user:id,name,email',
                'students' => function ($query) {
                    $query->select(['students.id', 'students.first_name', 'students.last_name', 'students.admission_number'])
                        ->wherePivot('is_active', true);
                },
                'todaysAttendance' => function ($query) {
                    $query->where('date', today())
                        ->select(['id', 'class_id', 'student_id', 'status', 'check_in_time', 'check_out_time', 'remarks'])
                        ->with('student:id,first_name,last_name,admission_number');
                }
            ])
                ->select(['id', 'name', 'section', 'grade_level', 'class_teacher_id', 'is_active', 'created_at'])
                ->paginate(15); // Add reasonable pagination limit

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return $this->successResponse(
                ClassResource::collection($classes),
                'My classes retrieved successfully',
                200,
                [
                    'pagination' => [
                        'current_page' => $classes->currentPage(),
                        'total_pages' => $classes->lastPage(),
                        'per_page' => $classes->perPage(),
                        'total' => $classes->total(),
                        'has_more_pages' => $classes->hasMorePages(),
                    ],
                    'execution_time_ms' => $executionTime, // Debug info
                ]
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get simplified classes (only id and name) for dropdowns and select lists
     * Supports pagination and search for fast, lightweight data retrieval
     */
    public function getSimpleClasses(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            // Get pagination parameters
            $perPage = $request->input('per_page', 15);
            $perPage = is_numeric($perPage) && $perPage >= 1 && $perPage <= 100 ? (int)$perPage : 15;

            $search = $request->input('search');

            $simpleClasses = $this->classService->getSimpleClasses($search, $perPage);

            return $this->successResponse($simpleClasses, 'Simple classes retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve simple classes: ' . $e->getMessage());
        }
    }

    public function store(StoreClassRequest $request): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $class = $this->classService->createClass($request->validated());

            // Invalidate related caches
            $this->invalidateCache('create', 'classes', $class->toArray());

            return $this->successResponse(
                new ClassResource($class->load(['classTeacher.user', 'students'])),
                'Class created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $class = $this->classService->find($id, ['classTeacher.user', 'students', 'subjects']);

            return $this->successResponse(
                new ClassResource($class),
                'Class retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function update(UpdateClassRequest $request, $id): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $class = $this->classService->update($id, $request->validated());

            // Invalidate related caches
            $this->invalidateCache('update', 'classes', $class->toArray());

            return $this->successResponse(
                new ClassResource($class->load(['classTeacher.user', 'students'])),
                'Class updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $this->classService->delete($id);

            // Invalidate related caches
            $this->invalidateCache('delete', 'classes', ['id' => $id]);

            return $this->successResponse(
                null,
                'Class deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function assignStudents(Request $request, $id): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'student_ids' => 'required|array',
                'student_ids.*' => 'exists:students,id'
            ]);

            $class = $this->classService->assignStudents($id, $request->student_ids);

            return $this->successResponse(
                new ClassResource($class->load(['students'])),
                'Students assigned successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function getStudents($id): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $students = $this->classService->getStudents($id);

            return $this->successResponse(
                $students,
                'Class students retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function getTimetable($id): JsonResponse
    {
        if (!$this->checkModuleAccess('timetable')) {
            return $this->moduleAccessDenied();
        }

        try {
            $timetable = $this->classService->getTimetable($id);

            return $this->successResponse(
                $timetable,
                'Class timetable retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function getClassesByTeacher($teacherId): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $classes = $this->classService->getClassesByTeacher($teacherId);

            // Since the service returns an array with attendance data, 
            // we can return it directly or convert to resource if needed
            return $this->successResponse(
                $classes,
                'Classes by teacher retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function getSubjects($id): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $subjects = $this->classService->getClassSubjects($id);

            return $this->successResponse(
                $subjects,
                'Class subjects retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function assignSubjects(Request $request, $id): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'subject_ids' => 'required|array',
                'subject_ids.*' => 'exists:subjects,id'
            ]);

            $class = $this->classService->assignSubjects($id, $request->subject_ids);

            return $this->successResponse(
                $class,
                'Subjects assigned to class successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get simplified list of classes for authenticated teacher (ID and Title only)
     */
    public function getMyClassesSimplified(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $startTime = microtime(true);
            $user = Auth::user();

            // Check if user is a teacher
            if ($user->user_type !== 'teacher') {
                return $this->errorResponse('This endpoint is only available for teachers.', null, 403);
            }

            // Get teacher ID from the user's teacher relationship
            $teacher = $user->teacher;
            if (!$teacher) {
                return $this->errorResponse('Teacher profile not found.', null, 404);
            }

            // Get classes where user is the class teacher OR teaches any subject - OPTIMIZED for minimal data
            $classes = ClassRoom::where('school_id', $request->school_id)
                ->where('is_active', true)
                ->where(function ($query) use ($teacher) {
                    // Class teacher condition
                    $query->where('class_teacher_id', $teacher->id)
                        // OR subject teacher condition
                        ->orWhereExists(function ($subQuery) use ($teacher) {
                            $subQuery->select(DB::raw(1))
                                ->from('class_schedules')
                                ->whereColumn('class_schedules.class_id', 'classes.id')
                                ->where('class_schedules.teacher_id', $teacher->id)
                                ->where('class_schedules.is_active', true);
                        });
                })
                ->select(['id', 'name', 'section'])
                ->orderBy('name')
                ->orderBy('section')
                ->get()
                ->map(function ($class) {
                    return [
                        'id' => $class->id,
                        'title' => $class->name . ' ' . $class->section
                    ];
                });

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return $this->successResponse([
                'classes' => $classes,
                'meta' => [
                    'total_classes' => $classes->count(),
                    'execution_time_ms' => $executionTime,
                ]
            ], 'Teacher classes retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Set promotion mapping for a class
     */
    public function setPromotionMapping(Request $request, $id): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'promotes_to_class_id' => 'nullable|exists:classes,id'
            ]);

            $class = ClassRoom::where('school_id', $request->school_id)
                ->findOrFail($id);

            $promotesToClassId = $request->promotes_to_class_id;

            // Validate promotion target
            if ($promotesToClassId) {
                if ($promotesToClassId == $id) {
                    return $this->errorResponse('A class cannot promote to itself');
                }

                $targetClass = ClassRoom::where('school_id', $request->school_id)
                    ->findOrFail($promotesToClassId);

                if ($targetClass->grade_level <= $class->grade_level) {
                    return $this->errorResponse('Target class must have a higher grade level');
                }
            }

            $class->promotes_to_class_id = $promotesToClassId;
            $class->save();

            // Clear related caches
            $this->invalidateResourceCache('classes', $request->school_id);

            return $this->successResponse(
                new ClassResource($class->load('promotesTo')),
                'Class promotion mapping updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update promotion mapping: ' . $e->getMessage());
        }
    }

    /**
     * Get classes with their promotion mappings
     */
    public function getWithPromotionMappings(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $classes = ClassRoom::where('school_id', $request->school_id)
                ->where('is_active', true)
                ->with(['promotesTo', 'promotesFrom'])
                ->orderBy('grade_level')
                ->orderBy('name')
                ->get();

            $groupedClasses = $classes->groupBy('grade_level')->map(function ($gradeClasses, $gradeLevel) {
                return [
                    'grade_level' => $gradeLevel,
                    'classes' => ClassResource::collection($gradeClasses)
                ];
            })->values();

            return $this->successResponse([
                'classes_by_grade' => $groupedClasses,
                'total_classes' => $classes->count()
            ], 'Classes with promotion mappings retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve classes: ' . $e->getMessage());
        }
    }
}
