<?php

namespace App\Services\Menu;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class MenuService
{
    private MenuSourceInterface $source;
    private Tenant $tenant;

    public function __construct()
    {
        $this->tenant = app('tenant');
        $this->source = $this->resolveSource();
    }

    public function getMenu(): array
    {
        if ($this->tenant->getMenuSource() === 'internal') {
            $cacheKey = "menu:internal:{$this->tenant->id}";
            return Cache::remember($cacheKey, 600, fn () => $this->source->getMenu());
        }

        return $this->source->getMenu();
    }

    public function getCategories(): array
    {
        return $this->getMenu()['categories'] ?? [];
    }

    public function getCategoryItems(string|int $categoryId): array
    {
        $categories = $this->getCategories();

        foreach ($categories as $cat) {
            if ((string) $cat['id'] === (string) $categoryId) {
                return $cat['items'] ?? [];
            }
        }

        return [];
    }

    public function findCategoryById(string|int $id): ?array
    {
        $categories = $this->getCategories();

        foreach ($categories as $cat) {
            if ((string) $cat['id'] === (string) $id) {
                return $cat;
            }
        }

        return null;
    }

    public function findCategoryByNameOrIndex(string $input): ?array
    {
        $categories = $this->getCategories();
        $input = mb_strtolower(trim($input));

        // Try numeric index
        if (is_numeric($input)) {
            $index = (int) $input - 1;
            return $categories[$index] ?? null;
        }

        // Try name match
        foreach ($categories as $cat) {
            if (mb_strtolower($cat['name']) === $input) {
                return $cat;
            }
            // Partial match
            if (str_contains(mb_strtolower($cat['name']), $input)) {
                return $cat;
            }
        }

        return null;
    }

    public function findItemByNameOrIndex(string $input, ?array $categoryItems = null): ?array
    {
        $input = mb_strtolower(trim($input));

        if ($categoryItems) {
            // Try index first
            if (is_numeric($input)) {
                $index = (int) $input - 1;
                return $categoryItems[$index] ?? null;
            }

            // Try name match within category items
            foreach ($categoryItems as $item) {
                $name = mb_strtolower($item['name']);
                if ($name === $input || str_contains($name, $input)) {
                    return $item;
                }
            }
        }

        // Search across all items
        return $this->source->searchItems($input)[0] ?? null;
    }

    public function searchItems(string $query): array
    {
        return $this->source->searchItems($query);
    }

    public function getItem(string|int $id): ?array
    {
        return $this->source->getItem($id);
    }

    public function findItemById(string|int $id): ?array
    {
        // Try direct source lookup first
        $item = $this->source->getItem($id);
        if ($item) return $item;

        // Fallback: search through all categories
        foreach ($this->getCategories() as $cat) {
            foreach ($cat['items'] ?? [] as $item) {
                if ((string) ($item['id'] ?? '') === (string) $id) {
                    return $item;
                }
            }
        }

        return null;
    }

    public function invalidateCache(): void
    {
        Cache::forget("menu:internal:{$this->tenant->id}");
        Cache::forget("menu:external:{$this->tenant->id}");
    }

    private function resolveSource(): MenuSourceInterface
    {
        if ($this->tenant->getMenuSource() === 'external') {
            return new ExternalMenuSource($this->tenant);
        }

        return new InternalMenuSource();
    }
}
