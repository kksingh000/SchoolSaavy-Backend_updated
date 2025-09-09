<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeStructureComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'fee_structure_id',
        'master_component_id',
        'component_name',
        'custom_name',
        'amount',
        'frequency',
        'is_required',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_required' => 'boolean',
    ];

    public function feeStructure()
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function masterComponent()
    {
        return $this->belongsTo(MasterFeeComponent::class, 'master_component_id');
    }

    public function studentFeePlanComponents()
    {
        return $this->hasMany(StudentFeePlanComponent::class, 'component_id');
    }

    public function feeInstallments()
    {
        return $this->hasMany(FeeInstallment::class, 'component_id');
    }

    /**
     * Get the component name (custom name or master component name)
     */
    public function getNameAttribute()
    {
        if ($this->custom_name) {
            return $this->custom_name;
        }
        
        if ($this->masterComponent) {
            return $this->masterComponent->name;
        }
        
        return $this->component_name; // Fallback for legacy data
    }

    /**
     * Get the component category from master component
     */
    public function getCategoryAttribute()
    {
        return $this->masterComponent?->category ?? 'academic';
    }

    /**
     * Scope to filter required components
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope to filter optional components
     */
    public function scopeOptional($query)
    {
        return $query->where('is_required', false);
    }
}
