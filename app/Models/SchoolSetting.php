<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SchoolSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'key',
        'value',
        'type',
        'category',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    // Scopes
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Accessors & Mutators
    public function getValueAttribute($value)
    {
        // Auto-decode JSON values
        if ($this->type === 'json' && is_string($value)) {
            return json_decode($value, true);
        }

        // Cast to appropriate type
        return match ($this->type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'float' => (float) $value,
            default => $value
        };
    }

    public function setValueAttribute($value)
    {
        // Auto-encode arrays/objects to JSON
        if ($this->type === 'json' && (is_array($value) || is_object($value))) {
            $this->attributes['value'] = json_encode($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }

    // Helper methods
    public static function getSetting($schoolId, $key, $default = null)
    {
        $setting = self::where('school_id', $schoolId)
            ->where('key', $key)
            ->where('is_active', true)
            ->first();

        return $setting ? $setting->value : $default;
    }

    public static function setSetting($schoolId, $key, $value, $type = 'string', $category = 'general', $description = null)
    {
        return self::updateOrCreate(
            ['school_id' => $schoolId, 'key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'category' => $category,
                'description' => $description,
                'is_active' => true
            ]
        );
    }

    public static function getSettingsByCategory($schoolId, $category)
    {
        return self::where('school_id', $schoolId)
            ->where('category', $category)
            ->where('is_active', true)
            ->pluck('value', 'key');
    }
}
