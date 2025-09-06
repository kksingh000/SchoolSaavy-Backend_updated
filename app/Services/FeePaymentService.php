<?php

namespace App\Services;

use App\Models\FeePayment;
use App\Models\StudentFee;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class FeePaymentService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = FeePayment::class;
    }

    /**
     * Get all fee payments with advanced filtering and caching
     */
    public function getAll($filters = [], $relations = [])
    {
        $cacheKey = 'fee_payments_' . md5(serialize($filters));

        return Cache::remember($cacheKey, 300, function () use ($filters, $relations) {
            $query = $this->model::query();

            if (!empty($relations)) {
                $query->with($relations);
            }

            // Apply school isolation through student relationship
            if (isset($filters['school_id'])) {
                $query->whereHas('studentFee.student', function ($q) use ($filters) {
                    $q->where('school_id', $filters['school_id']);
                });
            }

            // Filter by academic year
            if (isset($filters['academic_year_id'])) {
                $query->whereHas('studentFee.feeStructure', function ($q) use ($filters) {
                    $q->where('academic_year_id', $filters['academic_year_id']);
                });
            }

            // Filter by student
            if (isset($filters['student_id'])) {
                $query->whereHas('studentFee', function ($q) use ($filters) {
                    $q->where('student_id', $filters['student_id']);
                });
            }

            // Filter by class
            if (isset($filters['class_id'])) {
                $query->whereHas('studentFee.student.classes', function ($q) use ($filters) {
                    $q->where('class_id', $filters['class_id']);
                });
            }

            // Filter by payment method
            if (isset($filters['payment_method'])) {
                $query->where('payment_method', $filters['payment_method']);
            }

            // Filter by status
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            // Filter by date range
            if (isset($filters['date_range']) && is_array($filters['date_range'])) {
                $query->whereBetween('payment_date', $filters['date_range']);
            }

            // Search functionality
            if (isset($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('transaction_id', 'like', "%{$search}%")
                      ->orWhere('reference_number', 'like', "%{$search}%")
                      ->orWhereHas('studentFee.student', function ($sq) use ($search) {
                          $sq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('admission_number', 'like', "%{$search}%");
                      });
                });
            }

            return $query->orderBy('payment_date', 'desc')->paginate(15);
        });
    }

    /**
     * Record a fee payment
     */
    public function recordPayment(array $data)
    {
        DB::beginTransaction();
        try {
            // Verify student fee belongs to current school
            $studentFee = StudentFee::with(['student', 'feeStructure'])
                ->findOrFail($data['student_fee_id']);

            /** @var \App\Models\User $user */
            $user = Auth::user();
            $currentSchoolId = $user->getSchoolId();
            if ($studentFee->student->school_id !== $currentSchoolId) {
                throw new \Exception('Unauthorized access to student fee');
            }

            // Generate reference number if not provided
            if (empty($data['reference_number'])) {
                $data['reference_number'] = $this->generateReferenceNumber($studentFee);
            }

            // Create payment record
            $payment = $this->model::create([
                'student_fee_id' => $studentFee->id,
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'transaction_id' => $data['transaction_id'] ?? null,
                'reference_number' => $data['reference_number'],
                'received_by' => $data['received_by'],
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'] ?? 'completed',
            ]);

            // Update student fee status
            $this->updateFeeStatus($studentFee);

            // Clear relevant caches
            $this->clearPaymentCache($studentFee->student->school_id);

            DB::commit();
            return $payment->load(['studentFee.student', 'studentFee.feeStructure', 'receivedBy']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing payment
     */
    public function updatePayment(int $id, array $data)
    {
        DB::beginTransaction();
        try {
            $payment = $this->find($id, ['studentFee.student']);
            $payment->update($data);

            // Update student fee status
            $this->updateFeeStatus($payment->studentFee);

            // Clear relevant caches
            $this->clearPaymentCache($payment->studentFee->student->school_id);

            DB::commit();
            return $payment->load(['studentFee.student', 'studentFee.feeStructure', 'receivedBy']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a payment
     */
    public function deletePayment(int $id)
    {
        DB::beginTransaction();
        try {
            $payment = $this->find($id, ['studentFee.student']);
            $studentFee = $payment->studentFee;

            $payment->delete();

            // Update student fee status after payment deletion
            $this->updateFeeStatus($studentFee);

            // Clear relevant caches
            $this->clearPaymentCache($studentFee->student->school_id);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate fee receipt
     */
    public function generateReceipt(int $paymentId, string $format = 'pdf', bool $download = true)
    {
        $payment = $this->find($paymentId, [
            'studentFee.student',
            'studentFee.feeStructure',
            'receivedBy'
        ]);

        $receiptData = $this->prepareReceiptData($payment);

        if ($format === 'html') {
            return [
                'content' => $this->generateHtmlReceipt($receiptData),
                'receipt_number' => $receiptData['receipt_number'],
            ];
        }

        // Generate PDF receipt
        return $this->generatePdfReceipt($receiptData, $download);
    }

    /**
     * Prepare receipt data
     */
    private function prepareReceiptData($payment)
    {
        $student = $payment->studentFee->student;
        $feeStructure = $payment->studentFee->feeStructure;
        $school = $student->school;

        return [
            'receipt_number' => $payment->reference_number,
            'payment_date' => $payment->payment_date->format('d-m-Y'),
            'school' => [
                'name' => $school->name,
                'address' => $school->address ?? '',
                'phone' => $school->phone ?? '',
                'email' => $school->email ?? '',
                'logo' => $school->logo ?? null,
            ],
            'student' => [
                'name' => $student->first_name . ' ' . $student->last_name,
                'admission_number' => $student->admission_number,
                'class' => $student->classes->first()->name ?? 'N/A',
                'father_name' => $student->father_name ?? 'N/A',
            ],
            'payment' => [
                'amount' => $payment->amount,
                'method' => ucfirst(str_replace('_', ' ', $payment->payment_method)),
                'transaction_id' => $payment->transaction_id,
                'status' => ucfirst($payment->status),
                'received_by' => $payment->receivedBy->name,
            ],
            'fee_structure' => [
                'name' => $feeStructure->name,
                'component_name' => $payment->studentFee->component_name,
                'total_amount' => $payment->studentFee->amount,
                'paid_amount' => $payment->studentFee->payments->sum('amount'),
                'remaining_amount' => $payment->studentFee->amount - $payment->studentFee->payments->sum('amount'),
            ],
            'generated_at' => now()->format('d-m-Y H:i:s'),
        ];
    }

    /**
     * Generate HTML receipt
     */
    private function generateHtmlReceipt($data)
    {
        return view('receipts.fee-payment', $data)->render();
    }

    /**
     * Generate PDF receipt
     */
    private function generatePdfReceipt($data, $download = true)
    {
        $html = $this->generateHtmlReceipt($data);
        
        // For now, return HTML content until PDF library is properly installed
        if ($download) {
            $fileName = "fee_receipt_{$data['receipt_number']}.html";
            $filePath = "receipts/{$fileName}";
            
            Storage::disk('public')->put($filePath, $html);
            
            return [
                'download_url' => asset('storage/' . $filePath),
                'receipt_number' => $data['receipt_number'],
                'file_size' => Storage::disk('public')->size($filePath),
            ];
        }

        return [
            'content' => base64_encode($html),
            'receipt_number' => $data['receipt_number'],
            'file_size' => strlen($html),
        ];
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStatistics($filters = [])
    {
        $cacheKey = 'payment_stats_' . md5(serialize($filters));

        return Cache::remember($cacheKey, 300, function () use ($filters) {
            $query = $this->model::query()
                ->whereHas('studentFee.student', function ($q) use ($filters) {
                    $q->where('school_id', $filters['school_id']);
                });

            // Apply filters
            if (isset($filters['academic_year_id'])) {
                $query->whereHas('studentFee.feeStructure', function ($q) use ($filters) {
                    $q->where('academic_year_id', $filters['academic_year_id']);
                });
            }

            if (isset($filters['class_id'])) {
                $query->whereHas('studentFee.student.classes', function ($q) use ($filters) {
                    $q->where('class_id', $filters['class_id']);
                });
            }

            if (isset($filters['date_range']) && is_array($filters['date_range'])) {
                $query->whereBetween('payment_date', $filters['date_range']);
            }

            // Calculate statistics
            $payments = $query->get();

            $stats = [
                'total_payments' => $payments->count(),
                'total_amount' => $payments->sum('amount'),
                'payment_methods' => $payments->groupBy('payment_method')->map->count(),
                'status_breakdown' => $payments->groupBy('status')->map->count(),
                'monthly_collection' => $payments->groupBy(function ($payment) {
                    return $payment->payment_date->format('Y-m');
                })->map->sum('amount'),
                'daily_collection' => $payments->groupBy(function ($payment) {
                    return $payment->payment_date->format('Y-m-d');
                })->map->sum('amount'),
                'average_payment' => $payments->count() > 0 ? $payments->avg('amount') : 0,
            ];

            return $stats;
        });
    }

    /**
     * Bulk mark fees as paid
     */
    public function bulkMarkAsPaid($data)
    {
        DB::beginTransaction();
        try {
            $studentFeeIds = $data['student_fee_ids'];
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $schoolId = $user->getSchoolId();
            
            // Verify all student fees belong to current school
            $studentFees = StudentFee::with(['student', 'feeStructure'])
                ->whereIn('id', $studentFeeIds)
                ->whereHas('student', function ($q) use ($schoolId) {
                    $q->where('school_id', $schoolId);
                })
                ->get();

            if ($studentFees->count() !== count($studentFeeIds)) {
                throw new \Exception('Some student fees not found or unauthorized');
            }

            $payments = [];
            $successCount = 0;
            $failedCount = 0;

            foreach ($studentFees as $studentFee) {
                try {
                    // Check if fee is already fully paid
                    $totalPaid = $studentFee->payments->sum('amount');
                    $remainingAmount = $studentFee->amount - $totalPaid;

                    if ($remainingAmount <= 0) {
                        $failedCount++;
                        continue;
                    }

                    // Create payment for remaining amount
                    $payment = $this->model::create([
                        'student_fee_id' => $studentFee->id,
                        'amount' => $remainingAmount,
                        'payment_date' => $data['payment_date'],
                        'payment_method' => $data['payment_method'],
                        'reference_number' => $this->generateReferenceNumber($studentFee),
                        'received_by' => $data['received_by'],
                        'notes' => $data['notes'] ?? "Bulk payment - {$data['payment_method']}",
                        'status' => 'completed',
                    ]);

                    // Update fee status
                    $this->updateFeeStatus($studentFee);

                    $payments[] = $payment;
                    $successCount++;

                } catch (\Exception $e) {
                    $failedCount++;
                    \Illuminate\Support\Facades\Log::error("Failed to process payment for student fee {$studentFee->id}: " . $e->getMessage());
                }
            }

            // Clear relevant caches
            $this->clearPaymentCache($schoolId);

            DB::commit();

            return [
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'total_processed' => count($studentFeeIds),
                'payments' => $payments,
                'total_amount' => collect($payments)->sum('amount'),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update fee status based on payments
     */
    private function updateFeeStatus($studentFee)
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
     * Generate reference number
     */
    private function generateReferenceNumber($studentFee)
    {
        $school = $studentFee->student->school;
        $date = now()->format('Ymd');
        $sequence = $this->model::whereDate('created_at', today())->count() + 1;
        
        return strtoupper($school->code ?? 'SCH') . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
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
     * Clear payment related caches
     */
    private function clearPaymentCache($schoolId)
    {
        $patterns = [
            "fee_payments_*",
            "payment_stats_*",
            "fee_structure_stats_*",
            "parent_stats_*",
            "dashboard_*_{$schoolId}*"
        ];

        foreach ($patterns as $pattern) {
            Cache::tags(['fee_payments', "school_{$schoolId}"])->flush();
        }
    }
}
