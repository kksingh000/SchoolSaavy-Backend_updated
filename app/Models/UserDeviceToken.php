<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserDeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_id',
        'firebase_token',
        'device_type',
        'app_version',
        'device_name',
        'is_active',
        'last_used_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime'
    ];

    /**
     * Device types
     */
    const DEVICE_TYPE_ANDROID = 'android';
    const DEVICE_TYPE_IOS = 'ios';
    const DEVICE_TYPE_WEB = 'web';

    /**
     * Get the user that owns the device token.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for active tokens.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific device type.
     */
    public function scopeDeviceType($query, $type)
    {
        return $query->where('device_type', $type);
    }

    /**
     * Scope for recently used tokens.
     */
    public function scopeRecentlyUsed($query, $days = 30)
    {
        return $query->where('last_used_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Mark token as used.
     */
    public function markAsUsed()
    {
        $this->update([
            'last_used_at' => now(),
            'is_active' => true
        ]);
    }

    /**
     * Deactivate token.
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Activate token.
     */
    public function activate()
    {
        $this->update([
            'is_active' => true,
            'last_used_at' => now()
        ]);
    }

    /**
     * Check if token is stale (not used for specified days).
     */
    public function isStale($days = 30): bool
    {
        if (!$this->last_used_at) {
            return true;
        }

        return $this->last_used_at < Carbon::now()->subDays($days);
    }

    /**
     * Get device type options.
     */
    public static function getDeviceTypes(): array
    {
        return [
            self::DEVICE_TYPE_ANDROID => 'Android',
            self::DEVICE_TYPE_IOS => 'iOS',
            self::DEVICE_TYPE_WEB => 'Web',
        ];
    }
}
