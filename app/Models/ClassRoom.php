<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassRoom extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'classes';

    protected $fillable = [
        'school_id',
        'name',
        'section',
        'grade_level',
        'capacity',
        'class_teacher_id',
        'description',
        'is_active',
        'promotes_to_class_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'grade_level' => 'integer',
        'capacity' => 'integer',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function classTeacher()
    {
        return $this->belongsTo(Teacher::class, 'class_teacher_id');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'class_student', 'class_id', 'student_id')
            ->withPivot(['roll_number', 'enrolled_date', 'left_date', 'is_active'])
            ->withTimestamps();
    }

    public function activeStudents()
    {
        return $this->belongsToMany(Student::class, 'class_student', 'class_id', 'student_id')
            ->withPivot(['roll_number', 'enrolled_date', 'left_date', 'is_active'])
            ->withTimestamps()
            ->wherePivot('is_active', true);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class, 'class_id');
    }

    public function todaysAttendance()
    {
        return $this->hasMany(Attendance::class, 'class_id')
            ->where('date', today());
    }

    public function subjects()
    {
        return $this->belongsToMany(\App\Models\Subject::class, 'class_subject', 'class_id', 'subject_id')
            ->withTimestamps();
    }

    public function schedules()
    {
        return $this->hasMany(ClassSchedule::class, 'class_id');
    }

    public function galleryAlbums()
    {
        return $this->hasMany(GalleryAlbum::class, 'class_id');
    }

    public function promotesTo()
    {
        return $this->belongsTo(ClassRoom::class, 'promotes_to_class_id');
    }

    public function promotesFrom()
    {
        return $this->hasMany(ClassRoom::class, 'promotes_to_class_id');
    }
}
