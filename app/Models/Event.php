<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'created_by',
        'title',
        'description',
        'type',
        'priority',
        'event_date',
        'start_time',
        'end_time',
        'location',
        'target_audience',
        'affected_classes',
        'is_recurring',
        'recurrence_type',
        'recurrence_end_date',
        'requires_acknowledgment',
        'is_published',
        'published_at',
        'attachments',
    ];

    protected $casts = [
        'event_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'target_audience' => 'array',
        'affected_classes' => 'array',
        'is_recurring' => 'boolean',
        'requires_acknowledgment' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'attachments' => 'array',
        'recurrence_end_date' => 'date',
    ];

    // Relationships
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acknowledgments()
    {
        return $this->hasMany(EventAcknowledgment::class);
    }

    public function acknowledgedBy()
    {
        return $this->belongsToMany(User::class, 'event_acknowledgments', 'event_id', 'user_id')
            ->withPivot(['acknowledged_at', 'comments'])
            ->withTimestamps();
    }

    public function galleryAlbums()
    {
        return $this->hasMany(GalleryAlbum::class, 'event_id');
    }

    public function affectedClasses()
    {
        if (empty($this->affected_classes)) {
            return collect();
        }

        return ClassRoom::whereIn('id', $this->affected_classes)->get();
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('event_date', '>=', today());
    }

    public function scopePast($query)
    {
        return $query->where('event_date', '<', today());
    }

    public function scopeToday($query)
    {
        return $query->where('event_date', now()->format('Y-m-d'));
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeForAudience($query, $audience)
    {
        return $query->whereJsonContains('target_audience', $audience);
    }

    public function scopeForClass($query, $classId)
    {
        return $query->where(function ($q) use ($classId) {
            $q->whereJsonContains('affected_classes', $classId)
                ->orWhereJsonContains('target_audience', 'all');
        });
    }

    // Accessors
    public function getFormattedTimeAttribute()
    {
        if (!$this->start_time && !$this->end_time) {
            return 'All Day';
        }

        if ($this->start_time && $this->end_time) {
            return $this->start_time->format('H:i') . ' - ' . $this->end_time->format('H:i');
        }

        return $this->start_time ? $this->start_time->format('H:i') : '';
    }

    public function getIsUpcomingAttribute()
    {
        return $this->event_date >= today();
    }

    public function getIsTodayAttribute()
    {
        return $this->event_date->isToday();
    }

    public function getDaysUntilEventAttribute()
    {
        return today()->diffInDays($this->event_date, false);
    }

    public function getAcknowledgmentRateAttribute()
    {
        if (!$this->requires_acknowledgment) {
            return null;
        }

        $totalUsers = $this->getTargetUsersCount();
        $acknowledgedCount = $this->acknowledgments()->count();

        return $totalUsers > 0 ? round(($acknowledgedCount / $totalUsers) * 100, 2) : 0;
    }

    // Helper methods
    public function isAcknowledgedBy(User $user)
    {
        return $this->acknowledgments()->where('user_id', $user->id)->exists();
    }

    public function acknowledgeBy(User $user, $comments = null)
    {
        return $this->acknowledgments()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'acknowledged_at' => now(),
                'comments' => $comments,
            ]
        );
    }

    public function getTargetUsersCount()
    {
        // This would need to be implemented based on your user structure
        // For now, return a placeholder
        return 100; // Replace with actual logic
    }

    public function generateRecurringEvents()
    {
        if (!$this->is_recurring || !$this->recurrence_type || !$this->recurrence_end_date) {
            return [];
        }

        $events = [];
        $currentDate = $this->event_date->copy();
        $endDate = $this->recurrence_end_date;

        while ($currentDate <= $endDate) {
            // Skip the original event date
            if (!$currentDate->equalTo($this->event_date)) {
                $eventData = $this->toArray();
                unset($eventData['id'], $eventData['created_at'], $eventData['updated_at']);
                $eventData['event_date'] = $currentDate->format('Y-m-d');
                $events[] = $eventData;
            }

            // Increment based on recurrence type
            switch ($this->recurrence_type) {
                case 'daily':
                    $currentDate->addDay();
                    break;
                case 'weekly':
                    $currentDate->addWeek();
                    break;
                case 'monthly':
                    $currentDate->addMonth();
                    break;
                case 'yearly':
                    $currentDate->addYear();
                    break;
            }
        }

        return $events;
    }
}
