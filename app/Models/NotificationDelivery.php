<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationDelivery extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'notification_id',
        'user_id',
        'firebase_token',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'acknowledged_at',
        'firebase_response',
        'error_message',
        'retry_count',
        'last_retry_at'
    ];

    protected $casts = [
        'firebase_response' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'last_retry_at' => 'datetime'
    ];

    /**
     * Status values
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_READ = 'read';
    const STATUS_ACKNOWLEDGED = 'acknowledged';
    const STATUS_FAILED = 'failed';

    /**
     * Get the notification that owns the delivery.
     */
    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    /**
     * Get the user that owns the delivery.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for successful deliveries.
     */
    public function scopeSuccessful($query)
    {
        return $query->whereIn('status', [
            self::STATUS_SENT,
            self::STATUS_DELIVERED,
            self::STATUS_READ,
            self::STATUS_ACKNOWLEDGED
        ]);
    }

    /**
     * Scope for failed deliveries.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for read deliveries.
     */
    public function scopeRead($query)
    {
        return $query->whereIn('status', [self::STATUS_READ, self::STATUS_ACKNOWLEDGED]);
    }

    /**
     * Check if delivery is successful.
     */
    public function isSuccessful(): bool
    {
        return in_array($this->status, [
            self::STATUS_SENT,
            self::STATUS_DELIVERED,
            self::STATUS_READ,
            self::STATUS_ACKNOWLEDGED
        ]);
    }

    /**
     * Check if delivery is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if delivery is read.
     */
    public function isRead(): bool
    {
        return in_array($this->status, [self::STATUS_READ, self::STATUS_ACKNOWLEDGED]);
    }

    /**
     * Mark as sent.
     */
    public function markAsSent($firebaseResponse = null)
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'firebase_response' => $firebaseResponse
        ]);
    }

    /**
     * Mark as delivered.
     */
    public function markAsDelivered()
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now()
        ]);
    }

    /**
     * Mark as read.
     */
    public function markAsRead()
    {
        $this->update([
            'status' => self::STATUS_READ,
            'read_at' => now()
        ]);
    }

    /**
     * Mark as acknowledged.
     */
    public function markAsAcknowledged()
    {
        $this->update([
            'status' => self::STATUS_ACKNOWLEDGED,
            'acknowledged_at' => now()
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed($errorMessage = null)
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Increment retry count.
     */
    public function incrementRetry($errorMessage = null)
    {
        $this->update([
            'retry_count' => $this->retry_count + 1,
            'last_retry_at' => now(),
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Get status options.
     */
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SENT => 'Sent',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_READ => 'Read',
            self::STATUS_ACKNOWLEDGED => 'Acknowledged',
            self::STATUS_FAILED => 'Failed',
        ];
    }
}
