<?php

namespace App\Listeners\FeeManagement;

use App\Events\FeeManagement\PaymentOverdue;
use App\Jobs\Notifications\SendPaymentOverdueJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentOverdueNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(PaymentOverdue $event): void
    {
        // Get all parents of the student
        $parents = $event->student->parents;

        // Dispatch notification job for each parent
        foreach ($parents as $parent) {
            SendPaymentOverdueJob::dispatch(
                $event->student,
                $parent,
                $event->installment,
                $event->daysOverdue
            );
        }
    }
}
