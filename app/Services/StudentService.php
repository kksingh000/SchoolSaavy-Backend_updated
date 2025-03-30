<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class StudentService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = Student::class;
    }

    public function createStudent(array $data)
    {
        DB::beginTransaction();
        try {
            // Add school_id from authenticated user
            $data['school_id'] = auth()->user()->school_id;

            // Generate registration number
            $data['registration_number'] = $this->generateRegistrationNumber();

            // Create student
            $student = $this->create($data);

            // Create related health record if provided
            if (isset($data['health_record'])) {
                $student->healthRecord()->create($data['health_record']);
            }

            DB::commit();
            return $student;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getAttendanceReport($studentId, $startDate, $endDate)
    {
        $student = $this->find($studentId);
        return $student->attendance()
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('status');
    }

    public function getFeeStatus($studentId)
    {
        $student = $this->find($studentId);
        return [
            'total_fees' => $student->fees()->sum('amount'),
            'paid_fees' => $student->fees()->sum('paid_amount'),
            'pending_fees' => $student->fees()->where('status', 'pending')->sum('amount'),
            'overdue_fees' => $student->fees()->where('status', 'overdue')->sum('amount'),
        ];
    }

    protected function generateRegistrationNumber()
    {
        $prefix = date('Y');
        $lastStudent = Student::where('registration_number', 'like', $prefix . '%')
            ->orderBy('registration_number', 'desc')
            ->first();

        $sequence = $lastStudent ? 
            (int)substr($lastStudent->registration_number, -4) + 1 : 
            1;

        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
} 