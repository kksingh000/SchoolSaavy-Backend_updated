<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterFeeComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'is_required',
        'default_frequency',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get all fee structure components using this master component
     */
    public function feeStructureComponents()
    {
        return $this->hasMany(FeeStructureComponent::class, 'master_component_id');
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter active components
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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

    /**
     * Get component display name with category
     */
    public function getDisplayNameAttribute()
    {
        return $this->name . ' (' . ucfirst($this->category) . ')';
    }
}
