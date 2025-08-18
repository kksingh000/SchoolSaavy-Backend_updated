<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Teacher extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'school_id',
        'employee_id',
        'phone',
        'date_of_birth',
        'joining_date',
        'gender',
        'qualification',
        'profile_photo',
        'address',
        'specializations',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'joining_date' => 'date',
        'specializations' => 'json',
    ];

    /**
     * Get the user that owns the teacher profile
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the school that the teacher belongs to
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get classes where this teacher is the class teacher
     */
    public function classes()
    {
        return $this->hasMany(ClassRoom::class, 'class_teacher_id');
    }

    /**
     * Get assignments created by this teacher
     */
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Get class schedules for this teacher
     */
    public function schedules()
    {
        return $this->hasMany(ClassSchedule::class);
    }

    /**
     * Get assignment submissions graded by this teacher
     */
    public function gradedSubmissions()
    {
        return $this->hasMany(AssignmentSubmission::class, 'graded_by');
    }

    /**
     * Scope to filter teachers by school
     */
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Scope to filter active teachers
     */
    public function scopeActive($query)
    {
        return $query->whereHas('user', function ($q) {
            $q->where('is_active', true);
        });
    }

    /**
     * Get teacher's full name from user relationship
     */
    public function getFullNameAttribute()
    {
        return $this->user->name;
    }

    /**
     * Get teacher's email from user relationship
     */
    public function getEmailAttribute()
    {
        return $this->user->email;
    }

    /**
     * Check if teacher has specialization in a subject
     */
    public function hasSpecialization($subject)
    {
        if (!$this->specializations) {
            return false;
        }

        return in_array($subject, $this->specializations);
    }

    /**
     * Get years of experience
     */
    public function getYearsOfExperienceAttribute()
    {
        return $this->joining_date ? $this->joining_date->diffInYears(now()) : 0;
    }

    /**
     * Get the subjects taught by the teacher
     */
    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }
}
