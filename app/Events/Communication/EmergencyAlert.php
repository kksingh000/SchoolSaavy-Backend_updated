<?php

namespace App\Events\Communication;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyAlert
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $title;
    public string $message;
    public int $schoolId;
    public string $targetType;
    public ?array $targetIds;
    public ?User $sender;

    /**
     * Create a new event instance for emergency notifications
     */
    public function __construct(
        string $title,
        string $message,
        int $schoolId,
        string $targetType = 'all',
        ?array $targetIds = null,
        ?User $sender = null
    ) {
        $this->title = $title;
        $this->message = $message;
        $this->schoolId = $schoolId;
        $this->targetType = $targetType;
        $this->targetIds = $targetIds;
        $this->sender = $sender;
    }
}
