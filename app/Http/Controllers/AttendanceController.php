<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Http\Resources\AttendanceResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AttendanceController extends BaseController
{
    protected $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    public function index(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('attendance')) {
            return $this->moduleAccessDenied();
        }

        try {
            $filters = $request->only(['class_id', 'student_id', 'date', 'status', 'per_page']);
            $attendance = $this->attendanceService->getAll($filters, ['student', 'class', 'markedBy']);

            return $this->successResponse(
                AttendanceResource::collection($attendance),
                'Attendance records retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function markBulkAttendance(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('attendance')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'class_id' => 'required|exists:classes,id',
                'date' => 'required|date',
                'attendances' => 'required|array',
                'attendances.*.student_id' => 'required|exists:students,id',
                'attendances.*.status' => 'required|in:present,absent,late,excused,leave',
                'attendances.*.remarks' => 'nullable|string'
            ]);

            $this->attendanceService->markBulkAttendance($request->all());

            return $this->successResponse(
                null,
                'Bulk attendance marked successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function markSingleAttendance(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('attendance')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'class_id' => 'required|exists:classes,id',
                'student_id' => 'required|exists:students,id',
                'date' => 'required|date',
                'status' => 'required|in:present,absent,late,excused,leave',
                'remarks' => 'nullable|string'
            ]);

            $attendance = $this->attendanceService->markSingleAttendance($request->all());

            return $this->successResponse(
                new AttendanceResource($attendance),
                'Attendance marked successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function getClassAttendanceReport(Request $request, $classId): JsonResponse
    {
        if (!$this->checkModuleAccess('attendance')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date'
            ]);

            $report = $this->attendanceService->getClassAttendanceReport(
                $classId,
                $request->start_date,
                $request->end_date
            );

            return $this->successResponse(
                $report,
                'Class attendance report generated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function getStudentAttendanceReport(Request $request, $studentId): JsonResponse
    {
        if (!$this->checkModuleAccess('attendance')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date'
            ]);

            $report = $this->attendanceService->getStudentAttendanceReport(
                $studentId,
                $request->start_date,
                $request->end_date
            );

            return $this->successResponse(
                $report,
                'Student attendance report generated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function getClassAttendanceByDate(Request $request, $classId): JsonResponse
    {
        if (!$this->checkModuleAccess('attendance')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'date' => 'required|date|before_or_equal:today'
            ]);

            $attendanceData = $this->attendanceService->getClassAttendanceByDate(
                $classId,
                $request->date
            );

            return $this->successResponse(
                $attendanceData,
                'Class attendance retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
