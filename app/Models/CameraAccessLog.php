<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CameraAccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'camera_id',
        'parent_id',
        'student_id',
        'access_start_time',
        'access_end_time',
        'duration_seconds',
        'ip_address',
        'user_agent',
        'device_type',
        'access_result',
        'error_message',
        'session_metadata',
    ];

    protected $casts = [
        'access_start_time' => 'datetime',
        'access_end_time' => 'datetime',
        'session_metadata' => 'array',
    ];

    // Relationships
    public function camera()
    {
        return $this->belongsTo(SchoolCamera::class, 'camera_id');
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    // Scopes
    public function scopeForCamera($query, $cameraId)
    {
        return $query->where('camera_id', $cameraId);
    }

    public function scopeForParent($query, $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('access_result', 'success');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('access_end_time');
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('access_start_time', [$startDate, $endDate]);
    }

    // Methods
    public function endSession($endTime = null)
    {
        $endTime = $endTime ?: now();
        $duration = $endTime->diffInSeconds($this->access_start_time);

        $this->update([
            'access_end_time' => $endTime,
            'duration_seconds' => $duration,
        ]);

        return $this;
    }

    public function getDurationAttribute()
    {
        if ($this->access_end_time) {
            return $this->access_end_time->diffInSeconds($this->access_start_time);
        }

        return now()->diffInSeconds($this->access_start_time);
    }

    public function getFormattedDurationAttribute()
    {
        $seconds = $this->duration;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
        }

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }
}