<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssessmentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
        'display_name',
        'description',
        'frequency',
        'weightage_percentage',
        'sort_order',
        'is_active',
        'is_gradebook_component',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_gradebook_component' => 'boolean',
        'settings' => 'json',
    ];

    // Relationships
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeGradebookComponents($query)
    {
        return $query->where('is_gradebook_component', true);
    }

    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Helper methods
    public function getFrequencyDisplayAttribute()
    {
        return match ($this->frequency) {
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'half_yearly' => 'Half Yearly',
            'yearly' => 'Yearly',
            'custom' => 'Custom',
            default => ucfirst($this->frequency)
        };
    }
}
