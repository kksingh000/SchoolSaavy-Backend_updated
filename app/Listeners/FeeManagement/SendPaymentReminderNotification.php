<?php

namespace App\Listeners\FeeManagement;

use App\Events\FeeManagement\PaymentDueTomorrow;
use App\Jobs\Notifications\SendPaymentReminderJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentReminderNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(PaymentDueTomorrow $event): void
    {
        $student = $event->getStudent();
        $installment = $event->getInstallment();

        Log::info('🎯 Processing payment reminder notification', [
            'student_id' => $event->studentId,
            'student_name' => $student->first_name . ' ' . $student->last_name,
            'installment_id' => $event->installmentId,
            'due_amount' => $event->dueAmount,
            'due_date' => $event->dueDate,
        ]);

        // Get all parents of the student
        $parents = $student->parents;

        if ($parents->isEmpty()) {
            Log::warning('⚠️ No parents found for student', [
                'student_id' => $event->studentId,
            ]);
            return;
        }

        Log::info('👨‍👩‍👧 Found parents', [
            'count' => $parents->count(),
            'parent_ids' => $parents->pluck('id')->toArray(),
        ]);

        // Dispatch notification job for each parent
        foreach ($parents as $parent) {
            if ($parent->user) {
                Log::info('📤 Dispatching payment reminder notification job', [
                    'parent_id' => $parent->id,
                    'parent_name' => $parent->first_name . ' ' . $parent->last_name,
                    'user_id' => $parent->user->id,
                ]);

                SendPaymentReminderJob::dispatch(
                    $event->studentId,
                    $parent->id,
                    $event->installmentId,
                    $event->dueAmount,
                    $event->dueDate
                );
            }
        }

        Log::info('✅ Successfully dispatched all payment reminder notification jobs', [
            'student_id' => $event->studentId,
            'parents_notified' => $parents->count(),
        ]);
    }
}
