<?php

namespace App\Listeners\FeeManagement;

use App\Events\FeeManagement\PaymentDueTomorrow;
use App\Jobs\Notifications\SendPaymentReminderJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentReminderNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(PaymentDueTomorrow $event): void
    {
        // Get all parents of the student
        $parents = $event->student->parents;

        // Dispatch notification job for each parent
        foreach ($parents as $parent) {
            SendPaymentReminderJob::dispatch(
                $event->student,
                $parent,
                $event->installment
            );
        }
    }
}
