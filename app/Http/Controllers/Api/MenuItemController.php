<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    public function index(Request $request)
    {
        $query = MenuItem::active()->with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->boolean('available')) {
            $query->available();
        }

        return response()->json($query->orderBy('sort_order')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:menu_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'image_url' => 'nullable|url|max:500',
            'modifiers' => 'nullable|array',
            'modifiers.variant_groups' => 'nullable|array',
            'modifiers.variant_groups.*.id' => 'required|string',
            'modifiers.variant_groups.*.name' => 'required|string|max:100',
            'modifiers.variant_groups.*.required' => 'boolean',
            'modifiers.variant_groups.*.options' => 'required|array|min:2',
            'modifiers.variant_groups.*.options.*.id' => 'required|string',
            'modifiers.variant_groups.*.options.*.name' => 'required|string|max:100',
            'modifiers.variant_groups.*.options.*.price' => 'required|numeric|min:0',
            'modifiers.optional_groups' => 'nullable|array',
            'modifiers.optional_groups.*.id' => 'required|string',
            'modifiers.optional_groups.*.name' => 'required|string|max:100',
            'modifiers.optional_groups.*.min' => 'integer|min:0',
            'modifiers.optional_groups.*.max' => 'integer|min:0',
            'modifiers.optional_groups.*.options' => 'required|array|min:1',
            'modifiers.optional_groups.*.options.*.id' => 'required|string',
            'modifiers.optional_groups.*.options.*.name' => 'required|string|max:100',
            'modifiers.optional_groups.*.options.*.price' => 'required|numeric|min:0',
        ]);

        $maxOrder = MenuItem::where('category_id', $data['category_id'])->max('sort_order') ?? 0;
        $data['sort_order'] = $maxOrder + 1;

        MenuItem::create($data);

        return back()->with('success', 'Item creado');
    }

    public function update(Request $request, MenuItem $item)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'image_url' => 'nullable|url|max:500',
            'modifiers' => 'nullable|array',
            'modifiers.variant_groups' => 'nullable|array',
            'modifiers.variant_groups.*.id' => 'required|string',
            'modifiers.variant_groups.*.name' => 'required|string|max:100',
            'modifiers.variant_groups.*.required' => 'boolean',
            'modifiers.variant_groups.*.options' => 'required|array|min:2',
            'modifiers.variant_groups.*.options.*.id' => 'required|string',
            'modifiers.variant_groups.*.options.*.name' => 'required|string|max:100',
            'modifiers.variant_groups.*.options.*.price' => 'required|numeric|min:0',
            'modifiers.optional_groups' => 'nullable|array',
            'modifiers.optional_groups.*.id' => 'required|string',
            'modifiers.optional_groups.*.name' => 'required|string|max:100',
            'modifiers.optional_groups.*.min' => 'integer|min:0',
            'modifiers.optional_groups.*.max' => 'integer|min:0',
            'modifiers.optional_groups.*.options' => 'required|array|min:1',
            'modifiers.optional_groups.*.options.*.id' => 'required|string',
            'modifiers.optional_groups.*.options.*.name' => 'required|string|max:100',
            'modifiers.optional_groups.*.options.*.price' => 'required|numeric|min:0',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $item->update($data);

        return back()->with('success', 'Item actualizado');
    }

    public function toggleAvailability(Request $request, MenuItem $item)
    {
        $data = $request->validate([
            'is_available' => 'required|boolean',
        ]);

        $item->update($data);

        return back()->with('success', 'Disponibilidad actualizada');
    }

    public function destroy(MenuItem $item)
    {
        $item->update(['is_active' => false]);

        return response()->json(['success' => true]);
    }
}
