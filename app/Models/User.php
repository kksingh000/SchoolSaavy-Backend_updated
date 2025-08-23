<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type', // 'admin', 'teacher', 'parent'
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function schoolAdmin()
    {
        return $this->hasOne(SchoolAdmin::class);
    }

    public function superAdmin()
    {
        return $this->hasOne(SuperAdmin::class);
    }

    public function teacher()
    {
        return $this->hasOne(Teacher::class);
    }

    public function parent()
    {
        return $this->hasOne(Parents::class);
    }

    /**
     * Get the school ID for the authenticated user based on their type
     */
    public function getSchoolId()
    {
        return match ($this->user_type) {
            'school_admin' => $this->schoolAdmin?->school_id,
            'teacher' => $this->teacher?->school_id,
            'parent' => $this->parent?->students?->first()?->school_id,
            'super_admin' => null, // Super admins don't belong to any specific school
            default => null
        };
    }

    /**
     * Get the school model for the authenticated user
     */
    public function getSchool()
    {
        $schoolId = $this->getSchoolId();
        return $schoolId ? \App\Models\School::find($schoolId) : null;
    }
}
