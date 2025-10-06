<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CameraSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'camera_id',
        'schedule_name',
        'day_of_week',
        'start_time',
        'end_time',
        'is_active',
        'schedule_type',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    // Relationships
    public function camera()
    {
        return $this->belongsTo(SchoolCamera::class, 'camera_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDay($query, $dayOfWeek)
    {
        return $query->where('day_of_week', strtolower($dayOfWeek));
    }

    public function scopeForScheduleType($query, $type)
    {
        return $query->where('schedule_type', $type);
    }

    public function scopeCurrentTime($query, $time = null)
    {
        $time = $time ?: now()->format('H:i:s');
        
        return $query->where('start_time', '<=', $time)
                    ->where('end_time', '>=', $time);
    }

    // Methods
    public function isCurrentlyActive()
    {
        if (!$this->is_active) {
            return false;
        }

        $currentDay = strtolower(now()->format('l'));
        $currentTime = now()->format('H:i:s');

        return $this->day_of_week === $currentDay &&
               $this->start_time <= $currentTime &&
               $this->end_time >= $currentTime;
    }

    public function getTimeRangeAttribute()
    {
        return $this->start_time->format('H:i') . ' - ' . $this->end_time->format('H:i');
    }

    public function getDayDisplayNameAttribute()
    {
        return ucfirst($this->day_of_week);
    }
}