<?php

namespace App\Http\Controllers;

use App\Models\ClassSchedule;
use App\Services\TimetableService;
use App\Http\Resources\TimetableResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TimetableController extends BaseController
{
    protected $timetableService;

    public function __construct(TimetableService $timetableService)
    {
        $this->timetableService = $timetableService;
    }

    /**
     * Get timetable for a specific class
     */
    public function getClassTimetable($classId): JsonResponse
    {
        if (!$this->checkModuleAccess('timetable')) {
            return $this->moduleAccessDenied();
        }

        try {
            $timetable = $this->timetableService->getClassTimetable($classId);

            return $this->successResponse(
                $timetable,
                'Class timetable retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get timetable for a specific teacher
     */
    public function getTeacherTimetable($teacherId): JsonResponse
    {
        if (!$this->checkModuleAccess('timetable')) {
            return $this->moduleAccessDenied();
        }

        try {
            $timetable = $this->timetableService->getTeacherTimetable($teacherId);

            return $this->successResponse(
                $timetable,
                'Teacher timetable retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Create a new schedule entry
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('timetable')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'class_id' => 'required|exists:classes,id',
                'subject_id' => 'required|exists:subjects,id',
                'teacher_id' => 'required|exists:teachers,id',
                'day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'room_number' => 'nullable|string|max:50',
                'notes' => 'nullable|string|max:500'
            ]);

            $schedule = $this->timetableService->createClassSchedule($request->validated());

            return $this->successResponse(
                new TimetableResource($schedule),
                'Schedule created successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Update a schedule entry
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!$this->checkModuleAccess('timetable')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'class_id' => 'sometimes|exists:classes,id',
                'subject_id' => 'sometimes|exists:subjects,id',
                'teacher_id' => 'sometimes|exists:teachers,id',
                'day_of_week' => 'sometimes|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'start_time' => 'sometimes|date_format:H:i',
                'end_time' => 'sometimes|date_format:H:i|after:start_time',
                'room_number' => 'nullable|string|max:50',
                'notes' => 'nullable|string|max:500',
                'is_active' => 'sometimes|boolean'
            ]);

            $schedule = $this->timetableService->updateClassSchedule($id, $request->validated());

            return $this->successResponse(
                new TimetableResource($schedule),
                'Schedule updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Delete a schedule entry
     */
    public function destroy($id): JsonResponse
    {
        if (!$this->checkModuleAccess('timetable')) {
            return $this->moduleAccessDenied();
        }

        try {
            $this->timetableService->deleteClassSchedule($id);

            return $this->successResponse(
                null,
                'Schedule deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get all schedules with optional filters
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('timetable')) {
            return $this->moduleAccessDenied();
        }

        try {
            $filters = $request->only(['class_id', 'teacher_id', 'day_of_week', 'subject_id']);
            $schedules = $this->timetableService->getAll($filters, ['class', 'subject', 'teacher']);

            return $this->successResponse(
                TimetableResource::collection($schedules),
                'Schedules retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get weekly overview for the school with optional filters
     */
    public function getWeeklyOverview(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('timetable')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'class_id' => 'nullable|exists:classes,id',
                'teacher_id' => 'nullable|exists:teachers,id',
                'subject_id' => 'nullable|exists:subjects,id',
                'day_of_week' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'
            ]);

            $filters = $request->only(['class_id', 'teacher_id', 'subject_id', 'day_of_week']);
            $overview = $this->timetableService->getWeeklyOverview($filters);

            return $this->successResponse(
                $overview,
                'Weekly overview retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get filter options for timetable (classes, teachers, subjects)
     */
    public function getFilterOptions(): JsonResponse
    {
        if (!$this->checkModuleAccess('timetable')) {
            return $this->moduleAccessDenied();
        }

        try {
            $options = $this->timetableService->getFilterOptions();

            return $this->successResponse(
                $options,
                'Filter options retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Create bulk timetable for a class
     */
    public function createBulkTimetable(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('timetable')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'class_id' => 'required|exists:classes,id',
                'schedules' => 'required|array|min:1',
                'schedules.*.subject_id' => 'required|exists:subjects,id',
                'schedules.*.teacher_id' => 'required|exists:teachers,id',
                'schedules.*.day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'schedules.*.start_time' => 'required|date_format:H:i',
                'schedules.*.end_time' => 'required|date_format:H:i|after:schedules.*.start_time',
                'schedules.*.room_number' => 'nullable|string|max:50',
                'schedules.*.notes' => 'nullable|string|max:500',
                'replace_existing' => 'sometimes|boolean'
            ]);

            $classId = $request->class_id;
            $schedules = $request->schedules;
            $replaceExisting = $request->get('replace_existing', false);

            $result = $this->timetableService->createBulkTimetable($classId, $schedules, $replaceExisting);

            return $this->successResponse(
                $result,
                'Bulk timetable created successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Update bulk timetable for a class
     */
    public function updateBulkTimetable(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('timetable')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'class_id' => 'required|exists:classes,id',
                'schedules' => 'required|array|min:1',
                'schedules.*.id' => 'nullable|exists:class_schedules,id',
                'schedules.*.subject_id' => 'required|exists:subjects,id',
                'schedules.*.teacher_id' => 'required|exists:teachers,id',
                'schedules.*.day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'schedules.*.start_time' => 'required|date_format:H:i',
                'schedules.*.end_time' => 'required|date_format:H:i|after:schedules.*.start_time',
                'schedules.*.room_number' => 'nullable|string|max:50',
                'schedules.*.notes' => 'nullable|string|max:500',
                'schedules.*.is_active' => 'sometimes|boolean',
                'schedules.*.action' => 'sometimes|in:create,update,delete'
            ]);

            $classId = $request->class_id;
            $schedules = $request->schedules;

            $result = $this->timetableService->updateBulkTimetable($classId, $schedules);

            return $this->successResponse(
                $result,
                'Bulk timetable updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Replace entire timetable for a class
     */
    public function replaceTimetable(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('timetable')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'class_id' => 'required|exists:classes,id',
                'schedules' => 'required|array',
                'schedules.*.subject_id' => 'required|exists:subjects,id',
                'schedules.*.teacher_id' => 'required|exists:teachers,id',
                'schedules.*.day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                'schedules.*.start_time' => 'required|date_format:H:i',
                'schedules.*.end_time' => 'required|date_format:H:i|after:schedules.*.start_time',
                'schedules.*.room_number' => 'nullable|string|max:50',
                'schedules.*.notes' => 'nullable|string|max:500'
            ]);

            $classId = $request->class_id;
            $schedules = $request->schedules;

            $result = $this->timetableService->replaceTimetable($classId, $schedules);

            return $this->successResponse(
                $result,
                'Class timetable replaced successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
