<?php

namespace App\Http\Controllers;

use App\Services\AcademicYearService;
use App\Http\Requests\AcademicYearRequest;
use Illuminate\Http\Request;

class AcademicYearController extends BaseController
{
    public function __construct(
        private AcademicYearService $academicYearService
    ) {}

    /**
     * Display a listing of academic years
     */
    public function index()
    {
        try {
            // Check for either promotion-system or student-management module
            if (!$this->checkModuleAccess('promotion-system') && !$this->checkModuleAccess('student-management')) {
                return $this->moduleAccessDenied();
            }

            $academicYears = $this->academicYearService->getAcademicYearsWithStats();

            return $this->successResponse($academicYears, 'Academic years retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Store a newly created academic year
     */
    public function store(AcademicYearRequest $request)
    {
        try {
            // Check for either promotion-system or student-management module
            if (!$this->checkModuleAccess('promotion-system') && !$this->checkModuleAccess('student-management')) {
                return $this->moduleAccessDenied();
            }

            $academicYear = $this->academicYearService->createAcademicYear($request->validated());

            return $this->successResponse($academicYear, 'Academic year created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Display the specified academic year
     */
    public function show($id)
    {
        try {
            // Check for either promotion-system or student-management module
            if (!$this->checkModuleAccess('promotion-system') && !$this->checkModuleAccess('student-management')) {
                return $this->moduleAccessDenied();
            }

            $academicYear = $this->academicYearService->find($id);

            if (!$academicYear) {
                return $this->errorResponse('Academic year not found', null, 404);
            }

            return $this->successResponse($academicYear, 'Academic year retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Update the specified academic year
     */
    public function update(AcademicYearRequest $request, $id)
    {
        try {
            // Check for either promotion-system or student-management module
            if (!$this->checkModuleAccess('promotion-system') && !$this->checkModuleAccess('student-management')) {
                return $this->moduleAccessDenied();
            }

            $academicYear = $this->academicYearService->update($id, $request->validated());

            return $this->successResponse($academicYear, 'Academic year updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Remove the specified academic year
     */
    public function destroy($id)
    {
        try {
            // Check for either promotion-system or student-management module
            if (!$this->checkModuleAccess('promotion-system') && !$this->checkModuleAccess('student-management')) {
                return $this->moduleAccessDenied();
            }

            $this->academicYearService->delete($id);

            return $this->successResponse(null, 'Academic year deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Set academic year as current
     */
    public function setCurrent($id)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $academicYear = $this->academicYearService->setAsCurrentYear($id);

            return $this->successResponse($academicYear, 'Academic year set as current successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Start promotion period for academic year
     */
    public function startPromotionPeriod($id)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $academicYear = $this->academicYearService->startPromotionPeriod($id);

            return $this->successResponse($academicYear, 'Promotion period started successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Complete academic year
     */
    public function complete($id)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $academicYear = $this->academicYearService->completeAcademicYear($id);

            return $this->successResponse($academicYear, 'Academic year completed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Generate next academic year template
     */
    public function generateNext($id)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $nextYearTemplate = $this->academicYearService->generateNextAcademicYear($id);

            return $this->successResponse($nextYearTemplate, 'Next academic year template generated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Clone promotion criteria from another academic year
     */
    public function cloneCriteria(Request $request, $id)
    {
        try {
            if (!$this->checkModuleAccess('promotion-system')) {
                return $this->moduleAccessDenied();
            }

            $request->validate([
                'from_academic_year_id' => 'required|exists:academic_years,id'
            ]);

            $clonedCount = $this->academicYearService->clonePromotionCriteria(
                $request->from_academic_year_id,
                $id
            );

            return $this->successResponse(
                ['cloned_criteria_count' => $clonedCount],
                "Successfully cloned {$clonedCount} promotion criteria"
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
