<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SuperAdmin extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'phone',
        'profile_photo',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
