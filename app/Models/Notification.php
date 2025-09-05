<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'title',
        'message',
        'type',
        'priority',
        'sender_id',
        'sender_type',
        'target_type',
        'target_ids',
        'target_classes',
        'target_grades',
        'firebase_tokens',
        'firebase_message_id',
        'firebase_response',
        'status',
        'total_recipients',
        'successful_sends',
        'failed_sends',
        'scheduled_at',
        'sent_at',
        'expires_at',
        'is_urgent',
        'requires_acknowledgment',
        'is_broadcast',
        'image_url',
        'action_url',
        'data'
    ];

    protected $casts = [
        'target_ids' => 'array',
        'target_classes' => 'array',
        'target_grades' => 'array',
        'data' => 'array',
        'firebase_tokens' => 'array',
        'firebase_response' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_urgent' => 'boolean',
        'requires_acknowledgment' => 'boolean',
        'is_broadcast' => 'boolean'
    ];

    /**
     * Notification types
     */
    const TYPE_GENERAL = 'general';
    const TYPE_ASSIGNMENT = 'assignment';
    const TYPE_ASSESSMENT = 'assessment';
    const TYPE_ATTENDANCE = 'attendance';
    const TYPE_EVENT = 'event';
    const TYPE_FEE = 'fee';
    const TYPE_RESULT = 'result';
    const TYPE_ANNOUNCEMENT = 'announcement';

    /**
     * Target types
     */
    const TARGET_ALL_PARENTS = 'all_parents';
    const TARGET_ALL_TEACHERS = 'all_teachers';
    const TARGET_SPECIFIC_USERS = 'specific_users';
    const TARGET_CLASS_PARENTS = 'class_parents';
    const TARGET_CLASS_TEACHERS = 'class_teachers';

    /**
     * Priority levels
     */
    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    /**
     * Status values
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_SENDING = 'sending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_PARTIAL = 'partial';

    /**
     * Get the school that owns the notification.
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the user who sent the notification.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the notification deliveries.
     */
    public function deliveries()
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    /**
     * Get successful deliveries.
     */
    public function successfulDeliveries()
    {
        return $this->hasMany(NotificationDelivery::class)
            ->whereIn('status', ['sent', 'delivered', 'read', 'acknowledged']);
    }

    /**
     * Get failed deliveries.
     */
    public function failedDeliveries()
    {
        return $this->hasMany(NotificationDelivery::class)
            ->where('status', 'failed');
    }

    /**
     * Scope for active school notifications.
     */
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Scope for notifications by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for notifications by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for high priority notifications.
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_URGENT]);
    }

    /**
     * Scope for scheduled notifications.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at');
    }

    /**
     * Scope for due scheduled notifications.
     */
    public function scopeDueScheduled($query)
    {
        return $query->scheduled()
            ->where('scheduled_at', '<=', now());
    }

    /**
     * Check if notification is scheduled.
     */
    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED && $this->scheduled_at !== null;
    }

    /**
     * Check if notification is due to be sent.
     */
    public function isDue(): bool
    {
        return $this->isScheduled() && $this->scheduled_at <= now();
    }

    /**
     * Check if notification is sent.
     */
    public function isSent(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_PARTIAL]);
    }

    /**
     * Check if notification is high priority.
     */
    public function isHighPriority(): bool
    {
        return in_array($this->priority, [self::PRIORITY_HIGH, self::PRIORITY_URGENT]);
    }

    /**
     * Get delivery rate percentage.
     */
    public function getDeliveryRate(): float
    {
        if ($this->total_recipients == 0) {
            return 0;
        }

        return ($this->sent_count / $this->total_recipients) * 100;
    }

    /**
     * Get read rate percentage.
     */
    public function getReadRate(): float
    {
        if ($this->sent_count == 0) {
            return 0;
        }

        return ($this->read_count / $this->sent_count) * 100;
    }

    /**
     * Update counts from deliveries.
     */
    public function updateCounts()
    {
        $this->sent_count = $this->successfulDeliveries()->count();
        $this->delivered_count = $this->deliveries()->whereIn('status', ['delivered', 'read', 'acknowledged'])->count();
        $this->read_count = $this->deliveries()->whereIn('status', ['read', 'acknowledged'])->count();
        $this->save();
    }

    /**
     * Mark as sent.
     */
    public function markAsSent()
    {
        $this->update(['status' => self::STATUS_SENT]);
        $this->updateCounts();
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed()
    {
        $this->update(['status' => self::STATUS_FAILED]);
    }

    /**
     * Mark as partial (some delivered, some failed).
     */
    public function markAsPartial()
    {
        $this->update(['status' => self::STATUS_PARTIAL]);
    }

    /**
     * Get notification types array.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_GENERAL => 'General',
            self::TYPE_ASSIGNMENT => 'Assignment',
            self::TYPE_ASSESSMENT => 'Assessment',
            self::TYPE_ATTENDANCE => 'Attendance',
            self::TYPE_EVENT => 'Event',
            self::TYPE_FEE => 'Fee',
            self::TYPE_RESULT => 'Result',
            self::TYPE_ANNOUNCEMENT => 'Announcement',
        ];
    }

    /**
     * Get target types array.
     */
    public static function getTargetTypes(): array
    {
        return [
            self::TARGET_ALL_PARENTS => 'All Parents',
            self::TARGET_ALL_TEACHERS => 'All Teachers',
            self::TARGET_SPECIFIC_USERS => 'Specific Users',
            self::TARGET_CLASS_PARENTS => 'Class Parents',
            self::TARGET_CLASS_TEACHERS => 'Class Teachers',
        ];
    }

    /**
     * Get priorities array.
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_URGENT => 'Urgent',
        ];
    }
}
