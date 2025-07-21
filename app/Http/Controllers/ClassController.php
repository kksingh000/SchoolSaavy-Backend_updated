<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use App\Services\ClassService;
use App\Http\Resources\ClassResource;
use App\Http\Requests\Class\StoreClassRequest;
use App\Http\Requests\Class\UpdateClassRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
}
