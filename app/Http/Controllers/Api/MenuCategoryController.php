<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use Illuminate\Http\Request;

class MenuCategoryController extends Controller
{
    public function index()
    {
        return response()->json(
            MenuCategory::active()->ordered()->with('activeItems')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $maxOrder = MenuCategory::max('sort_order') ?? 0;
        $data['sort_order'] = $maxOrder + 1;

        $category = MenuCategory::create($data);

        return back()->with('success', 'Categoria creada');
    }

    public function update(Request $request, MenuCategory $category)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update($data);

        return response()->json($category);
    }

    public function destroy(MenuCategory $category)
    {
        $category->update(['is_active' => false]);

        return response()->json(['success' => true]);
    }
}
