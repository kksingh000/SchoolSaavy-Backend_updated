<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassPromotionMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'from_class_id',
        'to_class_id',
        'promotion_order',
        'is_active',
        'mapping_name',
        'description'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'promotion_order' => 'integer'
    ];

    /**
     * Relationships
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function fromClass()
    {
        return $this->belongsTo(ClassRoom::class, 'from_class_id');
    }

    public function toClass()
    {
        return $this->belongsTo(ClassRoom::class, 'to_class_id');
    }

    /**
     * Scopes
     */
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrderedByPromotion($query)
    {
        return $query->orderBy('promotion_order')->orderBy('id');
    }

    /**
     * Get promotion mapping display name
     */
    public function getDisplayNameAttribute()
    {
        return $this->mapping_name ?: "{$this->fromClass->name} → {$this->toClass->name}";
    }

    /**
     * Static methods for easy access
     */
    public static function getPromotionPathsForSchool($schoolId)
    {
        return static::forSchool($schoolId)
            ->active()
            ->with(['fromClass', 'toClass'])
            ->orderBy('promotion_order')
            ->get()
            ->groupBy('from_class_id')
            ->map(function ($mappings) {
                return $mappings->map(function ($mapping) {
                    return [
                        'id' => $mapping->id,
                        'to_class_id' => $mapping->to_class_id,
                        'to_class_name' => $mapping->toClass->name,
                        'mapping_name' => $mapping->display_name,
                        'promotion_order' => $mapping->promotion_order
                    ];
                });
            });
    }

    /**
     * Get all promotion batches that can be created with one click
     */
    public static function getPromotionBatchSuggestions($schoolId)
    {
        $mappings = static::forSchool($schoolId)
            ->active()
            ->with(['fromClass', 'toClass'])
            ->orderBy('promotion_order')
            ->get();

        $batches = [];
        $processedFromClasses = [];

        foreach ($mappings as $mapping) {
            $fromClassId = $mapping->from_class_id;

            if (!in_array($fromClassId, $processedFromClasses)) {
                // Find all classes that can be promoted together
                $similarMappings = $mappings->where('promotion_order', $mapping->promotion_order);

                $sourceClasses = [];
                $targetClasses = [];

                foreach ($similarMappings as $simMapping) {
                    $sourceClasses[] = [
                        'id' => $simMapping->from_class_id,
                        'name' => $simMapping->fromClass->name,
                        'grade_level' => $simMapping->fromClass->grade_level
                    ];
                    $targetClasses[] = [
                        'id' => $simMapping->to_class_id,
                        'name' => $simMapping->toClass->name,
                        'grade_level' => $simMapping->toClass->grade_level
                    ];
                    $processedFromClasses[] = $simMapping->from_class_id;
                }

                $batches[] = [
                    'batch_name' => "Promotion Batch - Order {$mapping->promotion_order}",
                    'description' => "Automatic promotion for classes in order {$mapping->promotion_order}",
                    'source_classes' => $sourceClasses,
                    'target_classes' => $targetClasses,
                    'promotion_order' => $mapping->promotion_order
                ];
            }
        }

        return $batches;
    }
}
