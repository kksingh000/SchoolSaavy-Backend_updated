<?php

namespace App\Listeners\FeeManagement;

use App\Events\FeeManagement\FeeInstallmentDue;
use App\Jobs\Notifications\SendFeeDueJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendFeeDueNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(FeeInstallmentDue $event): void
    {
        Log::info('📋 SendFeeDueNotification listener started', [
            'installment_id' => $event->installmentId,
            'student_id' => $event->studentId,
            'amount' => $event->amount,
            'due_date' => $event->dueDate,
        ]);

        // Get fresh student with parent relationships
        $student = $event->getStudent();

        // Get all parents of the student
        $parents = $student->parents;

        if ($parents->isEmpty()) {
            Log::warning('⚠️ No parents found for student', [
                'student_id' => $event->studentId,
            ]);
            return;
        }

        Log::info('👨‍👩‍👧 Found parents for fee due notification', [
            'student_id' => $event->studentId,
            'parent_count' => $parents->count(),
        ]);

        // Dispatch notification job for each parent's user
        foreach ($parents as $parent) {
            if ($parent->user) {
                SendFeeDueJob::dispatch(
                    $event->studentId,
                    $parent->user->id,
                    $event->installmentId
                );
                
                Log::info('💼 SendFeeDueJob dispatched', [
                    'student_id' => $event->studentId,
                    'parent_id' => $parent->id,
                    'user_id' => $parent->user->id,
                    'installment_id' => $event->installmentId,
                ]);
            }
        }

        Log::info('✅ SendFeeDueNotification listener completed', [
            'installment_id' => $event->installmentId,
            'jobs_dispatched' => $parents->count(),
        ]);
    }
}
