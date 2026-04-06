<?php

namespace App\Services\Menu;

use App\Models\MenuCategory;
use App\Models\MenuItem;

class InternalMenuSource implements MenuSourceInterface
{
    public function getMenu(): array
    {
        $categories = MenuCategory::active()
            ->ordered()
            ->with(['items' => fn ($q) => $q->active()->available()->orderBy('sort_order')])
            ->get();

        return [
            'categories' => $categories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description,
                'items' => $cat->items->map(fn ($item) => $this->normalizeItem($item))->values()->all(),
            ])->values()->all(),
        ];
    }

    public function searchItems(string $query): array
    {
        $query = mb_strtolower(trim($query));

        $items = MenuItem::active()
            ->available()
            ->where(function ($q) use ($query) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$query}%"])
                  ->orWhereRaw('LOWER(description) LIKE ?', ["%{$query}%"]);
            })
            ->with('category')
            ->limit(10)
            ->get();

        return $items->map(fn ($item) => $this->normalizeItem($item))->all();
    }

    public function getItem(string|int $id): ?array
    {
        $item = MenuItem::active()->find($id);

        return $item ? $this->normalizeItem($item) : null;
    }

    private function normalizeItem(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'price' => (float) $item->price,
            'is_available' => $item->is_available,
            'modifiers' => $item->modifiers ?? [],
            'image_url' => $item->image_url,
            'category_name' => $item->category?->name,
        ];
    }
}
