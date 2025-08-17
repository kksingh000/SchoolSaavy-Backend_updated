<?php

namespace App\Http\Controllers;

use App\Services\AdminMenuService;
use Illuminate\Http\Request;

class AdminMenuController extends BaseController
{
    public function __construct(
        private AdminMenuService $adminMenuService
    ) {}

    /**
     * Get complete menu hierarchy for admin dashboard
     */
    public function index()
    {
        try {
            $menus = $this->adminMenuService->getMenuHierarchy();

            return $this->successResponse($menus, 'Admin menus retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get flat list of all menus
     */
    public function getAllFlat()
    {
        try {
            $menus = $this->adminMenuService->getAllMenus();

            return $this->successResponse($menus, 'All menus retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get menus by type (GROUP, MENU, CATALOGUE)
     */
    public function getByType($type)
    {
        try {
            $menus = $this->adminMenuService->getMenusByType(strtoupper($type));

            return $this->successResponse($menus, "Menus of type {$type} retrieved successfully");
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get root level groups
     */
    public function getRootGroups()
    {
        try {
            $groups = $this->adminMenuService->getRootGroups();

            return $this->successResponse($groups, 'Root groups retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get children of a specific menu
     */
    public function getChildren($menuId)
    {
        try {
            $children = $this->adminMenuService->getMenuChildren($menuId);

            return $this->successResponse($children, 'Menu children retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get specific menu details
     */
    public function show($id)
    {
        try {
            $menu = $this->adminMenuService->find($id);

            if (!$menu) {
                return $this->errorResponse('Menu not found', null, 404);
            }

            return $this->successResponse($menu, 'Menu retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Create a new menu item
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|string|unique:admin_menus,id',
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:255|unique:admin_menus,code',
                'parent_id' => 'nullable|string|exists:admin_menus,id',
                'type' => 'required|in:GROUP,MENU,CATALOGUE',
                'icon' => 'nullable|string|max:255',
                'path' => 'nullable|string|max:255',
                'component' => 'nullable|string|max:255',
                'meta' => 'nullable|array',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean'
            ]);

            $menu = $this->adminMenuService->createMenu($validated);

            return $this->successResponse($menu, 'Menu created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Update a menu item
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'code' => 'sometimes|string|max:255|unique:admin_menus,code,' . $id,
                'parent_id' => 'nullable|string|exists:admin_menus,id',
                'type' => 'sometimes|in:GROUP,MENU,CATALOGUE',
                'icon' => 'nullable|string|max:255',
                'path' => 'nullable|string|max:255',
                'component' => 'nullable|string|max:255',
                'meta' => 'nullable|array',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean'
            ]);

            $menu = $this->adminMenuService->updateMenu($id, $validated);

            return $this->successResponse($menu, 'Menu updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Delete a menu item and all its children
     */
    public function destroy($id)
    {
        try {
            $this->adminMenuService->deleteMenu($id);

            return $this->successResponse(null, 'Menu deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Reorder menus
     */
    public function reorder(Request $request)
    {
        try {
            $validated = $request->validate([
                'menu_orders' => 'required|array',
                'menu_orders.*.id' => 'required|string|exists:admin_menus,id',
                'menu_orders.*.sort_order' => 'required|integer|min:0'
            ]);

            $this->adminMenuService->reorderMenus($validated['menu_orders']);

            return $this->successResponse(null, 'Menus reordered successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Toggle menu active status
     */
    public function toggleStatus($id)
    {
        try {
            $menu = $this->adminMenuService->toggleMenuStatus($id);

            return $this->successResponse($menu, 'Menu status updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get menu breadcrumb path
     */
    public function getBreadcrumb($menuId)
    {
        try {
            $breadcrumb = $this->adminMenuService->getMenuBreadcrumb($menuId);

            return $this->successResponse($breadcrumb, 'Menu breadcrumb retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Search menus
     */
    public function search(Request $request)
    {
        try {
            $validated = $request->validate([
                'q' => 'required|string|min:2|max:100'
            ]);

            $menus = $this->adminMenuService->searchMenus($validated['q']);

            return $this->successResponse($menus, 'Menu search completed successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
