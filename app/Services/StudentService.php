<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class StudentService
{
    public function getAllStudents($filters = [], $perPage = 15)
    {
        $query = Student::with(['school', 'parents'])
            ->where('school_id', request()->school_id);

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('admission_number', 'like', "%{$search}%");
            });
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (isset($filters['blood_group'])) {
            $query->where('blood_group', $filters['blood_group']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['admission_date'])) {
            $query->whereDate('admission_date', $filters['admission_date']);
        }

        // Add ordering for consistent pagination
        $query->orderBy('first_name')->orderBy('last_name')->orderBy('id');

        return $query->paginate($perPage);
    }

    public function createStudent($data)
    {
        DB::beginTransaction();
        try {
            // Handle profile photo upload if present
            if (isset($data['profile_photo'])) {
                $data['profile_photo'] = $this->uploadProfilePhoto($data['profile_photo']);
            }

            // school_id and created_by are already in $data from middleware
            $student = Student::create($data);

            // Create parent-student relationship
            if (isset($data['parent_id'])) {
                $student->parents()->attach($data['parent_id'], [
                    'relationship' => $data['relationship'],
                    'is_primary' => $data['is_primary'] ?? true
                ]);
            }

            DB::commit();
            return $student->load(['school', 'parents']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getStudentById($id)
    {
        return Student::with(['school', 'parents'])
            ->where('school_id', request()->school_id)
            ->findOrFail($id);
    }

    public function updateStudent($id, array $data)
    {
        DB::beginTransaction();
        try {
            Log::info('Updating student with data:', $data);

            $student = Student::where('school_id', request()->school_id)
                ->findOrFail($id);

            // Handle profile photo if provided
            if (isset($data['profile_photo']) && $data['profile_photo'] instanceof UploadedFile) {
                if ($student->profile_photo) {
                    Storage::delete($student->profile_photo);
                }
                $data['profile_photo'] = $this->uploadProfilePhoto($data['profile_photo']);
            }

            $student->update($data);

            DB::commit();

            Log::info('Student updated successfully:', ['id' => $student->id]);

            return $student->fresh()->load(['school', 'parents']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Student update failed:', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function deleteStudent($id)
    {
        DB::beginTransaction();
        try {
            $student = Student::where('school_id', request()->school_id)
                ->findOrFail($id);

            if ($student->profile_photo) {
                Storage::delete($student->profile_photo);
            }

            $student->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getAttendanceReport($studentId, $startDate = null, $endDate = null)
    {
        $student = Student::where('school_id', request()->school_id)
            ->findOrFail($studentId);

        $query = $student->attendance();

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->where('date', '>=', $startDate);
        } elseif ($endDate) {
            $query->where('date', '<=', $endDate);
        } else {
            // Default to current month if no dates provided
            $query->whereBetween('date', [
                now()->startOfMonth(),
                now()->endOfMonth()
            ]);
        }

        $attendanceRecords = $query->orderBy('date', 'desc')->get();

        // Calculate attendance statistics
        $totalDays = $attendanceRecords->count();
        $presentDays = $attendanceRecords->where('status', 'present')->count();
        $absentDays = $attendanceRecords->where('status', 'absent')->count();
        $lateDays = $attendanceRecords->where('status', 'late')->count();
        $excusedDays = $attendanceRecords->where('status', 'excused')->count();

        $attendancePercentage = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0;

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->first_name . ' ' . $student->last_name,
                'admission_number' => $student->admission_number,
            ],
            'period' => [
                'start_date' => $startDate ?? now()->startOfMonth()->format('Y-m-d'),
                'end_date' => $endDate ?? now()->endOfMonth()->format('Y-m-d'),
            ],
            'statistics' => [
                'total_days' => $totalDays,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'late_days' => $lateDays,
                'excused_days' => $excusedDays,
                'attendance_percentage' => $attendancePercentage,
            ],
            'records' => $attendanceRecords->map(function ($record) {
                return [
                    'date' => $record->date,
                    'status' => $record->status,
                    'check_in_time' => $record->check_in_time,
                    'check_out_time' => $record->check_out_time,
                    'remarks' => $record->remarks,
                ];
            }),
        ];
    }

    public function getFeeStatus($studentId)
    {
        $student = Student::where('school_id', request()->school_id)
            ->findOrFail($studentId);

        // Get all fee records for this student
        $feeRecords = $student->fees()->with(['feeStructure', 'payments'])->get();

        $totalFees = $feeRecords->sum('amount');
        $totalPaid = $feeRecords->sum(function ($fee) {
            return $fee->payments->sum('amount');
        });
        $totalPending = $totalFees - $totalPaid;

        // Group fees by status
        $feesByStatus = $feeRecords->groupBy('status');

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->first_name . ' ' . $student->last_name,
                'admission_number' => $student->admission_number,
            ],
            'fee_summary' => [
                'total_fees' => $totalFees,
                'total_paid' => $totalPaid,
                'total_pending' => $totalPending,
                'payment_percentage' => $totalFees > 0 ? round(($totalPaid / $totalFees) * 100, 2) : 0,
            ],
            'fees_by_status' => [
                'paid' => $feesByStatus->get('paid', collect())->map(function ($fee) {
                    return [
                        'id' => $fee->id,
                        'amount' => $fee->amount,
                        'due_date' => $fee->due_date,
                        'fee_structure' => $fee->feeStructure->name ?? 'N/A',
                        'total_paid' => $fee->payments->sum('amount'),
                    ];
                }),
                'partial' => $feesByStatus->get('partial', collect())->map(function ($fee) {
                    return [
                        'id' => $fee->id,
                        'amount' => $fee->amount,
                        'due_date' => $fee->due_date,
                        'fee_structure' => $fee->feeStructure->name ?? 'N/A',
                        'total_paid' => $fee->payments->sum('amount'),
                        'remaining' => $fee->amount - $fee->payments->sum('amount'),
                    ];
                }),
                'pending' => $feesByStatus->get('pending', collect())->map(function ($fee) {
                    return [
                        'id' => $fee->id,
                        'amount' => $fee->amount,
                        'due_date' => $fee->due_date,
                        'fee_structure' => $fee->feeStructure->name ?? 'N/A',
                        'days_overdue' => now()->diffInDays($fee->due_date, false),
                    ];
                }),
            ],
            'recent_payments' => $student->fees()
                ->with(['payments' => function ($query) {
                    $query->latest()->take(5);
                }])
                ->get()
                ->pluck('payments')
                ->flatten()
                ->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'payment_date' => $payment->payment_date,
                        'payment_method' => $payment->payment_method,
                        'transaction_id' => $payment->transaction_id,
                    ];
                }),
        ];
    }

    protected function uploadProfilePhoto($photo)
    {
        return $photo->store('student-photos', 'public');
    }
}
