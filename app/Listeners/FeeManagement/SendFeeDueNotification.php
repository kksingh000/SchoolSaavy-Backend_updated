<?php

namespace App\Listeners\FeeManagement;

use App\Events\FeeManagement\FeeInstallmentDue;
use App\Jobs\Notifications\SendFeeDueJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendFeeDueNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(FeeInstallmentDue $event): void
    {
        // Get all parents of the student
        $parents = $event->student->parents;

        // Dispatch notification job for each parent
        foreach ($parents as $parent) {
            SendFeeDueJob::dispatch(
                $event->student,
                $parent,
                $event->installment
            );
        }
    }
}
