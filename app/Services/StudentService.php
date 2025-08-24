<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentService
{
    public function getAllStudents($filters = [], $perPage = 15)
    {
        $query = Student::with([
            'school',
            'parents',
            'currentClass' => function ($query) {
                $query->withPivot(['roll_number', 'enrolled_date', 'is_active']);
            }
        ])->where('school_id', request()->school_id);

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('admission_number', 'like', "%{$search}%");
            });
        }

        if (isset($filters['class_id'])) {
            $query->whereHas('currentClass', function ($q) use ($filters) {
                $q->where('classes.id', $filters['class_id']);
            });
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
            $admissionDate = $filters['admission_date'];

            // Handle different date formats
            if (preg_match('/^\d{4}$/', $admissionDate)) {
                // Year only (e.g., "2025")
                $query->whereYear('admission_date', $admissionDate);
            } elseif (preg_match('/^\d{4}-\d{2}$/', $admissionDate)) {
                // Year-Month format (e.g., "2025-08")
                list($year, $month) = explode('-', $admissionDate);
                $query->whereYear('admission_date', $year)
                    ->whereMonth('admission_date', $month);
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $admissionDate)) {
                // Full date format (e.g., "2025-08-19")
                $query->whereDate('admission_date', $admissionDate);
            } else {
                // Try to parse as a date and filter by year if it's valid
                try {
                    $parsedDate = \Carbon\Carbon::parse($admissionDate);
                    $query->whereDate('admission_date', $parsedDate->format('Y-m-d'));
                } catch (\Exception $e) {
                    // If parsing fails, ignore the filter
                    Log::warning('Invalid admission_date filter format: ' . $admissionDate);
                }
            }
        }

        // Add ordering for consistent pagination
        $query->orderBy('first_name')->orderBy('last_name')->orderBy('id');

        return $query->paginate($perPage);
    }

    public function createStudent($data)
    {
        DB::beginTransaction();
        try {
            // Generate admission number if not provided
            if (!isset($data['admission_number']) || empty($data['admission_number'])) {
                $data['admission_number'] = $this->generateAdmissionNumber($data['school_id']);
            }

            // Handle profile photo path if present (expects S3 path string from upload API)
            if (isset($data['profile_photo']) && !empty($data['profile_photo'])) {
                // Validate the path format (should start with uploads/)
                if (!str_starts_with($data['profile_photo'], 'uploads/')) {
                    throw new \Exception('Invalid profile photo path format');
                }
                // Store the S3 path as-is
                $data['profile_photo'] = $data['profile_photo'];
            }

            // school_id and created_by are already in $data from middleware

            // Extract roll number and class assignment data before creating student
            $rollNumber = $data['class_roll_number'] ?? null;
            $classId = $data['class_id'] ?? null;

            // Remove class-related fields from student data as they don't belong to students table directly
            unset($data['class_roll_number'], $data['class_id'], $data['roll_number']);

            $student = Student::create($data);

            // Create parent-student relationship
            if (isset($data['parent_id'])) {
                $student->parents()->attach($data['parent_id'], [
                    'relationship' => $data['relationship'],
                    'is_primary' => $data['is_primary'] ?? true
                ]);
            }

            // Assign student to class if class_id is provided
            if ($classId) {
                $classData = [];
                if ($rollNumber) {
                    $classData['roll_number'] = $rollNumber;
                }
                $this->assignStudentToClass($student, $classId, $classData);
            }

            DB::commit();
            return $student->load([
                'school',
                'parents',
                'currentClass' => function ($query) {
                    $query->withPivot(['roll_number', 'enrolled_date', 'is_active']);
                }
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getStudentById($id)
    {
        return Student::with([
            'school',
            'parents',
            'currentClass' => function ($query) {
                $query->withPivot(['roll_number', 'enrolled_date', 'is_active']);
            }
        ])
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

            // Handle profile photo if provided (expects S3 path string from upload API)
            if (isset($data['profile_photo']) && !empty($data['profile_photo'])) {
                // Validate the path format (should start with uploads/)
                if (!str_starts_with($data['profile_photo'], 'uploads/')) {
                    throw new \Exception('Invalid profile photo path format');
                }

                // If there's an old profile photo, we could optionally delete it
                // but we'll leave that to the frontend to handle via the delete API
                $data['profile_photo'] = $data['profile_photo'];
            } elseif (array_key_exists('profile_photo', $data) && is_null($data['profile_photo'])) {
                // If profile_photo is explicitly set to null, remove the photo
                $data['profile_photo'] = null;
            }

            $student->update($data);

            // Handle class assignment if class_id is provided
            if (array_key_exists('class_id', $data)) {
                $this->handleClassAssignment($student, $data);
            }

            DB::commit();

            Log::info('Student updated successfully:', ['id' => $student->id]);

            return $student->fresh()->load([
                'school',
                'parents',
                'currentClass' => function ($query) {
                    $query->withPivot(['roll_number', 'enrolled_date', 'is_active']);
                }
            ]);
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

            // Note: We no longer automatically delete the profile photo file
            // The frontend should use the FileUploadController's deleteFile method
            // if they want to clean up the S3 files when deleting a student

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

    /**
     * Assign student to a class
     */
    private function assignStudentToClass(Student $student, int $classId, array $data): void
    {
        // Verify the class exists and belongs to the same school
        $class = \App\Models\ClassRoom::where('id', $classId)
            ->where('school_id', $student->school_id)
            ->where('is_active', true)
            ->first();

        if (!$class) {
            throw new \Exception('Invalid class ID or class does not belong to your school');
        }

        // Check if student is already assigned to this class
        $existingAssignment = $student->classes()->where('classes.id', $classId)->first();
        if ($existingAssignment) {
            throw new \Exception('Student is already assigned to this class');
        }

        // Determine roll number for the class
        $rollNumber = $data['class_roll_number'] ?? null;

        // If no roll number provided, auto-generate based on existing students in class
        if (!$rollNumber) {
            $rollNumber = $this->generateNextRollNumber($classId);
        }

        // Validate roll number availability
        $this->validateRollNumber($classId, $rollNumber);

        // Assign student to class
        $student->classes()->attach($classId, [
            'roll_number' => $rollNumber,
            'enrolled_date' => now(),
            'is_active' => true
        ]);
    }

    /**
     * Handle class assignment during student update
     */
    private function handleClassAssignment(Student $student, array $data): void
    {
        if ($data['class_id'] === null) {
            // Remove student from current active class
            $student->classes()->wherePivot('is_active', true)->updateExistingPivot($student->classes()->wherePivot('is_active', true)->pluck('classes.id'), [
                'is_active' => false,
                'left_date' => now()
            ]);
            return;
        }

        $classId = $data['class_id'];

        // Verify the class exists and belongs to the same school
        $class = \App\Models\ClassRoom::where('id', $classId)
            ->where('school_id', $student->school_id)
            ->where('is_active', true)
            ->first();

        if (!$class) {
            throw new \Exception('Invalid class ID or class does not belong to your school');
        }

        // Check if student is already assigned to this class
        $existingAssignment = $student->classes()->where('classes.id', $classId)->first();

        if ($existingAssignment) {
            // If already assigned and active, do nothing
            if ($existingAssignment->pivot->is_active) {
                return;
            }

            // If assigned but inactive, reactivate
            $student->classes()->updateExistingPivot($classId, [
                'is_active' => true,
                'left_date' => null,
                'enrolled_date' => now()
            ]);
            return;
        }

        // Deactivate current class assignment
        $student->classes()->wherePivot('is_active', true)->updateExistingPivot($student->classes()->wherePivot('is_active', true)->pluck('classes.id'), [
            'is_active' => false,
            'left_date' => now()
        ]);

        // Determine roll number for the new class
        $rollNumber = $data['class_roll_number'] ?? null;

        // If no roll number provided, auto-generate
        if (!$rollNumber) {
            $rollNumber = $this->generateNextRollNumber($classId);
        }

        // Validate roll number availability
        $this->validateRollNumber($classId, $rollNumber);

        // Assign student to new class
        $student->classes()->attach($classId, [
            'roll_number' => $rollNumber,
            'enrolled_date' => now(),
            'is_active' => true
        ]);
    }

    /**
     * Generate admission number based on school settings
     */
    private function generateAdmissionNumber($schoolId): string
    {
        // Get admission number settings
        $prefix = \App\Models\SchoolSetting::getSetting($schoolId, 'admission_number_prefix', '');
        $format = \App\Models\SchoolSetting::getSetting($schoolId, 'admission_number_format', 'sequential');
        $startFrom = \App\Models\SchoolSetting::getSetting($schoolId, 'admission_number_start_from', 1);
        $includeYear = \App\Models\SchoolSetting::getSetting($schoolId, 'admission_number_include_year', false);
        $yearFormat = \App\Models\SchoolSetting::getSetting($schoolId, 'admission_number_year_format', 'YYYY');
        $paddingLength = \App\Models\SchoolSetting::getSetting($schoolId, 'admission_number_padding', 4);

        $maxAttempts = 1000;
        $attempts = 0;

        do {
            $number = '';

            // Add prefix if provided
            if ($prefix) {
                $number .= $prefix;
            }

            // Add year if required
            if ($includeYear) {
                $year = now()->format($yearFormat === 'YY' ? 'y' : 'Y');
                $number .= $year;
            }

            // Get next sequential number
            $sequentialNumber = $this->getNextSequentialNumber($schoolId, $format, $startFrom) + $attempts;

            // Pad the number
            $paddedNumber = str_pad($sequentialNumber, $paddingLength, '0', STR_PAD_LEFT);
            $number .= $paddedNumber;

            // Check if this number already exists
            $exists = Student::where('school_id', $schoolId)
                ->where('admission_number', $number)
                ->exists();

            if (!$exists) {
                return $number;
            }

            $attempts++;
        } while ($attempts < $maxAttempts);

        throw new \Exception("Could not generate a unique admission number after {$maxAttempts} attempts");
    }

    /**
     * Get next sequential number based on existing students
     */
    private function getNextSequentialNumber($schoolId, $format, $startFrom): int
    {
        if ($format === 'year_sequential') {
            // Reset sequence each year
            $year = now()->year;
            $prefix = \App\Models\SchoolSetting::getSetting($schoolId, 'admission_number_prefix', '');
            $includeYear = \App\Models\SchoolSetting::getSetting($schoolId, 'admission_number_include_year', false);
            $yearFormat = \App\Models\SchoolSetting::getSetting($schoolId, 'admission_number_year_format', 'YYYY');
            $paddingLength = \App\Models\SchoolSetting::getSetting($schoolId, 'admission_number_padding', 4);

            $yearString = '';
            if ($includeYear) {
                $yearString = now()->format($yearFormat === 'YY' ? 'y' : 'Y');
            }

            $students = Student::where('school_id', $schoolId)
                ->whereYear('created_at', $year)
                ->whereNotNull('admission_number')
                ->pluck('admission_number');

            $maxSequenceNumber = 0;
            foreach ($students as $admissionNumber) {
                $sequenceNumber = $this->extractSequenceNumber($admissionNumber, $prefix, $yearString, $paddingLength);
                if ($sequenceNumber > $maxSequenceNumber) {
                    $maxSequenceNumber = $sequenceNumber;
                }
            }

            return max($maxSequenceNumber + 1, $startFrom);
        } else {
            // Continuous sequence
            $prefix = \App\Models\SchoolSetting::getSetting($schoolId, 'admission_number_prefix', '');
            $paddingLength = \App\Models\SchoolSetting::getSetting($schoolId, 'admission_number_padding', 4);

            $students = Student::where('school_id', $schoolId)
                ->whereNotNull('admission_number')
                ->pluck('admission_number');

            $maxSequenceNumber = 0;
            foreach ($students as $admissionNumber) {
                $sequenceNumber = $this->extractSequenceNumber($admissionNumber, $prefix, '', $paddingLength);
                if ($sequenceNumber > $maxSequenceNumber) {
                    $maxSequenceNumber = $sequenceNumber;
                }
            }

            return max($maxSequenceNumber + 1, $startFrom);
        }
    }

    /**
     * Extract sequence number from admission number
     */
    private function extractSequenceNumber($admissionNumber, $prefix, $yearString, $paddingLength): int
    {
        // Remove prefix
        if ($prefix && str_starts_with($admissionNumber, $prefix)) {
            $admissionNumber = substr($admissionNumber, strlen($prefix));
        }

        // Remove year if present
        if ($yearString && str_starts_with($admissionNumber, $yearString)) {
            $admissionNumber = substr($admissionNumber, strlen($yearString));
        }

        // The remaining should be the sequence number
        if (is_numeric($admissionNumber)) {
            return (int)$admissionNumber;
        }

        return 0;
    }

    /**
     * Generate next available roll number for a class
     */
    private function generateNextRollNumber(int $classId): int
    {
        // Get the highest roll number currently assigned in this class
        $lastRollNumber = DB::table('class_student')
            ->where('class_id', $classId)
            ->where('is_active', true)
            ->max('roll_number');

        // If no students in class, start from 1, otherwise increment
        return $lastRollNumber ? $lastRollNumber + 1 : 1;
    }

    /**
     * Validate if roll number is available in the class
     */
    private function validateRollNumber(int $classId, int $rollNumber): void
    {
        if ($rollNumber < 1) {
            throw new \Exception('Roll number must be greater than 0');
        }

        // Check if roll number is already taken in this class
        $existingRollNumber = DB::table('class_student')
            ->where('class_id', $classId)
            ->where('roll_number', $rollNumber)
            ->where('is_active', true)
            ->exists();

        if ($existingRollNumber) {
            throw new \Exception("Roll number {$rollNumber} is already taken in this class");
        }
    }

    /**
     * Get available roll numbers for a class (useful for frontend)
     */
    public function getAvailableRollNumbers(int $classId, int $limit = 10): array
    {
        // Get all assigned roll numbers for this class
        $assignedRollNumbers = DB::table('class_student')
            ->where('class_id', $classId)
            ->where('is_active', true)
            ->pluck('roll_number')
            ->sort()
            ->values()
            ->toArray();

        $availableNumbers = [];
        $maxRoll = $assignedRollNumbers ? max($assignedRollNumbers) : 0;

        // Find gaps in the sequence (1, 2, 3, 5, 7 -> gaps are 4, 6)
        for ($i = 1; $i <= $maxRoll; $i++) {
            if (!in_array($i, $assignedRollNumbers)) {
                $availableNumbers[] = $i;
                if (count($availableNumbers) >= $limit) break;
            }
        }

        // If we need more numbers, add sequential numbers after the max
        while (count($availableNumbers) < $limit) {
            $availableNumbers[] = ++$maxRoll;
        }

        return $availableNumbers;
    }

    /**
     * Get class roll number statistics
     */
    public function getClassRollNumberStats(int $classId): array
    {
        $assignedRollNumbers = DB::table('class_student')
            ->where('class_id', $classId)
            ->where('is_active', true)
            ->pluck('roll_number')
            ->sort()
            ->values();

        $stats = [
            'total_students' => $assignedRollNumbers->count(),
            'highest_roll_number' => $assignedRollNumbers->max() ?? 0,
            'lowest_roll_number' => $assignedRollNumbers->min() ?? 0,
            'next_available' => $this->generateNextRollNumber($classId),
            'available_gaps' => []
        ];

        // Find gaps in roll number sequence
        if ($assignedRollNumbers->count() > 0) {
            $assignedArray = $assignedRollNumbers->toArray();
            for ($i = 1; $i <= max($assignedArray); $i++) {
                if (!in_array($i, $assignedArray)) {
                    $stats['available_gaps'][] = $i;
                }
            }
        }

        return $stats;
    }
}
