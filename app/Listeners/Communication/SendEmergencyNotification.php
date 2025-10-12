<?php

namespace App\Listeners\Communication;

use App\Events\Communication\EmergencyAlert;
use App\Jobs\Notifications\SendEmergencyAlertJob;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendEmergencyNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(EmergencyAlert $event): void
    {
        // Get recipients based on target type and IDs
        $recipients = $this->getRecipients($event);

        // Dispatch notification job for each recipient
        foreach ($recipients as $recipient) {
            SendEmergencyAlertJob::dispatch(
                $event->schoolId,
                $event->title,
                $event->message,
                $recipient,
                $event->targetType,
                null // Additional data can be added if needed
            );
        }
    }

    /**
     * Get recipients based on target type and IDs
     */
    private function getRecipients(EmergencyAlert $event): \Illuminate\Support\Collection
    {
        // If specific target IDs are provided, get those users
        if ($event->targetIds) {
            return User::whereIn('id', $event->targetIds)->get();
        }

        // Otherwise, get all users of the target type for this school
        $query = User::where('school_id', $event->schoolId);

        // Filter by target type if not 'all'
        if ($event->targetType !== 'all') {
            $query->where('user_type', $event->targetType);
        }

        return $query->get();
    }
}
