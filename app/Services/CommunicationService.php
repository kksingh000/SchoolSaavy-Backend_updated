<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Message;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class CommunicationService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = Announcement::class;
    }

    public function createAnnouncement(array $data)
    {
        DB::beginTransaction();
        try {
            $data['school_id'] = auth()->user()->getSchoolId();
            $data['created_by'] = auth()->id();

            $announcement = $this->create($data);

            // Handle file attachments if any
            if (isset($data['attachments'])) {
                $this->handleAttachments($announcement, $data['attachments']);
            }

            // Send notifications based on target audience
            $this->sendAnnouncementNotifications($announcement);

            DB::commit();
            return $announcement;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function sendMessage(array $data)
    {
        return Message::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $data['receiver_id'],
            'subject' => $data['subject'],
            'content' => $data['content'],
            'attachments' => $data['attachments'] ?? null,
            'school_id' => auth()->user()->getSchoolId(),
        ]);
    }

    public function getMessages($userId, $filters = [])
    {
        $query = Message::where(function ($q) use ($userId) {
            $q->where('sender_id', $userId)
                ->orWhere('receiver_id', $userId);
        });

        if (isset($filters['unread'])) {
            $query->whereNull('read_at');
        }

        if (isset($filters['date_range'])) {
            $query->whereBetween('created_at', $filters['date_range']);
        }

        return $query->with(['sender', 'receiver'])->latest()->get();
    }

    public function sendBulkNotification(array $data)
    {
        $recipients = $this->getRecipientsByRole($data['target_role']);

        foreach ($recipients as $recipient) {
            Notification::create([
                'user_id' => $recipient->id,
                'title' => $data['title'],
                'content' => $data['content'],
                'type' => $data['type'],
                'school_id' => auth()->user()->getSchoolId(),
            ]);
        }
    }

    protected function handleAttachments($announcement, $attachments)
    {
        foreach ($attachments as $attachment) {
            $path = $attachment->store('announcements');
            $announcement->attachments()->create([
                'file_path' => $path,
                'file_name' => $attachment->getClientOriginalName(),
                'file_type' => $attachment->getClientMimeType(),
            ]);
        }
    }

    protected function getRecipientsByRole($role)
    {
        return User::where('role', $role)
            ->where('school_id', auth()->user()->getSchoolId())
            ->get();
    }
}
