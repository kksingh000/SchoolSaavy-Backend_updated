<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CameraPermission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'camera_id',
        'parent_id',
        'student_id',
        'access_granted',
        'access_start_time',
        'access_end_time',
        'permission_type',
        'schedule_settings',
        'justification',
        'request_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'access_granted' => 'boolean',
        'access_start_time' => 'datetime',
        'access_end_time' => 'datetime',
        'approved_at' => 'datetime',
        'schedule_settings' => 'array',
    ];

    // Relationships
    public function school()
    {
        return $this->belongsTo(School::class);
    }

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

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeForParent($query, $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeApproved($query)
    {
        return $query->where('request_status', 'approved')->where('access_granted', true);
    }

    public function scopePending($query)
    {
        return $query->where('request_status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->where('access_granted', true)
                    ->where('request_status', 'approved')
                    ->where(function ($q) {
                        $q->where('permission_type', 'permanent')
                          ->orWhere(function ($subQ) {
                              $subQ->where('permission_type', 'temporary')
                                   ->where('access_start_time', '<=', now())
                                   ->where('access_end_time', '>=', now());
                          });
                    });
    }

    // Methods
    public function approve($approvedBy, $startTime = null, $endTime = null)
    {
        $this->update([
            'request_status' => 'approved',
            'access_granted' => true,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'access_start_time' => $startTime ?: now(),
            'access_end_time' => $endTime,
        ]);

        return $this;
    }

    public function reject($rejectedBy, $reason = null)
    {
        $this->update([
            'request_status' => 'rejected',
            'access_granted' => false,
            'approved_by' => $rejectedBy,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $this;
    }

    public function isExpired()
    {
        if ($this->permission_type === 'temporary' && $this->access_end_time) {
            return now()->isAfter($this->access_end_time);
        }

        return false;
    }

    public function isWithinSchedule()
    {
        if ($this->permission_type !== 'scheduled' || !$this->schedule_settings) {
            return true;
        }

        $currentDay = strtolower(now()->format('l'));
        $currentTime = now()->format('H:i');

        $scheduleSettings = $this->schedule_settings;
        
        if (!isset($scheduleSettings['days']) || !in_array($currentDay, $scheduleSettings['days'])) {
            return false;
        }

        if (isset($scheduleSettings['start_time']) && isset($scheduleSettings['end_time'])) {
            return $currentTime >= $scheduleSettings['start_time'] && 
                   $currentTime <= $scheduleSettings['end_time'];
        }

        return true;
    }

    public function canAccess()
    {
        return $this->access_granted && 
               $this->request_status === 'approved' && 
               !$this->isExpired() && 
               $this->isWithinSchedule();
    }
}