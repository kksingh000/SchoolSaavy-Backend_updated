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
        'academic_year',
        'fee_components',
        'total_amount',
        'is_active',
        'description',
    ];

    protected $casts = [
        'fee_components' => 'json',
        'total_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function class()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function studentFees()
    {
        return $this->hasMany(StudentFee::class);
    }
}
