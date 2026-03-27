<?php

namespace App\Services\Menu;

interface MenuSourceInterface
{
    /**
     * Get all categories with their items in normalized format.
     * Returns: ['categories' => [['id' => ..., 'name' => ..., 'items' => [...]]]]
     */
    public function getMenu(): array;

    /**
     * Search items by keyword.
     */
    public function searchItems(string $query): array;

    /**
     * Get a specific item by ID.
     */
    public function getItem(string|int $id): ?array;
}
