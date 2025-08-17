<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminMenu extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'code',
        'parent_id',
        'type',
        'icon',
        'path',
        'component',
        'meta',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'meta' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    /**
     * Menu type constants
     */
    const TYPE_GROUP = 'GROUP';
    const TYPE_MENU = 'MENU';
    const TYPE_CATALOGUE = 'CATALOGUE';

    /**
     * Relationships
     */
    public function parent()
    {
        return $this->belongsTo(AdminMenu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(AdminMenu::class, 'parent_id')->orderBy('sort_order');
    }

    public function allChildren()
    {
        return $this->hasMany(AdminMenu::class, 'parent_id')->with('allChildren')->orderBy('sort_order');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRootMenus($query)
    {
        return $query->whereNull('parent_id')->orderBy('sort_order');
    }

    public function scopeGroups($query)
    {
        return $query->where('type', self::TYPE_GROUP);
    }

    public function scopeMenus($query)
    {
        return $query->where('type', self::TYPE_MENU);
    }

    public function scopeCatalogues($query)
    {
        return $query->where('type', self::TYPE_CATALOGUE);
    }

    /**
     * Helper methods
     */
    public function isGroup()
    {
        return $this->type === self::TYPE_GROUP;
    }

    public function isMenu()
    {
        return $this->type === self::TYPE_MENU;
    }

    public function isCatalogue()
    {
        return $this->type === self::TYPE_CATALOGUE;
    }

    public function hasChildren()
    {
        return $this->children()->count() > 0;
    }

    /**
     * Get menu hierarchy as nested array
     */
    public static function getMenuHierarchy()
    {
        return self::active()
            ->rootMenus()
            ->with('allChildren')
            ->get()
            ->map(function ($menu) {
                return self::formatMenuForFrontend($menu);
            });
    }

    /**
     * Format menu for frontend consumption
     */
    public static function formatMenuForFrontend($menu)
    {
        $formatted = [
            'id' => $menu->id,
            'name' => $menu->name,
            'code' => $menu->code,
            'type' => $menu->type,
            'icon' => $menu->icon,
            'path' => $menu->path,
            'component' => $menu->component,
            'meta' => $menu->meta,
            'sort_order' => $menu->sort_order
        ];

        if ($menu->parent_id) {
            $formatted['parentId'] = $menu->parent_id;
        }

        if ($menu->allChildren && $menu->allChildren->isNotEmpty()) {
            $formatted['children'] = $menu->allChildren->map(function ($child) {
                return self::formatMenuForFrontend($child);
            })->toArray();
        }

        return $formatted;
    }
}
