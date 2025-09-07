<?php

namespace App\Services;

use App\Models\FeeInstallment;
use App\Models\FeeStructure;
use App\Models\FeeStructureComponent;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentFeePlan;
use App\Models\StudentFeePlanComponent;
use App\Models\AcademicYear;
use App\Jobs\GenerateStudentFeeInstallments;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FeeManagementService extends BaseService
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
        parent::__construct();
    }

    protected function initializeModel()
    {
        $this->model = FeeStructure::class;
    }

    /**
     * Get the school ID from the request
     */
    protected function getSchoolId()
    {
        return $this->request->input('school_id');
    }

    /**
     * Create a new fee structure with components
     */
    public function createFeeStructure(array $data)
    {
        DB::beginTransaction();
        try {
            // Ensure school isolation
            $schoolId = $this->getSchoolId();
            $data['school_id'] = $schoolId;

            // Create fee structure
            $feeStructure = FeeStructure::create([
                'school_id' => $schoolId,
                'class_id' => $data['class_id'],
                'academic_year_id' => $data['academic_year_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            // Create fee components
            foreach ($data['components'] as $component) {
                FeeStructureComponent::create([
                    'fee_structure_id' => $feeStructure->id,
                    'component_name' => $component['name'],
                    'amount' => $component['amount'],
                    'frequency' => $component['frequency'],
                ]);
            }

            // Clear cache
            $this->clearCache($schoolId);

            DB::commit();
            
            // Load all relationships that might be needed by the resource
            return $feeStructure->fresh(['components', 'class', 'academicYear', 'school']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get all fee structures for a school with pagination
     */
    public function getAllFeeStructures(array $filters = [])
    {
        $schoolId = $this->getSchoolId();
        $cacheKey = "fee_structures_{$schoolId}_" . md5(json_encode($filters));
        
        return Cache::remember($cacheKey, 300, function () use ($schoolId, $filters) {
            $query = FeeStructure::with(['components', 'class', 'academicYear'])
                ->forSchool($schoolId);
            
            // Apply filters
            if (isset($filters['class_id'])) {
                $query->where('class_id', $filters['class_id']);
            }
            
            if (isset($filters['academic_year_id'])) {
                $query->where('academic_year_id', $filters['academic_year_id']);
            } else {
                // Default to current academic year
                $query->currentYear();
            }
            
            // Search by name
            if (isset($filters['search'])) {
                $query->where('name', 'like', '%' . $filters['search'] . '%');
            }
            
            return $query->orderBy('created_at', 'desc')->paginate(15);
        });
    }

    /**
     * Get a specific fee structure with its components
     */
    public function getFeeStructure($id)
    {
        $schoolId = $this->getSchoolId();
        $cacheKey = "fee_structure_{$schoolId}_{$id}";
        
        return Cache::remember($cacheKey, 300, function () use ($schoolId, $id) {
            return FeeStructure::with(['components', 'class', 'academicYear'])
                ->forSchool($schoolId)
                ->findOrFail($id);
        });
    }

    /**
     * Update a fee structure and its components
     */
    public function updateFeeStructure($id, array $data)
    {
        DB::beginTransaction();
        try {
            $schoolId = $this->getSchoolId();
            $feeStructure = FeeStructure::forSchool($schoolId)->findOrFail($id);
            
            // Update basic info
            $feeStructure->update([
                'name' => $data['name'],
                'class_id' => $data['class_id'],
                'academic_year_id' => $data['academic_year_id'],
                'description' => $data['description'] ?? null,
            ]);
            
            // Handle components if provided
            if (isset($data['components'])) {
                // Remove old components
                FeeStructureComponent::where('fee_structure_id', $id)->delete();
                
                // Create new components
                foreach ($data['components'] as $component) {
                    FeeStructureComponent::create([
                        'fee_structure_id' => $id,
                        'component_name' => $component['name'],
                        'amount' => $component['amount'],
                        'frequency' => $component['frequency'],
                    ]);
                }
            }
            
            // Clear cache
            $this->clearCache($schoolId);
            
            DB::commit();
            return $feeStructure->load(['components', 'class', 'academicYear']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a fee structure and its components
     */
    public function deleteFeeStructure($id)
    {
        DB::beginTransaction();
        try {
            $schoolId = $this->getSchoolId();
            $feeStructure = FeeStructure::forSchool($schoolId)->findOrFail($id);
            
            // Check if fee structure is used in any student fee plans
            $hasFeePlans = StudentFeePlan::where('fee_structure_id', $id)->exists();
            if ($hasFeePlans) {
                throw new \Exception('Cannot delete fee structure as it is assigned to students');
            }
            
            // Delete components first
            FeeStructureComponent::where('fee_structure_id', $id)->delete();
            
            // Delete the fee structure
            $feeStructure->delete();
            
            // Clear cache
            $this->clearCache($schoolId);
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create a student fee plan with components
     */
    public function createStudentFeePlan(array $data)
    {
        DB::beginTransaction();
        try {
            $schoolId = $this->getSchoolId();
            
            // Verify student belongs to school
            $student = Student::forSchool($schoolId)->findOrFail($data['student_id']);
            
            // Verify fee structure belongs to school
            $feeStructure = FeeStructure::with('components')
                ->forSchool($schoolId)
                ->findOrFail($data['fee_structure_id']);
            
            // Check if student already has a fee plan with this fee structure
            $existingPlan = StudentFeePlan::where('school_id', $schoolId)
                ->where('student_id', $data['student_id'])
                ->where('fee_structure_id', $data['fee_structure_id'])
                ->first();
                
            if ($existingPlan) {
                throw new \Exception('A fee plan already exists for this student with the same fee structure');
            }
            
            // Create student fee plan
            $feePlan = StudentFeePlan::create([
                'school_id' => $schoolId,
                'student_id' => $data['student_id'],
                'fee_structure_id' => $data['fee_structure_id'],
                'start_date' => $data['start_date'] ?? now(),
                'end_date' => $data['end_date'] ?? null,
                'is_active' => true,
            ]);
            
            // Create student fee plan components
            if (isset($data['components'])) {
                foreach ($data['components'] as $component) {
                    StudentFeePlanComponent::create([
                        'student_fee_plan_id' => $feePlan->id,
                        'component_id' => $component['component_id'],
                        'is_active' => $component['is_active'] ?? true,
                        'custom_amount' => $component['custom_amount'] ?? null,
                    ]);
                }
            } else {
                // If no components specified, use all components from fee structure
                foreach ($feeStructure->components as $component) {
                    StudentFeePlanComponent::create([
                        'student_fee_plan_id' => $feePlan->id,
                        'component_id' => $component->id,
                        'is_active' => true,
                    ]);
                }
            }
            
            // Dispatch job to generate installments asynchronously
            \App\Jobs\GenerateStudentFeeInstallments::dispatch($feePlan)->onQueue('fee-processing');
            
            // Clear cache
            $this->clearCache($schoolId);
            
            DB::commit();
            return $feePlan->load(['components.component', 'student', 'feeStructure']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate installments for a student fee plan
     */
    protected function generateInstallments($feePlanId)
    {
        $feePlan = StudentFeePlan::with(['components.component', 'feeStructure'])->findOrFail($feePlanId);
        $schoolId = $feePlan->school_id;
        
        // Delete any existing installments
        FeeInstallment::where('student_fee_plan_id', $feePlanId)->delete();
        
        foreach ($feePlan->components as $planComponent) {
            if (!$planComponent->is_active) {
                continue; // Skip inactive components
            }
            
            $component = $planComponent->component;
            $amount = $planComponent->custom_amount ?? $component->amount;
            $startDate = $feePlan->start_date;
            
            // Generate installments based on frequency
            switch ($component->frequency) {
                case 'Monthly':
                    for ($i = 0; $i < 12; $i++) {
                        $dueDate = (clone $startDate)->addMonths($i);
                        $this->createInstallment($schoolId, $feePlanId, $component->id, $i + 1, $dueDate, $amount / 12);
                    }
                    break;
                    
                case 'Quarterly':
                    for ($i = 0; $i < 4; $i++) {
                        $dueDate = (clone $startDate)->addMonths($i * 3);
                        $this->createInstallment($schoolId, $feePlanId, $component->id, $i + 1, $dueDate, $amount / 4);
                    }
                    break;
                    
                case 'Yearly':
                    $this->createInstallment($schoolId, $feePlanId, $component->id, 1, $startDate, $amount);
                    break;
            }
        }
        
        return FeeInstallment::where('student_fee_plan_id', $feePlanId)->get();
    }

    /**
     * Create a single installment
     */
    protected function createInstallment($schoolId, $feePlanId, $componentId, $installmentNo, $dueDate, $amount)
    {
        return FeeInstallment::create([
            'school_id' => $schoolId,
            'student_fee_plan_id' => $feePlanId,
            'component_id' => $componentId,
            'installment_no' => $installmentNo,
            'due_date' => $dueDate,
            'amount' => $amount,
            'status' => 'Pending',
            'paid_amount' => 0,
        ]);
    }

    /**
     * Get all student fee plans for a school with pagination
     */
    public function getAllStudentFeePlans(array $filters = [])
    {
        $schoolId = $this->getSchoolId();
        $cacheKey = "student_fee_plans_{$schoolId}_" . md5(json_encode($filters));
        
        return Cache::remember($cacheKey, 300, function () use ($schoolId, $filters) {
            $query = StudentFeePlan::with(['student', 'feeStructure', 'components.component'])
                ->forSchool($schoolId);
            
            // Apply filters
            if (isset($filters['student_id'])) {
                $query->where('student_id', $filters['student_id']);
            }
            
            if (isset($filters['fee_structure_id'])) {
                $query->where('fee_structure_id', $filters['fee_structure_id']);
            }
            
            if (isset($filters['is_active'])) {
                $query->where('is_active', $filters['is_active']);
            }
            
            // Get student fee plans by class
            if (isset($filters['class_id'])) {
                $query->whereHas('student.classes', function ($q) use ($filters) {
                    $q->where('class_id', $filters['class_id'])
                      ->where('is_active', true);
                });
            }
            
            return $query->orderBy('created_at', 'desc')->paginate(15);
        });
    }

    /**
     * Get a specific student fee plan with its components and installments
     */
    public function getStudentFeePlan($id)
    {
        $schoolId = $this->getSchoolId();
        $cacheKey = "student_fee_plan_{$schoolId}_{$id}";
        
        return Cache::remember($cacheKey, 300, function () use ($schoolId, $id) {
            return StudentFeePlan::with([
                'student', 
                'feeStructure.components', 
                'components.component',
                'installments'
            ])
            ->forSchool($schoolId)
            ->findOrFail($id);
        });
    }

    /**
     * Update a student fee plan and regenerate installments
     */
    public function updateStudentFeePlan($id, array $data)
    {
        DB::beginTransaction();
        try {
            $schoolId = $this->getSchoolId();
            $feePlan = StudentFeePlan::forSchool($schoolId)->findOrFail($id);
            
            // Update basic info
            if (isset($data['start_date']) || isset($data['end_date']) || isset($data['is_active'])) {
                $feePlan->update([
                    'start_date' => $data['start_date'] ?? $feePlan->start_date,
                    'end_date' => $data['end_date'] ?? $feePlan->end_date,
                    'is_active' => $data['is_active'] ?? $feePlan->is_active,
                ]);
            }
            
            // Handle components if provided
            if (isset($data['components'])) {
                // Remove old components
                StudentFeePlanComponent::where('student_fee_plan_id', $id)->delete();
                
                // Create new components
                foreach ($data['components'] as $component) {
                    StudentFeePlanComponent::create([
                        'student_fee_plan_id' => $id,
                        'component_id' => $component['component_id'],
                        'is_active' => $component['is_active'] ?? true,
                        'custom_amount' => $component['custom_amount'] ?? null,
                    ]);
                }
                
                // Dispatch job to regenerate installments asynchronously
                \App\Jobs\GenerateStudentFeeInstallments::dispatch(StudentFeePlan::find($id))->onQueue('fee-processing');
            }
            
            // Clear cache
            $this->clearCache($schoolId);
            
            DB::commit();
            return $feePlan->load(['components.component', 'student', 'feeStructure', 'installments']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get all fee installments for a school with pagination
     */
    public function getAllFeeInstallments(array $filters = [])
    {
        $schoolId = $this->getSchoolId();
        $cacheKey = "fee_installments_{$schoolId}_" . md5(json_encode($filters));
        
        return Cache::remember($cacheKey, 300, function () use ($schoolId, $filters) {
            $query = FeeInstallment::with([
                'studentFeePlan.student',
                'studentFeePlan.feeStructure',
                'component'
            ])
            ->forSchool($schoolId);
            
            // Apply filters
            if (isset($filters['student_id'])) {
                $query->whereHas('studentFeePlan', function ($q) use ($filters) {
                    $q->where('student_id', $filters['student_id']);
                });
            }
            
            if (isset($filters['fee_structure_id'])) {
                $query->whereHas('studentFeePlan', function ($q) use ($filters) {
                    $q->where('fee_structure_id', $filters['fee_structure_id']);
                });
            }
            
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (isset($filters['due_date_from'])) {
                $query->where('due_date', '>=', $filters['due_date_from']);
            }
            
            if (isset($filters['due_date_to'])) {
                $query->where('due_date', '<=', $filters['due_date_to']);
            }
            
            // Sort by due date by default
            return $query->orderBy('due_date', 'asc')->paginate(15);
        });
    }

    /**
     * Record a payment and allocate to installments
     */
    public function recordPayment(array $data)
    {
        DB::beginTransaction();
        try {
            $schoolId = $this->getSchoolId();
            
            // Verify student belongs to school
            $student = Student::forSchool($schoolId)->findOrFail($data['student_id']);
            
            // Create payment record
            $payment = Payment::create([
                'school_id' => $schoolId,
                'student_id' => $data['student_id'],
                'amount' => $data['amount'],
                'method' => $data['method'],
                'date' => $data['date'] ?? now(),
                'status' => $data['status'] ?? 'Success',
                'transaction_id' => $data['transaction_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            
            // Dispatch job to allocate payment asynchronously
            \App\Jobs\AllocatePayment::dispatch($payment->id);
            
            // Clear cache
            $this->clearCache($schoolId);
            
            DB::commit();
            return $payment->load(['allocations.installment', 'student']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get all payments for a school with pagination
     */
    public function getAllPayments(array $filters = [])
    {
        $schoolId = $this->getSchoolId();
        $cacheKey = "payments_{$schoolId}_" . md5(json_encode($filters));
        
        return Cache::remember($cacheKey, 300, function () use ($schoolId, $filters) {
            $query = Payment::with(['student', 'allocations.installment'])
                ->forSchool($schoolId);
            
            // Apply filters
            if (isset($filters['student_id'])) {
                $query->where('student_id', $filters['student_id']);
            }
            
            if (isset($filters['method'])) {
                $query->where('method', $filters['method']);
            }
            
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (isset($filters['date_from'])) {
                $query->where('date', '>=', $filters['date_from']);
            }
            
            if (isset($filters['date_to'])) {
                $query->where('date', '<=', $filters['date_to']);
            }
            
            // Sort by date (newest first) by default
            return $query->orderBy('date', 'desc')->paginate(15);
        });
    }

    /**
     * Get payment details
     */
    public function getPayment($id)
    {
        $schoolId = $this->getSchoolId();
        $cacheKey = "payment_{$schoolId}_{$id}";
        
        return Cache::remember($cacheKey, 300, function () use ($schoolId, $id) {
            return Payment::with([
                'student',
                'allocations.installment.component',
                'allocations.installment.studentFeePlan.feeStructure'
            ])
            ->forSchool($schoolId)
            ->findOrFail($id);
        });
    }

    /**
     * Get student fee summary
     */
    public function getStudentFeeSummary($studentId)
    {
        $schoolId = $this->getSchoolId();
        $cacheKey = "student_fee_summary_{$schoolId}_{$studentId}";
        
        return Cache::remember($cacheKey, 300, function () use ($schoolId, $studentId) {
            // Verify student belongs to school
            $student = Student::forSchool($schoolId)->findOrFail($studentId);
            
            // Get active fee plan
            $activePlan = StudentFeePlan::with([
                'feeStructure',
                'components.component',
                'installments'
            ])
            ->where('student_id', $studentId)
            ->where('is_active', true)
            ->latest()
            ->first();
            
            if (!$activePlan) {
                return [
                    'student' => $student,
                    'has_fee_plan' => false,
                ];
            }
            
            // Calculate fee statistics
            $totalFeeAmount = $activePlan->installments->sum('amount');
            $totalPaidAmount = $activePlan->installments->sum('paid_amount');
            $totalPendingAmount = $totalFeeAmount - $totalPaidAmount;
            
            // Get payment history
            $payments = Payment::with('allocations.installment')
                ->where('student_id', $studentId)
                ->where('status', 'Success')
                ->orderBy('date', 'desc')
                ->get();
            
            // Get upcoming dues
            $upcomingDues = FeeInstallment::with('component')
                ->whereHas('studentFeePlan', function ($query) use ($studentId) {
                    $query->where('student_id', $studentId)
                          ->where('is_active', true);
                })
                ->where(function ($query) {
                    $query->where('status', 'Pending')
                          ->orWhere('status', 'Overdue');
                })
                ->orderBy('due_date', 'asc')
                ->get();
            
            return [
                'student' => $student,
                'has_fee_plan' => true,
                'fee_plan' => $activePlan,
                'statistics' => [
                    'total_fee_amount' => $totalFeeAmount,
                    'total_paid_amount' => $totalPaidAmount,
                    'total_pending_amount' => $totalPendingAmount,
                    'payment_percentage' => $totalFeeAmount > 0 ? ($totalPaidAmount / $totalFeeAmount) * 100 : 0,
                ],
                'payments' => $payments,
                'upcoming_dues' => $upcomingDues,
            ];
        });
    }

    /**
     * Delete a student fee plan
     */
    public function deleteStudentFeePlan($id)
    {
        DB::beginTransaction();
        try {
            $schoolId = $this->getSchoolId();
            $feePlan = StudentFeePlan::forSchool($schoolId)->findOrFail($id);
            
            // Check if installments have payments
            $hasPayments = FeeInstallment::where('student_fee_plan_id', $id)
                ->whereHas('allocations')
                ->exists();
                
            if ($hasPayments) {
                throw new \Exception('Cannot delete fee plan as it has payments associated with it');
            }
            
            // Delete installments
            FeeInstallment::where('student_fee_plan_id', $id)->delete();
            
            // Delete components
            StudentFeePlanComponent::where('student_fee_plan_id', $id)->delete();
            
            // Delete the fee plan
            $feePlan->delete();
            
            // Clear cache
            $this->clearCache($schoolId);
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Process a payment
     */
    public function processPayment(array $data)
    {
        return $this->recordPayment($data);
    }

    /**
     * Get student payment history
     */
    public function getStudentPaymentHistory($studentId, $academicYearId = null, $perPage = 15)
    {
        $schoolId = $this->getSchoolId();
        
        // Verify student belongs to school
        $student = Student::forSchool($schoolId)->findOrFail($studentId);
        
        $query = Payment::with(['allocations.installment.component', 'student'])
            ->forSchool($schoolId)
            ->where('student_id', $studentId);
            
        if ($academicYearId) {
            $query->whereHas('allocations.installment.studentFeePlan', function ($q) use ($academicYearId) {
                $q->whereHas('feeStructure', function ($q2) use ($academicYearId) {
                    $q2->where('academic_year_id', $academicYearId);
                });
            });
        }
        
        return $query->orderBy('date', 'desc')->paginate($perPage);
    }

    /**
     * Get due installments
     */
    public function getDueInstallments($classId = null, $studentId = null, $academicYearId = null, $dueDate = null, $perPage = 15)
    {
        $schoolId = $this->getSchoolId();
        $cacheKey = "due_installments_{$schoolId}_" . md5(json_encode(func_get_args()));
        
        return Cache::remember($cacheKey, 300, function () use ($schoolId, $classId, $studentId, $academicYearId, $dueDate, $perPage) {
            $query = FeeInstallment::with([
                'studentFeePlan.student', 
                'studentFeePlan.feeStructure',
                'component'
            ])
            ->forSchool($schoolId)
            ->where(function ($query) {
                $query->where('status', 'Pending')
                      ->orWhere('status', 'Overdue');
            });
            
            // Filter by class
            if ($classId) {
                $query->whereHas('studentFeePlan.student.classes', function ($q) use ($classId) {
                    $q->where('class_id', $classId)
                      ->where('is_active', true);
                });
            }
            
            // Filter by student
            if ($studentId) {
                $query->whereHas('studentFeePlan', function ($q) use ($studentId) {
                    $q->where('student_id', $studentId);
                });
            }
            
            // Filter by academic year
            if ($academicYearId) {
                $query->whereHas('studentFeePlan.feeStructure', function ($q) use ($academicYearId) {
                    $q->where('academic_year_id', $academicYearId);
                });
            }
            
            // Filter by due date
            if ($dueDate) {
                $query->where('due_date', '<=', $dueDate);
            }
            
            return $query->orderBy('due_date', 'asc')->paginate($perPage);
        });
    }
    
    /**
     * Get student fee details in a student-centric format
     * 
     * @param int|null $classId Filter by class ID
     * @param int|null $studentId Filter by student ID
     * @param int|null $academicYearId Filter by academic year ID
     * @param int $perPage Number of items per page
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getStudentFeeDetails($classId = null, $studentId = null, $academicYearId = null, $perPage = 15)
    {
        $schoolId = $this->getSchoolId();
        $cacheKey = "student_fee_details_{$schoolId}_{$classId}_{$studentId}_{$academicYearId}_{$perPage}";
        
        return Cache::remember($cacheKey, 300, function () use ($schoolId, $classId, $studentId, $academicYearId, $perPage) {
            // Start with student fee plans query - optimized to load only necessary relationships
            $query = StudentFeePlan::with([
                'student:id,first_name,last_name',
                'student.currentClass:id,name',
                'feeStructure:id,academic_year_id',
                'feeStructure.academicYear:id,display_name',
                'installments:id,student_fee_plan_id,amount,paid_amount,due_date',
            ])
            ->where('school_id', $schoolId)
            ->where('is_active', true);
            
            // Apply filters
            if ($studentId) {
                $query->where('student_id', $studentId);
            }
            
            if ($academicYearId) {
                $query->whereHas('feeStructure', function ($q) use ($academicYearId) {
                    $q->where('academic_year_id', $academicYearId);
                });
            }
            
            if ($classId) {
                $query->whereHas('student.classes', function ($q) use ($classId) {
                    $q->where('class_rooms.id', $classId);
                });
            }
            
            // Get the current academic year if not specified
            if (!$academicYearId) {
                $currentAcademicYear = AcademicYear::where('school_id', $schoolId)
                    ->where('is_current', true)
                    ->first();
                    
                if ($currentAcademicYear) {
                    $query->whereHas('feeStructure', function ($q) use ($currentAcademicYear) {
                        $q->where('academic_year_id', $currentAcademicYear->id);
                    });
                }
            }
            
            // Paginate results
            return $query->paginate($perPage);
        });
    }
    
    /**
     * Get detailed fee information for a specific student
     * 
     * @param int $studentId The student ID
     * @param int|null $academicYearId Filter by academic year ID
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getDetailedStudentFeeDetails($studentId, $academicYearId = null)
    {
        $schoolId = $this->getSchoolId();
        $cacheKey = "detailed_student_fee_details_{$schoolId}_{$studentId}_{$academicYearId}";
        
        return Cache::remember($cacheKey, 300, function () use ($schoolId, $studentId, $academicYearId) {
            // Load student with all necessary relationships for detailed view
            $query = StudentFeePlan::with([
                'student.currentClass',
                'feeStructure.academicYear',
                'installments.component',
            ])
            ->where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->where('is_active', true);
            
            // Apply academic year filter if provided
            if ($academicYearId) {
                $query->whereHas('feeStructure', function ($q) use ($academicYearId) {
                    $q->where('academic_year_id', $academicYearId);
                });
            } else {
                // Get the current academic year if not specified
                $currentAcademicYear = AcademicYear::where('school_id', $schoolId)
                    ->where('is_current', true)
                    ->first();
                    
                if ($currentAcademicYear) {
                    $query->whereHas('feeStructure', function ($q) use ($currentAcademicYear) {
                        $q->where('academic_year_id', $currentAcademicYear->id);
                    });
                }
            }
            
            return $query->first();
        });
    }
    
    /**
     * Clear cache related to fee management
     */
    protected function clearCache($schoolId)
    {
        $cachePatterns = [
            "fee_structures_{$schoolId}_*",
            "fee_structure_{$schoolId}_*",
            "student_fee_plans_{$schoolId}_*",
            "student_fee_plan_{$schoolId}_*",
            "fee_installments_{$schoolId}_*",
            "payments_{$schoolId}_*",
            "payment_{$schoolId}_*",
            "student_fee_details_{$schoolId}_*",
            "detailed_student_fee_details_{$schoolId}_*",
            "student_fee_summary_{$schoolId}_*",
            "due_installments_{$schoolId}_*",
        ];
        
        foreach ($cachePatterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}
