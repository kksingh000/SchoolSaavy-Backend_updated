<?php

namespace App\Http\Controllers;

use App\Models\AssessmentType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AssessmentTypeController extends Controller
{
    /**
     * Display a listing of assessment types for the school.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = AssessmentType::where('school_id', $request->school_id)
                ->orderBy('sort_order')
                ->orderBy('name');

            // Filter by active status if requested
            if ($request->has('active_only') && $request->active_only) {
                $query->where('is_active', true);
            }

            // Filter by gradebook components only if requested
            if ($request->has('gradebook_only') && $request->gradebook_only) {
                $query->where('is_gradebook_component', true);
            }

            $assessmentTypes = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Assessment types retrieved successfully',
                'data' => $assessmentTypes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assessment types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created assessment type.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:50',
                'display_name' => 'required|string|max:100',
                'description' => 'nullable|string|max:500',
                'frequency' => 'required|in:weekly,monthly,quarterly,half_yearly,yearly,custom',
                'weightage_percentage' => 'required|integer|min:0|max:100',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
                'settings' => 'nullable|array'
            ]);

            // Check for duplicate name within school
            $exists = AssessmentType::where('school_id', $request->school_id)
                ->where('name', $validated['name'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment type with this name already exists',
                    'errors' => ['name' => ['This assessment type name is already in use']]
                ], 422);
            }

            $validated['school_id'] = $request->school_id;
            $validated['settings'] = json_encode($validated['settings'] ?? []);

            // Set default sort order if not provided
            if (!isset($validated['sort_order'])) {
                $maxOrder = AssessmentType::where('school_id', $request->school_id)
                    ->max('sort_order') ?? 0;
                $validated['sort_order'] = $maxOrder + 1;
            }

            $assessmentType = AssessmentType::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Assessment type created successfully',
                'data' => $assessmentType
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
                'message' => 'Failed to create assessment type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified assessment type.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $assessmentType = AssessmentType::where('school_id', $request->school_id)
                ->where('id', $id)
                ->first();

            if (!$assessmentType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment type not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Assessment type retrieved successfully',
                'data' => $assessmentType
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assessment type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified assessment type.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $assessmentType = AssessmentType::where('school_id', $request->school_id)
                ->where('id', $id)
                ->first();

            if (!$assessmentType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment type not found'
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:50',
                'display_name' => 'sometimes|required|string|max:100',
                'description' => 'nullable|string|max:500',
                'frequency' => 'sometimes|required|in:weekly,monthly,quarterly,half_yearly,yearly,custom',
                'weightage_percentage' => 'sometimes|required|integer|min:0|max:100',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
                'settings' => 'nullable|array'
            ]);

            // Check for duplicate name within school (excluding current record)
            if (isset($validated['name'])) {
                $exists = AssessmentType::where('school_id', $request->school_id)
                    ->where('name', $validated['name'])
                    ->where('id', '!=', $id)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Assessment type with this name already exists',
                        'errors' => ['name' => ['This assessment type name is already in use']]
                    ], 422);
                }
            }

            if (isset($validated['settings'])) {
                $validated['settings'] = json_encode($validated['settings']);
            }

            $assessmentType->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Assessment type updated successfully',
                'data' => $assessmentType->fresh()
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
                'message' => 'Failed to update assessment type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified assessment type.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $assessmentType = AssessmentType::where('school_id', $request->school_id)
                ->where('id', $id)
                ->first();

            if (!$assessmentType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment type not found'
                ], 404);
            }

            // Check if there are any assessments using this type
            $assessmentCount = $assessmentType->assessments()->count();
            if ($assessmentCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete assessment type as it is being used in assessments',
                    'data' => ['assessment_count' => $assessmentCount]
                ], 422);
            }

            $assessmentType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Assessment type deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete assessment type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get only active assessment types.
     */
    public function getActive(Request $request): JsonResponse
    {
        try {
            $assessmentTypes = AssessmentType::where('school_id', $request->school_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Active assessment types retrieved successfully',
                'data' => $assessmentTypes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active assessment types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assessment types that are gradebook components.
     */
    public function getGradebookComponents(Request $request): JsonResponse
    {
        try {
            $assessmentTypes = AssessmentType::where('school_id', $request->school_id)
                ->where('is_active', true)
                ->where('is_gradebook_component', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Gradebook component assessment types retrieved successfully',
                'data' => $assessmentTypes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve gradebook component assessment types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle the active status of an assessment type.
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        try {
            $assessmentType = AssessmentType::where('school_id', $request->school_id)
                ->where('id', $id)
                ->first();

            if (!$assessmentType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assessment type not found'
                ], 404);
            }

            $assessmentType->is_active = !$assessmentType->is_active;
            $assessmentType->save();

            return response()->json([
                'success' => true,
                'message' => 'Assessment type status updated successfully',
                'data' => $assessmentType
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle assessment type status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
