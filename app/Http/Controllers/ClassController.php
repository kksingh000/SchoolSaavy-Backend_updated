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

class ClassController extends BaseController
{
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
            $filters = $request->only(['grade_level', 'is_active', 'class_teacher_id']);
            $classes = $this->classService->getAll($filters, ['classTeacher.user', 'students']);

            return $this->successResponse(
                ClassResource::collection($classes),
                'Classes retrieved successfully'
            );
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

            // Get classes where teacher is either:
            // 1. Class teacher (homeroom teacher)
            // 2. Subject teacher (teaches subjects in the class)
            $classIds = collect();

            // Get classes where teacher is the class teacher
            $classTeacherIds = \App\Models\ClassRoom::where('class_teacher_id', $teacher->id)
                ->pluck('id');
            $classIds = $classIds->merge($classTeacherIds);

            // Get classes where teacher teaches subjects (from class_schedules)
            $subjectTeacherIds = DB::table('class_schedules')
                ->where('teacher_id', $teacher->id)
                ->where('is_active', true)
                ->distinct()
                ->pluck('class_id');
            $classIds = $classIds->merge($subjectTeacherIds);

            // Remove duplicates and get unique class IDs
            $uniqueClassIds = $classIds->unique()->values();

            if ($uniqueClassIds->isEmpty()) {
                return $this->successResponse(
                    [],
                    'No classes assigned to this teacher'
                );
            }

            // Apply additional filters from request
            $filters = $request->only(['grade_level', 'is_active']);

            // Build query with class IDs
            $query = \App\Models\ClassRoom::whereIn('id', $uniqueClassIds);

            // Apply filters
            foreach ($filters as $field => $value) {
                if ($value !== null && $value !== '') {
                    $query->where($field, $value);
                }
            }

            // Get classes with relationships
            $classes = $query->with([
                'classTeacher.user',
                'students' => function ($query) {
                    $query->select(['students.id', 'students.first_name', 'students.last_name', 'students.admission_number']);
                },
                'todaysAttendance' => function ($query) {
                    $query->where('date', today())
                        ->with('student:id,first_name,last_name,admission_number');
                }
            ])->paginate();

            return $this->successResponse(
                ClassResource::collection($classes),
                'My classes retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function store(StoreClassRequest $request): JsonResponse
    {
        if (!$this->checkModuleAccess('class-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $class = $this->classService->createClass($request->validated());

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
            $class = ClassRoom::with('subjects')->findOrFail($id);

            return $this->successResponse(
                $class->subjects,
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

            $class = ClassRoom::findOrFail($id);
            $class->subjects()->sync($request->subject_ids);

            return $this->successResponse(
                $class->load('subjects'),
                'Subjects assigned to class successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
