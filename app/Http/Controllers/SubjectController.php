<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Services\ClassService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SubjectController extends BaseController
{
    protected $classService;

    public function __construct(ClassService $classService)
    {
        $this->classService = $classService;
    }

    public function index(): JsonResponse
    {
        try {
            $schoolId = Auth::user()->getSchoolId();
            $subjects = Subject::where('school_id', $schoolId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            return $this->successResponse(
                $subjects,
                'Subjects retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:10|unique:subjects,code',
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            $schoolId = Auth::user()->getSchoolId();

            $subject = Subject::create([
                'school_id' => $schoolId,
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'is_active' => $request->is_active ?? true,
            ]);

            return $this->successResponse(
                $subject,
                'Subject created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $schoolId = Auth::user()->getSchoolId();
            $subject = Subject::where('school_id', $schoolId)
                ->findOrFail($id);

            return $this->successResponse(
                $subject,
                'Subject retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:10|unique:subjects,code,' . $id,
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            $schoolId = Auth::user()->getSchoolId();
            $subject = Subject::where('school_id', $schoolId)
                ->findOrFail($id);

            $subject->update($request->only(['name', 'code', 'description', 'is_active']));

            return $this->successResponse(
                $subject,
                'Subject updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $schoolId = Auth::user()->getSchoolId();
            $subject = Subject::where('school_id', $schoolId)
                ->findOrFail($id);

            // Check if subject is assigned to any classes
            if ($subject->classes()->count() > 0) {
                return $this->errorResponse(
                    'Cannot delete subject that is assigned to classes. Please remove from classes first.',
                    null,
                    400
                );
            }

            $subject->delete();

            return $this->successResponse(
                null,
                'Subject deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    public function getByClass($classId): JsonResponse
    {
        try {
            $subjects = $this->classService->getClassSubjects($classId);

            return $this->successResponse(
                $subjects,
                'Class subjects retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
