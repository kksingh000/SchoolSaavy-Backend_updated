<?php

namespace App\Listeners\FeeManagement;

use App\Events\FeeManagement\PaymentReceived;
use App\Jobs\Notifications\SendPaymentConfirmJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(PaymentReceived $event): void
    {
        try {
            // Load student with relationships from the event
            $student = $event->getStudent();

            Log::info('🎯 SendPaymentConfirmation listener started', [
                'student_id' => $student->id,
                'payment_id' => $event->paymentId,
                'amount' => $event->amount
            ]);

            // Get all parents of the student
            $parents = $student->parents;

            Log::info('👨‍👩‍👧 Found parents for student', [
                'student_id' => $student->id,
                'parents_count' => $parents->count()
            ]);

            if ($parents->isEmpty()) {
                Log::warning('⚠️ No parents found for student', [
                    'student_id' => $student->id
                ]);
                return;
            }

            // Dispatch job for each parent
            foreach ($parents as $parent) {
                Log::info('👤 Processing parent', [
                    'parent_id' => $parent->id,
                    'parent_user_id' => $parent->user_id ?? null,
                    'has_user' => isset($parent->user)
                ]);

                if ($parent->user) {
                    // Pass payment ID instead of model
                    SendPaymentConfirmJob::dispatch(
                        $event->paymentId,
                        $student,
                        $parent->user
                    );

                    Log::info('✅ Payment confirmation job dispatched', [
                        'parent_user_id' => $parent->user->id,
                        'parent_name' => $parent->user->name
                    ]);
                } else {
                    Log::warning('⚠️ Parent has no user account', [
                        'parent_id' => $parent->id
                    ]);
                }
            }

            Log::info('✅ SendPaymentConfirmation listener completed successfully');

        } catch (\Exception $e) {
            Log::error('❌ SendPaymentConfirmation listener failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'student_id' => $event->studentId ?? null
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(PaymentReceived $event, \Throwable $exception): void
    {
        Log::error('💥 SendPaymentConfirmation listener permanently failed', [
            'student_id' => $event->studentId ?? null,
            'payment_id' => $event->paymentId ?? null,
            'error' => $exception->getMessage()
        ]);
    }
}
