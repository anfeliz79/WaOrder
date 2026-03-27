<?php

namespace App\Services\Menu;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalMenuSource implements MenuSourceInterface
{
    private Tenant $tenant;
    private array $fieldMapping;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->fieldMapping = $tenant->getSetting('menu_api_field_mapping', []);
    }

    public function getMenu(): array
    {
        $cacheKey = "menu:external:{$this->tenant->id}";
        $ttl = ($this->tenant->getSetting('menu_sync_interval', 15)) * 60;

        return Cache::remember($cacheKey, $ttl, function () {
            return $this->fetchAndNormalize();
        });
    }

    public function searchItems(string $query): array
    {
        $menu = $this->getMenu();
        $query = mb_strtolower(trim($query));
        $results = [];

        foreach ($menu['categories'] as $category) {
            foreach ($category['items'] as $item) {
                if (
                    str_contains(mb_strtolower($item['name']), $query) ||
                    str_contains(mb_strtolower($item['description'] ?? ''), $query)
                ) {
                    $item['category_name'] = $category['name'];
                    $results[] = $item;
                }
            }
        }

        return array_slice($results, 0, 10);
    }

    public function getItem(string|int $id): ?array
    {
        $menu = $this->getMenu();

        foreach ($menu['categories'] as $category) {
            foreach ($category['items'] as $item) {
                if ((string) $item['id'] === (string) $id) {
                    $item['category_name'] = $category['name'];
                    return $item;
                }
            }
        }

        return null;
    }

    private function fetchAndNormalize(): array
    {
        $url = $this->tenant->getSetting('menu_api_url');
        $apiKey = $this->tenant->getSetting('menu_api_key');
        $apiSecret = $this->tenant->getSetting('menu_api_secret');
        $authMode = $this->tenant->getSetting('menu_api_auth_mode', 'bearer');

        if (!$url) {
            Log::error('External menu: No API URL configured', ['tenant_id' => $this->tenant->id]);
            return ['categories' => []];
        }

        try {
            $request = Http::timeout(10);

            if ($authMode === 'headers' && $apiKey && $apiSecret) {
                // SelfOrder-style header auth
                $request = $request->withHeaders([
                    'X-Api-Key' => $apiKey,
                    'X-Api-Secret' => $apiSecret,
                ]);
            } elseif ($apiKey) {
                // Bearer token auth (legacy)
                $request = $request->withToken($apiKey);
            }

            // Append status=active if not already in URL
            $separator = str_contains($url, '?') ? '&' : '?';
            $finalUrl = str_contains($url, 'status=') ? $url : $url . $separator . 'status=active';

            $response = $request->get($finalUrl);

            if (!$response->successful()) {
                Log::error('External menu: API error', [
                    'tenant_id' => $this->tenant->id,
                    'status' => $response->status(),
                ]);
                return ['categories' => []];
            }

            $data = $response->json();

            return $this->normalizeExternalData($data);
        } catch (\Exception $e) {
            Log::error('External menu: Exception', [
                'tenant_id' => $this->tenant->id,
                'error' => $e->getMessage(),
            ]);
            return ['categories' => []];
        }
    }

    private function normalizeExternalData(array $data): array
    {
        // Detect SelfOrder format: flat product list with category field
        if (isset($data['data']) && isset($data['count'])) {
            return $this->normalizeSelfOrderData($data);
        }

        // Legacy: grouped by categories
        return $this->normalizeLegacyData($data);
    }

    /**
     * Normalize SelfOrder API format (flat product list with category field).
     */
    private function normalizeSelfOrderData(array $data): array
    {
        $products = $data['data'] ?? [];
        $categoryMap = [];

        foreach ($products as $product) {
            // Skip hidden products
            if (!empty($product['is_hidden_in_menu'])) {
                continue;
            }

            // Skip inactive products
            if (isset($product['is_active']) && !$product['is_active']) {
                continue;
            }

            // Get primary category
            $categoryName = $product['category'] ?? 'Sin categoria';
            $categoryId = $product['category_ids'][0] ?? crc32($categoryName);
            $categoryOrder = $product['category_order'] ?? 999;

            if (!isset($categoryMap[$categoryId])) {
                $categoryMap[$categoryId] = [
                    'id' => $categoryId,
                    'name' => $categoryName,
                    'description' => '',
                    'sort_order' => $categoryOrder,
                    'items' => [],
                ];
            }

            // Build modifiers from variants, addons, and optionals
            $modifiers = $this->buildModifiersFromSelfOrder($product);

            // Use base_price, or min variant price
            $price = (float) ($product['base_price'] ?? $product['price'] ?? 0);

            $categoryMap[$categoryId]['items'][] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'description' => $product['description'] ?? '',
                'price' => $price,
                'is_available' => (bool) ($product['is_active'] ?? true),
                'modifiers' => $modifiers,
                'image_url' => $this->resolveImageUrl($product['image'] ?? null),
            ];
        }

        // Sort categories by sort_order
        $categories = array_values($categoryMap);
        usort($categories, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        // Remove sort_order from output
        $categories = array_map(function ($cat) {
            unset($cat['sort_order']);
            return $cat;
        }, $categories);

        return ['categories' => $categories];
    }

    /**
     * Build variant_groups and optional_groups from SelfOrder's variants/addons/optionals.
     */
    private function buildModifiersFromSelfOrder(array $product): array
    {
        $modifiers = [];

        // Variants → variant_groups (each variant is a size/flavor option with its own price)
        $variants = $product['variants'] ?? [];
        if (!empty($variants)) {
            $options = [];
            foreach ($variants as $v) {
                $options[] = [
                    'id' => 'v_' . ($v['id'] ?? uniqid()),
                    'name' => $v['name'],
                    'price' => (float) ($v['price'] ?? $v['base_price'] ?? 0),
                ];
            }
            $modifiers['variant_groups'] = [[
                'id' => 'vg_size',
                'name' => 'Elige tu opcion',
                'required' => true,
                'options' => $options,
            ]];
        }

        // Addons → variant_groups (addons in SelfOrder are like variants with prices)
        $addons = $product['addons'] ?? [];
        if (!empty($addons) && empty($variants)) {
            $options = [];
            foreach ($addons as $a) {
                $options[] = [
                    'id' => 'a_' . ($a['id'] ?? uniqid()),
                    'name' => $a['name'],
                    'price' => (float) ($a['price'] ?? $a['base_price'] ?? 0),
                ];
            }
            $modifiers['variant_groups'] = [[
                'id' => 'vg_addons',
                'name' => 'Elige tu opcion',
                'required' => true,
                'options' => $options,
            ]];
        }

        // Optionals → optional_groups (free add-ons like wasabi)
        $optionals = $product['optionals'] ?? [];
        if (!empty($optionals)) {
            $options = [];
            foreach ($optionals as $o) {
                $options[] = [
                    'id' => 'o_' . ($o['id'] ?? uniqid()),
                    'name' => $o['name'],
                    'price' => 0,
                ];
            }
            $modifiers['optional_groups'] = [[
                'id' => 'og_extras',
                'name' => 'Extras',
                'min' => 0,
                'max' => count($options),
                'options' => $options,
            ]];
        }

        return $modifiers;
    }

    /**
     * Resolve relative image URLs to absolute.
     */
    private function resolveImageUrl(?string $image): ?string
    {
        if (!$image) {
            return null;
        }

        // Already absolute URL
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        // Relative path - prepend API base URL
        $apiUrl = $this->tenant->getSetting('menu_api_url', '');
        $baseUrl = preg_replace('#/integrations/.*$#', '', $apiUrl);

        return $baseUrl ? rtrim($baseUrl, '/') . '/' . ltrim($image, '/') : null;
    }

    /**
     * Legacy normalization: grouped category structure.
     */
    private function normalizeLegacyData(array $data): array
    {
        $categories = $data['categories'] ?? $data['menu'] ?? $data['data'] ?? [];

        if (!is_array($categories)) {
            return ['categories' => []];
        }

        $normalized = [];
        foreach ($categories as $cat) {
            $catName = $this->mapField($cat, 'category_name', 'name');
            $items = $cat['items'] ?? $cat['products'] ?? $cat['productos'] ?? [];

            $normalizedItems = [];
            foreach ($items as $item) {
                $normalizedItems[] = [
                    'id' => $item['id'] ?? $item['sku'] ?? uniqid('ext_'),
                    'name' => $this->mapField($item, 'item_name', 'name'),
                    'description' => $this->mapField($item, 'item_description', 'description') ?? '',
                    'price' => (float) ($this->mapField($item, 'item_price', 'price') ?? 0),
                    'is_available' => (bool) ($this->mapField($item, 'item_available', 'is_available') ?? true),
                    'modifiers' => $item['modifiers'] ?? $item['modificadores'] ?? [],
                ];
            }

            $normalized[] = [
                'id' => $cat['id'] ?? uniqid('cat_'),
                'name' => $catName,
                'description' => $cat['description'] ?? $cat['descripcion'] ?? '',
                'items' => $normalizedItems,
            ];
        }

        return ['categories' => $normalized];
    }

    private function mapField(array $data, string $mappingKey, string $defaultKey): mixed
    {
        $field = $this->fieldMapping[$mappingKey] ?? $defaultKey;
        return $data[$field] ?? $data[$defaultKey] ?? null;
    }
}
