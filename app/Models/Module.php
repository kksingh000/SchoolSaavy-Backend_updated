<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'monthly_price',
        'yearly_price',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'features' => 'json',
        'is_active' => 'boolean',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
    ];

    public function schools()
    {
        return $this->belongsToMany(School::class, 'school_modules')
            ->withPivot(['activated_at', 'expires_at', 'status', 'settings'])
            ->withTimestamps();
    }
}
