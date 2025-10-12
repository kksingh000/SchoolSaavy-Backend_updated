<?php

namespace App\Listeners\FeeManagement;

use App\Events\FeeManagement\PaymentReceived;
use App\Jobs\Notifications\SendPaymentConfirmJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPaymentConfirmation implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(PaymentReceived $event): void
    {
        // Get all parents of the student
        $parents = $event->student->parents;

        if ($parents->isEmpty()) {
            return;
        }

        // Dispatch job for each parent
        foreach ($parents as $parent) {
            if ($parent->user) {
                SendPaymentConfirmJob::dispatch(
                    $event->payment,
                    $event->student,
                    $parent->user
                );
            }
        }
    }
}
