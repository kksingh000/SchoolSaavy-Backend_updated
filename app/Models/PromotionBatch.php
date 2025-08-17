<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromotionBatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'batch_name',
        'description',
        'status',
        'total_students',
        'processed_students',
        'promoted_students',
        'failed_students',
        'pending_students',
        'class_filters',
        'processing_log',
        'error_log',
        'created_by',
        'processed_by',
        'processing_started_at',
        'processing_completed_at'
    ];

    protected $casts = [
        'class_filters' => 'array',
        'processing_log' => 'array',
        'processing_started_at' => 'datetime',
        'processing_completed_at' => 'datetime'
    ];

    /**
     * Relationships
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scopes
     */
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeForAcademicYear($query, $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Helper Methods
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isProcessing()
    {
        return $this->status === 'processing';
    }

    public function getProgressPercentage()
    {
        if ($this->total_students === 0) {
            return 0;
        }

        return round(($this->processed_students / $this->total_students) * 100, 2);
    }

    public function getPromotionRate()
    {
        if ($this->processed_students === 0) {
            return 0;
        }

        return round(($this->promoted_students / $this->processed_students) * 100, 2);
    }

    public function getFailureRate()
    {
        if ($this->processed_students === 0) {
            return 0;
        }

        return round(($this->failed_students / $this->processed_students) * 100, 2);
    }

    public function addToProcessingLog($message, $type = 'info')
    {
        $log = $this->processing_log ?? [];
        $log[] = [
            'timestamp' => now()->toISOString(),
            'type' => $type,
            'message' => $message
        ];

        $this->update(['processing_log' => $log]);
    }

    public function addError($error)
    {
        $errors = $this->error_log ? $this->error_log . "\n" : '';
        $errors .= "[" . now()->toDateTimeString() . "] " . $error;

        $this->update(['error_log' => $errors]);
    }

    public function markAsStarted($processedBy)
    {
        $this->update([
            'status' => 'processing',
            'processed_by' => $processedBy,
            'processing_started_at' => now()
        ]);

        $this->addToProcessingLog('Batch processing started', 'info');
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'processing_completed_at' => now()
        ]);

        $this->addToProcessingLog('Batch processing completed successfully', 'success');
    }

    public function markAsFailed($errorMessage)
    {
        $this->update([
            'status' => 'failed',
            'processing_completed_at' => now()
        ]);

        $this->addError($errorMessage);
        $this->addToProcessingLog('Batch processing failed: ' . $errorMessage, 'error');
    }

    public function updateProgress($processed, $promoted, $failed, $pending)
    {
        $this->update([
            'processed_students' => $processed,
            'promoted_students' => $promoted,
            'failed_students' => $failed,
            'pending_students' => $pending
        ]);
    }

    public function getStatusDisplay()
    {
        $statusMap = [
            'created' => 'Created',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed'
        ];

        return $statusMap[$this->status] ?? 'Unknown';
    }

    public function getProcessingTime()
    {
        if (!$this->processing_started_at) {
            return null;
        }

        $endTime = $this->processing_completed_at ?? now();
        return $this->processing_started_at->diffForHumans($endTime, true);
    }
}
