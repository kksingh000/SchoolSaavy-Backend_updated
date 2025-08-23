<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'email',
        'website',
        'logo',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function admin()
    {
        return $this->hasOne(SchoolAdmin::class);
    }

    public function schoolAdmin()
    {
        return $this->hasOne(SchoolAdmin::class);
    }

    public function teachers()
    {
        return $this->hasMany(Teacher::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function parents()
    {
        return $this->hasManyThrough(Parents::class, Student::class, 'school_id', 'id', 'id', 'id')
            ->join('parent_student', 'parents.id', '=', 'parent_student.parent_id');
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'school_modules')
            ->withPivot(['activated_at', 'expires_at', 'status', 'settings'])
            ->withTimestamps();
    }

    public function hasActiveModule(string $moduleSlug): bool
    {
        return $this->modules()
            ->where('slug', $moduleSlug)
            ->wherePivot('status', 'active')
            ->exists();
    }
    public function classes()
    {
        return $this->hasMany(ClassRoom::class);
    }

    public function academicYears()
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function promotionCriteria()
    {
        return $this->hasMany(PromotionCriteria::class);
    }

    public function studentPromotions()
    {
        return $this->hasMany(StudentPromotion::class);
    }
}
