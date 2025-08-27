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

    public function index(Request $request): JsonResponse
    {
        try {
            $schoolId = $request->school_id;
            $perPage = $request->get('per_page', 15); // Default 15 items per page
            $search = $request->get('search');
            $status = $request->get('is_active'); // active, inactive, all

            $query = Subject::where('school_id', $schoolId);

            // Apply status filter
            if ($status === 1 || $status === '1') {
                $query->where('is_active', true);
            } elseif ($status === 0 || $status === '0') {
                $query->where('is_active', false);
            }
            // For 'all' status, no additional where clause needed

            // Apply search filter
            if ($search) {
                $search = trim($search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('code', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            $subjects = $query->orderBy('name')
                ->paginate($perPage);

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

            $schoolId = $request->school_id;

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

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $schoolId = $request->school_id;
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

            $schoolId = $request->school_id;
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

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $schoolId = $request->school_id;
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

    /**
     * Get subjects by teacher ID
     * Returns all subjects that a teacher teaches based on class schedules
     */
    public function getByTeacher(Request $request, $teacherId): JsonResponse
    {
        try {
            $schoolId = $request->school_id;

            // Get subjects that the teacher teaches through class schedules
            $subjects = Subject::where('subjects.school_id', $schoolId)
                ->join('class_schedules', 'subjects.id', '=', 'class_schedules.subject_id')
                ->where('class_schedules.teacher_id', $teacherId)
                ->where('class_schedules.is_active', true)
                ->where('subjects.is_active', true)
                ->select([
                    'subjects.id',
                    'subjects.name',
                    'subjects.code',
                    'subjects.description',
                    'subjects.is_active',
                    'subjects.created_at',
                    'subjects.updated_at'
                ])
                ->distinct()
                ->orderBy('subjects.name')
                ->get();

            // Add additional data about classes where teacher teaches each subject
            $subjects->each(function ($subject) use ($teacherId, $schoolId) {
                $classes = \App\Models\ClassRoom::join('class_schedules', 'classes.id', '=', 'class_schedules.class_id')
                    ->where('class_schedules.teacher_id', $teacherId)
                    ->where('class_schedules.subject_id', $subject->id)
                    ->where('class_schedules.is_active', true)
                    ->where('classes.school_id', $schoolId)
                    ->where('classes.is_active', true)
                    ->select([
                        'classes.id',
                        'classes.name',
                        'classes.section',
                        'classes.grade_level'
                    ])
                    ->distinct()
                    ->get();

                $subject->classes = $classes;
                $subject->classes_count = $classes->count();
            });

            return $this->successResponse(
                $subjects,
                'Teacher subjects retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
