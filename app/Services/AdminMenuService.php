<?php

namespace App\Services;

use App\Models\AdminMenu;

class AdminMenuService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = AdminMenu::class;
    }

    /**
     * Get complete menu hierarchy for admin dashboard
     */
    public function getMenuHierarchy()
    {
        return AdminMenu::getMenuHierarchy();
    }

    /**
     * Get flat list of all menus
     */
    public function getAllMenus()
    {
        return AdminMenu::active()
            ->orderBy('sort_order')
            ->get()
            ->map(function ($menu) {
                return AdminMenu::formatMenuForFrontend($menu);
            });
    }

    /**
     * Get menus by type
     */
    public function getMenusByType(string $type)
    {
        return AdminMenu::active()
            ->byType($type)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($menu) {
                return AdminMenu::formatMenuForFrontend($menu);
            });
    }

    /**
     * Get root level groups
     */
    public function getRootGroups()
    {
        return AdminMenu::active()
            ->groups()
            ->rootMenus()
            ->get()
            ->map(function ($menu) {
                return AdminMenu::formatMenuForFrontend($menu);
            });
    }

    /**
     * Get children of a specific menu
     */
    public function getMenuChildren(string $menuId)
    {
        $menu = AdminMenu::active()->find($menuId);
        
        if (!$menu) {
            return collect();
        }

        return $menu->children()
            ->active()
            ->get()
            ->map(function ($child) {
                return AdminMenu::formatMenuForFrontend($child);
            });
    }

    /**
     * Create a new menu item
     */
    public function createMenu(array $data)
    {
        // Auto-generate sort order if not provided
        if (!isset($data['sort_order'])) {
            $maxOrder = AdminMenu::where('parent_id', $data['parent_id'] ?? null)
                ->max('sort_order');
            $data['sort_order'] = ($maxOrder ?? 0) + 1;
        }

        return AdminMenu::create($data);
    }

    /**
     * Update menu item
     */
    public function updateMenu(string $id, array $data)
    {
        $menu = AdminMenu::findOrFail($id);
        $menu->update($data);
        return $menu;
    }

    /**
     * Delete menu item and all children
     */
    public function deleteMenu(string $id)
    {
        $menu = AdminMenu::findOrFail($id);
        
        // Delete all children recursively
        $this->deleteMenuWithChildren($menu);
        
        return true;
    }

    /**
     * Recursively delete menu and its children
     */
    private function deleteMenuWithChildren(AdminMenu $menu)
    {
        // First delete all children
        foreach ($menu->children as $child) {
            $this->deleteMenuWithChildren($child);
        }
        
        // Then delete the menu itself
        $menu->delete();
    }

    /**
     * Reorder menus
     */
    public function reorderMenus(array $menuOrders)
    {
        foreach ($menuOrders as $order) {
            AdminMenu::where('id', $order['id'])
                ->update(['sort_order' => $order['sort_order']]);
        }
        
        return true;
    }

    /**
     * Toggle menu active status
     */
    public function toggleMenuStatus(string $id)
    {
        $menu = AdminMenu::findOrFail($id);
        $menu->is_active = !$menu->is_active;
        $menu->save();
        
        return $menu;
    }

    /**
     * Get menu breadcrumb path
     */
    public function getMenuBreadcrumb(string $menuId)
    {
        $menu = AdminMenu::find($menuId);
        if (!$menu) {
            return [];
        }

        $breadcrumb = [];
        $current = $menu;
        
        while ($current) {
            array_unshift($breadcrumb, [
                'id' => $current->id,
                'name' => $current->name,
                'code' => $current->code,
                'path' => $current->path
            ]);
            $current = $current->parent;
        }
        
        return $breadcrumb;
    }

    /**
     * Search menus by name or code
     */
    public function searchMenus(string $query)
    {
        return AdminMenu::active()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('code', 'like', "%{$query}%");
            })
            ->orderBy('sort_order')
            ->get()
            ->map(function ($menu) {
                return AdminMenu::formatMenuForFrontend($menu);
            });
    }
}
