<?php

namespace App\Services;

use App\Models\FeeStructure;
use App\Models\StudentFee;
use App\Models\FeePayment;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class FeeStructureService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = FeeStructure::class;
    }

    /**
     * Get all fee structures with advanced filtering and caching
     */
    public function getAll($filters = [], $relations = [])
    {
        $cacheKey = 'fee_structures_' . md5(serialize($filters));

        return Cache::remember($cacheKey, 300, function () use ($filters, $relations) {
            $query = $this->model::query();

            if (!empty($relations)) {
                $query->with($relations);
            }

            // Apply school isolation
            if (isset($filters['school_id'])) {
                $query->where('school_id', $filters['school_id']);
            }

            // Filter by academic year
            if (isset($filters['academic_year_id'])) {
                $query->where('academic_year_id', $filters['academic_year_id']);
            }

            // Filter by class
            if (isset($filters['class_id'])) {
                $query->where('class_id', $filters['class_id']);
            }

            // Filter by active status
            if (isset($filters['is_active'])) {
                $query->where('is_active', $filters['is_active']);
            }

            // Search functionality
            if (isset($filters['search']) && !empty($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('description', 'like', '%' . $filters['search'] . '%');
                });
            }

            return $query->orderBy('created_at', 'desc')->paginate(15);
        });
    }

    /**
     * Create a new fee structure with student fee generation
     */
    /**
     * Override the base create method to handle academic_year field
     */
    public function create(array $data)
    {
        // If no academic_year_id is provided, use the current/active academic year
        if (!isset($data['academic_year_id']) && isset($data['school_id'])) {
            $activeAcademicYear = \App\Models\AcademicYear::where('school_id', $data['school_id'])
                ->current()
                ->first();
            
            if ($activeAcademicYear) {
                $data['academic_year_id'] = $activeAcademicYear->id;
            }
        }

        // Get the academic year display name for the academic_year field
        if (isset($data['academic_year_id'])) {
            $academicYear = \App\Models\AcademicYear::find($data['academic_year_id']);
            if ($academicYear) {
                $data['academic_year'] = $academicYear->display_name;
            }
        }

        return parent::create($data);
    }

    public function createFeeStructure(array $data)
    {
        DB::beginTransaction();
        try {
            // Create fee structure
            $feeStructure = $this->create($data);

            // Generate student fees if class is specified
            if (isset($data['class_id']) && $data['class_id']) {
                $this->generateStudentFeesForStructure($feeStructure->id);
            }

            // Clear cache
            $this->clearFeeStructureCache($data['school_id']);

            DB::commit();
            return $feeStructure->load(['school', 'academicYear', 'class']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing fee structure
     */
    public function updateFeeStructure(int $id, array $data)
    {
        DB::beginTransaction();
        try {
            $feeStructure = $this->find($id);
            $oldClassId = $feeStructure->class_id;

            $feeStructure->update($data);

            // If class changed, regenerate student fees
            if (isset($data['class_id']) && $data['class_id'] !== $oldClassId) {
                // Delete existing student fees for this structure
                StudentFee::where('fee_structure_id', $id)->delete();

                // Generate new student fees if class is specified
                if ($data['class_id']) {
                    $this->generateStudentFeesForStructure($id);
                }
            }

            // Clear cache
            $this->clearFeeStructureCache($feeStructure->school_id);

            DB::commit();
            return $feeStructure->load(['school', 'academicYear', 'class']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Soft delete a fee structure
     */
    public function deleteFeeStructure(int $id)
    {
        DB::beginTransaction();
        try {
            $feeStructure = $this->find($id);

            // Check if there are any payments for this fee structure
            $hasPayments = StudentFee::where('fee_structure_id', $id)
                ->whereHas('payments')
                ->exists();

            if ($hasPayments) {
                throw new \Exception('Cannot delete fee structure with existing payments');
            }

            // Soft delete the fee structure and related student fees
            StudentFee::where('fee_structure_id', $id)->delete();
            $this->delete($id);

            // Clear cache
            $this->clearFeeStructureCache($feeStructure->school_id);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Toggle active status of a fee structure
     */
    public function toggleStatus(int $id)
    {
        $feeStructure = $this->find($id);
        $feeStructure->update(['is_active' => !$feeStructure->is_active]);

        // Clear cache
        $this->clearFeeStructureCache($feeStructure->school_id);

        return $feeStructure;
    }

    /**
     * Generate student fees for a specific fee structure
     */
    public function generateStudentFeesForStructure(int $feeStructureId)
    {
        $feeStructure = $this->find($feeStructureId, ['class']);

        if (!$feeStructure->class_id) {
            throw new \Exception('Fee structure must be associated with a class to generate student fees');
        }

        // Get all active students in the class using the many-to-many relationship
        $students = Student::where('school_id', $feeStructure->school_id)
            ->where('is_active', true)
            ->whereHas('classes', function ($query) use ($feeStructure) {
                $query->where('class_id', $feeStructure->class_id)
                    ->where('class_student.is_active', true);
            })
            ->get();

        $generatedCount = 0;

        foreach ($students as $student) {
            // Check if student fee already exists
            $existingFee = StudentFee::where([
                'student_id' => $student->id,
                'fee_structure_id' => $feeStructureId
            ])->first();

            if (!$existingFee) {
                foreach ($feeStructure->fee_components as $component) {
                    StudentFee::create([
                        'student_id' => $student->id,
                        'fee_structure_id' => $feeStructureId,
                        'component_type' => $component['type'],
                        'component_name' => $component['name'],
                        'amount' => $component['amount'],
                        'due_date' => $component['due_date'] ?? null,
                        'status' => 'pending',
                        'is_mandatory' => $component['is_mandatory'] ?? true,
                    ]);
                    $generatedCount++;
                }
            }
        }

        return [
            'fee_structure_id' => $feeStructureId,
            'students_count' => $students->count(),
            'fees_generated' => $generatedCount,
            'components_per_student' => count($feeStructure->fee_components)
        ];
    }

    /**
     * Get fee structure statistics
     */
    public function getFeeStructureStatistics(int $id)
    {
        $cacheKey = "fee_structure_stats_{$id}";

        return Cache::remember($cacheKey, 300, function () use ($id) {
            $feeStructure = $this->find($id);

            $stats = [
                'fee_structure' => $feeStructure->only(['id', 'name', 'total_amount']),
                'total_students' => 0,
                'total_fees_generated' => 0,
                'total_amount_due' => 0,
                'total_amount_paid' => 0,
                'total_amount_pending' => 0,
                'payment_statistics' => [
                    'paid_count' => 0,
                    'partial_count' => 0,
                    'pending_count' => 0,
                    'overdue_count' => 0,
                ],
                'collection_rate' => 0,
            ];

            $studentFees = StudentFee::where('fee_structure_id', $id)
                ->with(['payments', 'student'])
                ->get();

            $stats['total_fees_generated'] = $studentFees->count();
            $stats['total_students'] = $studentFees->pluck('student_id')->unique()->count();
            $stats['total_amount_due'] = $studentFees->sum('amount');

            foreach ($studentFees as $fee) {
                $totalPaid = $fee->payments->sum('amount');
                $stats['total_amount_paid'] += $totalPaid;

                // Determine fee status
                if ($totalPaid >= $fee->amount) {
                    $stats['payment_statistics']['paid_count']++;
                } elseif ($totalPaid > 0) {
                    $stats['payment_statistics']['partial_count']++;
                } else {
                    $stats['payment_statistics']['pending_count']++;
                }

                // Check if overdue
                if ($fee->due_date && $fee->due_date < Carbon::now() && $totalPaid < $fee->amount) {
                    $stats['payment_statistics']['overdue_count']++;
                }
            }

            $stats['total_amount_pending'] = $stats['total_amount_due'] - $stats['total_amount_paid'];
            $stats['collection_rate'] = $stats['total_amount_due'] > 0
                ? round(($stats['total_amount_paid'] / $stats['total_amount_due']) * 100, 2)
                : 0;

            return $stats;
        });
    }

    /**
     * Clone a fee structure to a new academic year or class
     */
    public function cloneFeeStructure(int $id, array $cloneData)
    {
        DB::beginTransaction();
        try {
            $originalFeeStructure = $this->find($id);

            $newData = $originalFeeStructure->toArray();
            unset($newData['id'], $newData['created_at'], $newData['updated_at'], $newData['deleted_at']);

            // Apply clone modifications
            $newData['name'] = $originalFeeStructure->name . ($cloneData['name_suffix'] ?? ' (Copy)');

            if (isset($cloneData['target_academic_year_id'])) {
                $newData['academic_year_id'] = $cloneData['target_academic_year_id'];
            }

            if (isset($cloneData['target_class_id'])) {
                $newData['class_id'] = $cloneData['target_class_id'];
            }

            $clonedFeeStructure = $this->create($newData);

            // Clear cache
            $this->clearFeeStructureCache($newData['school_id']);

            DB::commit();
            return $clonedFeeStructure->load(['school', 'academicYear', 'class']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Record a fee payment
     */
    public function recordPayment(array $data)
    {
        DB::beginTransaction();
        try {
            $studentFee = StudentFee::findOrFail($data['student_fee_id']);

            // Generate reference number if not provided
            if (empty($data['reference_number'])) {
                $data['reference_number'] = $this->generatePaymentReferenceNumber($studentFee);
            }

            // Create payment record
            $payment = FeePayment::create([
                'student_fee_id' => $studentFee->id,
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'transaction_id' => $data['transaction_id'] ?? null,
                'reference_number' => $data['reference_number'],
                'received_by' => \Illuminate\Support\Facades\Auth::id(),
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'] ?? 'completed',
            ]);

            // Update student fee status
            $this->updateFeeStatus($studentFee);

            // Clear relevant caches
            $this->clearFeeStructureCache($studentFee->feeStructure->school_id);

            DB::commit();
            return $payment;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get student fees with payment status for a school
     */
    public function getStudentFeesWithPayments($filters = [])
    {
        $cacheKey = 'student_fees_payments_' . md5(serialize($filters));

        return Cache::remember($cacheKey, 300, function () use ($filters) {
            $query = StudentFee::with(['student', 'feeStructure', 'payments.receivedBy']);

            // Apply school isolation
            if (isset($filters['school_id'])) {
                $query->whereHas('student', function ($q) use ($filters) {
                    $q->where('school_id', $filters['school_id']);
                });
            }

            // Filter by academic year
            if (isset($filters['academic_year_id'])) {
                $query->whereHas('feeStructure', function ($q) use ($filters) {
                    $q->where('academic_year_id', $filters['academic_year_id']);
                });
            }

            // Filter by class
            if (isset($filters['class_id'])) {
                $query->whereHas('student.classes', function ($q) use ($filters) {
                    $q->where('class_id', $filters['class_id']);
                });
            }

            // Filter by payment status
            if (isset($filters['payment_status'])) {
                $query->where('status', $filters['payment_status']);
            }

            // Filter by fee structure
            if (isset($filters['fee_structure_id'])) {
                $query->where('fee_structure_id', $filters['fee_structure_id']);
            }

            return $query->orderBy('due_date', 'asc')->paginate(15);
        });
    }

    /**
     * Generate payment reference number
     */
    private function generatePaymentReferenceNumber($studentFee)
    {
        $school = $studentFee->student->school;
        $date = now()->format('Ymd');
        $sequence = FeePayment::whereDate('created_at', today())->count() + 1;
        
        return strtoupper($school->code ?? 'SCH') . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Update fee status based on payments
     */
    protected function updateFeeStatus($studentFee)
    {
        $totalPaid = $studentFee->payments()->sum('amount');

        if ($totalPaid >= $studentFee->amount) {
            $studentFee->update(['status' => 'paid']);
        } elseif ($totalPaid > 0) {
            $studentFee->update(['status' => 'partial']);
        } else {
            $studentFee->update(['status' => 'pending']);
        }
    }

    /**
     * Clear fee structure related caches
     */
    protected function clearFeeStructureCache($schoolId)
    {
        $patterns = [
            "fee_structures_*",
            "fee_structure_stats_*",
            "parent_stats_*",
            "dashboard_*_{$schoolId}*"
        ];

        foreach ($patterns as $pattern) {
            Cache::tags(['fee_structures', "school_{$schoolId}"])->flush();
        }
    }
}
