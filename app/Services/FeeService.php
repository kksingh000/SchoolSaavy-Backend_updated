<?php

namespace App\Services;

use App\Models\StudentFee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class FeeService extends FeeStructureService
{
    // Keep existing methods for backward compatibility
    public function createFeeStructure(array $data)
    {
        return parent::createFeeStructure($data);
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

    protected function calculateAndApplyLateFee($fee)
    {
        // Implementation for late fee calculation
        // This can be customized based on school policies
    }
}
