<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeStructure extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'name',
        'class_id',
        'academic_year_id',
        'description',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function class()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function components()
    {
        return $this->hasMany(FeeStructureComponent::class);
    }

    public function studentFeePlans()
    {
        return $this->hasMany(StudentFeePlan::class);
    }

    public function scopeForAcademicYear($query, $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeCurrentYear($query)
    {
        return $query->whereHas('academicYear', function ($q) {
            $q->where('is_current', true);
        });
    }

    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }
}
