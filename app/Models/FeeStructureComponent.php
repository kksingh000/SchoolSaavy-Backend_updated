<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeStructureComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'fee_structure_id',
        'component_name',
        'amount',
        'frequency',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function feeStructure()
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function studentFeePlanComponents()
    {
        return $this->hasMany(StudentFeePlanComponent::class, 'component_id');
    }

    public function feeInstallments()
    {
        return $this->hasMany(FeeInstallment::class, 'component_id');
    }
}
