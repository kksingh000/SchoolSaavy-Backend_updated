<?php

namespace App\Services;

use App\Models\FeeStructure;
use App\Models\StudentFee;
use App\Models\FeePayment;
use Illuminate\Support\Facades\DB;

class FeeService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = FeeStructure::class;
    }

    public function createFeeStructure(array $data)
    {
        DB::beginTransaction();
        try {
            $data['school_id'] = auth()->user()->getSchoolId();

            // Create fee structure
            $feeStructure = $this->create($data);

            // Generate fee records for all students in the class
            if (isset($data['class_id'])) {
                $this->generateStudentFees($feeStructure);
            }

            DB::commit();
            return $feeStructure;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function recordPayment(array $data)
    {
        DB::beginTransaction();
        try {
            $studentFee = StudentFee::findOrFail($data['student_fee_id']);

            // Create payment record
            $payment = FeePayment::create([
                'student_fee_id' => $studentFee->id,
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'transaction_id' => $data['transaction_id'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'school_id' => auth()->user()->getSchoolId(),
            ]);

            // Update student fee status
            $this->updateFeeStatus($studentFee);

            DB::commit();
            return $payment;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function generateLateFees()
    {
        $overdueFees = StudentFee::where('due_date', '<', now())
            ->where('status', '!=', 'paid')
            ->get();

        foreach ($overdueFees as $fee) {
            $this->calculateAndApplyLateFee($fee);
        }
    }

    public function getFeeReport($filters = [])
    {
        $query = StudentFee::query();

        if (isset($filters['class_id'])) {
            $query->whereHas('student', function ($q) use ($filters) {
                $q->where('class_id', $filters['class_id']);
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_range'])) {
            $query->whereBetween('due_date', $filters['date_range']);
        }

        return $query->with(['student', 'feeStructure'])->get();
    }

    protected function generateStudentFees($feeStructure)
    {
        $students = $feeStructure->class->students;

        foreach ($students as $student) {
            foreach ($feeStructure->fee_components as $component) {
                StudentFee::create([
                    'student_id' => $student->id,
                    'fee_structure_id' => $feeStructure->id,
                    'amount' => $component['amount'],
                    'due_date' => $component['due_date'],
                    'status' => 'pending',
                    'school_id' => auth()->user()->getSchoolId(),
                ]);
            }
        }
    }

    protected function updateFeeStatus($studentFee)
    {
        $totalPaid = $studentFee->payments()->sum('amount');

        if ($totalPaid >= $studentFee->amount) {
            $studentFee->update(['status' => 'paid']);
        } elseif ($totalPaid > 0) {
            $studentFee->update(['status' => 'partial']);
        }
    }
}
