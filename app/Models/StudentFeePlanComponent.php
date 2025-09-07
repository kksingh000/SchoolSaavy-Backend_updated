<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentFeePlanComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_fee_plan_id',
        'component_id',
        'is_active',
        'custom_amount',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'custom_amount' => 'decimal:2',
    ];

    public function studentFeePlan()
    {
        return $this->belongsTo(StudentFeePlan::class);
    }

    public function component()
    {
        return $this->belongsTo(FeeStructureComponent::class, 'component_id');
    }

    public function getEffectiveAmountAttribute()
    {
        return $this->custom_amount ?? $this->component->amount;
    }
}
