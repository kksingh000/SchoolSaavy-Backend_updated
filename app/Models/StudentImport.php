<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'user_id',
        'file_name',
        'file_path',
        'file_size',
        'status',
        'total_rows',
        'processed_rows',
        'success_count',
        'failed_count',
        'summary',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'summary' => 'json',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'file_size' => 'integer',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
    ];

    // Relationships
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function errors()
    {
        return $this->hasMany(StudentImportError::class);
    }

    // Scopes
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Helper methods
    public function getProgressPercentage()
    {
        if ($this->total_rows == 0) return 0;
        return round(($this->processed_rows / $this->total_rows) * 100, 2);
    }

    public function isCompleted()
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled']);
    }

    public function hasErrors()
    {
        return $this->failed_count > 0;
    }
}
