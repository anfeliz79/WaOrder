<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Services\Menu\MenuService;
use Inertia\Inertia;

class MenuPageController extends Controller
{
    public function index()
    {
        $tenant = app('tenant');
        $menuSource = $tenant?->getMenuSource() ?? 'internal';

        if ($menuSource === 'external') {
            $menuService = app(MenuService::class);
            $menu = $menuService->getMenu();
            $categories = $menu['categories'] ?? [];
        } else {
            $categories = MenuCategory::active()
                ->ordered()
                ->with(['items' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                ->get();
        }

        return Inertia::render('Menu/Index', [
            'categories' => $categories,
            'menuSource' => $menuSource,
        ]);
    }
}
