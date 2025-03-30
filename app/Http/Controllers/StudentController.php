<?php

namespace App\Http\Controllers;

use App\Services\StudentService;
use App\Http\Resources\StudentResource;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    protected $studentService;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['class_id', 'status', 'search']);
        $students = $this->studentService->getAll($filters, ['class', 'healthRecord']);
        return StudentResource::collection($students);
    }

    public function store(StoreStudentRequest $request)
    {
        $student = $this->studentService->createStudent($request->validated());
        return new StudentResource($student);
    }

    public function show($id)
    {
        $student = $this->studentService->find($id, ['class', 'healthRecord', 'attendance', 'fees']);
        return new StudentResource($student);
    }

    public function update(UpdateStudentRequest $request, $id)
    {
        $student = $this->studentService->update($id, $request->validated());
        return new StudentResource($student);
    }

    public function destroy($id)
    {
        $this->studentService->delete($id);
        return response()->noContent();
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