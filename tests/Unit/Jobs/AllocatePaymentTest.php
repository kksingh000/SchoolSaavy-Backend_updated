<?php

namespace Tests\Unit\Jobs;

use App\Jobs\AllocatePayment;
use App\Models\FeeInstallment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\StudentFeePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AllocatePaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test payment allocation to installments
     */
    public function testPaymentAllocationToInstallments()
    {
        // Setup
        $schoolId = 1;
        
        // Create a student
        $student = Student::factory()->create([
            'school_id' => $schoolId
        ]);
        
        // Create a student fee plan
        $studentFeePlan = StudentFeePlan::factory()->create([
            'school_id' => $schoolId,
            'student_id' => $student->id,
            'total_amount' => 1000
        ]);
        
        // Create two installments
        $installment1 = FeeInstallment::factory()->create([
            'student_fee_plan_id' => $studentFeePlan->id,
            'amount' => 500,
            'paid_amount' => 0,
            'due_date' => now()->subDays(30),
            'status' => 'Overdue'
        ]);
        
        $installment2 = FeeInstallment::factory()->create([
            'student_fee_plan_id' => $studentFeePlan->id,
            'amount' => 500,
            'paid_amount' => 0,
            'due_date' => now()->addDays(30),
            'status' => 'Pending'
        ]);
        
        // Create a payment
        $payment = Payment::factory()->create([
            'school_id' => $schoolId,
            'student_id' => $student->id,
            'amount' => 750,
            'status' => 'Success'
        ]);
        
        // Mock the cache
        Cache::shouldReceive('forget')
            ->zeroOrMoreTimes();
        
        // Execute the job
        $job = new AllocatePayment($payment->id);
        $job->handle();
        
        // Assert
        // First installment should be paid in full
        $this->assertDatabaseHas('fee_installments', [
            'id' => $installment1->id,
            'paid_amount' => 500,
            'status' => 'Paid'
        ]);
        
        // Second installment should be partially paid
        $this->assertDatabaseHas('fee_installments', [
            'id' => $installment2->id,
            'paid_amount' => 250,
            'status' => 'Pending'
        ]);
        
        // There should be two payment allocations
        $this->assertEquals(2, PaymentAllocation::count());
        
        // The first allocation should be for 500
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'installment_id' => $installment1->id,
            'amount' => 500
        ]);
        
        // The second allocation should be for 250
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'installment_id' => $installment2->id,
            'amount' => 250
        ]);
    }
    
    /**
     * Test job dispatch when recording payment
     */
    public function testJobDispatchOnPaymentRecord()
    {
        // Setup
        Queue::fake();
        
        // Create a student
        $student = Student::factory()->create([
            'school_id' => 1
        ]);
        
        // Dispatch a payment record
        $this->actingAs($student->user)
            ->postJson('/api/fee-management/payments', [
                'school_id' => 1,
                'student_id' => $student->id,
                'amount' => 500,
                'method' => 'Cash',
                'date' => now()->format('Y-m-d')
            ]);
        
        // Assert
        Queue::assertPushed(AllocatePayment::class);
    }
    
    /**
     * Test payment allocation skips failed payments
     */
    public function testPaymentAllocationSkipsFailedPayments()
    {
        // Setup
        $schoolId = 1;
        
        // Create a student
        $student = Student::factory()->create([
            'school_id' => $schoolId
        ]);
        
        // Create a student fee plan
        $studentFeePlan = StudentFeePlan::factory()->create([
            'school_id' => $schoolId,
            'student_id' => $student->id,
            'total_amount' => 1000
        ]);
        
        // Create an installment
        $installment = FeeInstallment::factory()->create([
            'student_fee_plan_id' => $studentFeePlan->id,
            'amount' => 500,
            'paid_amount' => 0,
            'due_date' => now()->subDays(30),
            'status' => 'Overdue'
        ]);
        
        // Create a failed payment
        $payment = Payment::factory()->create([
            'school_id' => $schoolId,
            'student_id' => $student->id,
            'amount' => 500,
            'status' => 'Failed'
        ]);
        
        // Mock the cache
        Cache::shouldReceive('forget')
            ->zeroOrMoreTimes();
        
        // Execute the job
        $job = new AllocatePayment($payment->id);
        $job->handle();
        
        // Assert - installment should remain unpaid
        $this->assertDatabaseHas('fee_installments', [
            'id' => $installment->id,
            'paid_amount' => 0,
            'status' => 'Overdue'
        ]);
        
        // There should be no payment allocations
        $this->assertEquals(0, PaymentAllocation::count());
    }
}
