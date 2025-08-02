<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'created_by',
        'admission_number',
        'roll_number',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'admission_date',
        'blood_group',
        'profile_photo',
        'address',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'admission_date' => 'date',
        'is_active' => 'boolean',
    ];

    // Accessor for full name
    public function getNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function parents()
    {
        return $this->belongsToMany(Parents::class, 'parent_student', 'student_id', 'parent_id')
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }

    public function classes()
    {
        return $this->belongsToMany(ClassRoom::class, 'class_student', 'student_id', 'class_id')
            ->withPivot(['roll_number', 'enrolled_date', 'left_date', 'is_active'])
            ->withTimestamps();
    }

    public function currentClass()
    {
        return $this->belongsToMany(ClassRoom::class, 'class_student', 'student_id', 'class_id')
            ->withPivot(['roll_number', 'enrolled_date', 'left_date', 'is_active'])
            ->withTimestamps()
            ->wherePivot('is_active', true)
            ->latest('pivot_enrolled_date')
            ->limit(1);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function fees()
    {
        return $this->hasMany(StudentFee::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
