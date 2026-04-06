<?php

namespace App\Services\Conversation\Handlers;

use App\Models\ChatSession;
use App\Services\AI\AiService;
use App\Services\Menu\MenuService;
use App\Services\Menu\MenuTokenService;
use App\Services\WhatsApp\MessageFactory;
use App\Services\Conversation\Handlers\GreetingHandler;

class MenuBrowsingHandler implements HandlerInterface
{
    public function handle(ChatSession $session, string $message, string $messageType): array
    {
        $menuService = app(MenuService::class);
        $message = trim($message);
        $lower = mb_strtolower($message);

        // Greeting keywords → restart welcome flow
        $greetingWords = ['hola', 'hi', 'hello', 'buenas', 'buenos dias', 'buenas tardes', 'buenas noches', 'hey', 'ola', 'inicio', 'start', 'comenzar', 'reiniciar'];
        if (in_array($lower, $greetingWords) || preg_match('/^(hola|buenas|buenos)\b/i', $lower)) {
            return (new GreetingHandler())->handle($session, $message, $messageType);
        }

        // Handle button replies from greeting
        if (in_array($lower, ['opt_menu', 'opt_order', 'ver el menu', 'hacer un pedido', 'hacer mi pedido', 'pedir'])) {
            $tenant = app('tenant');
            return $this->showWebMenuLink($session, $tenant);
        }

        // Handle "Llamar al local" button from greeting
        if ($lower === 'opt_call') {
            return $this->handleCallRestaurant();
        }

        if (in_array($lower, ['opt_status', 'estado de pedido'])) {
            return [
                'response' => 'No tienes pedidos activos ahora mismo. ¿Quieres ver el menú?',
                'response_type' => 'buttons',
                'buttons' => [
                    ['id' => 'opt_order', 'title' => 'Hacer mi pedido'],
                ],
            ];
        }

        // Handle list reply - category selection by ID (e.g. "cat_5", "cat_70", "cat_-123456")
        if (preg_match('/^cat_(-?\d+)$/', $lower, $matches)) {
            $category = $menuService->findCategoryById($matches[1]);
            if ($category) {
                return $this->showCategoryItems($category, $session, $menuService);
            }
        }

        // Try to find a category by name or index
        $category = $menuService->findCategoryByNameOrIndex($message);

        // AI fallback: ask AI to match the category when rule-based fails
        if (!$category) {
            $ai = app(AiService::class);
            if ($ai->isAvailable()) {
                $categories = $menuService->getCategories();
                $names      = array_column($categories, 'name');
                $aiName     = $ai->matchMenuCategory($message, $names);
                if ($aiName) {
                    $category = $menuService->findCategoryByNameOrIndex($aiName);
                }
            }
        }

        if ($category) {
            return $this->showCategoryItems($category, $session, $menuService);
        }

        $tenant = app('tenant');
        return $this->showWebMenuLink($session, $tenant);
    }

    private function handleCallRestaurant(): array
    {
        $tenant = app('tenant');
        $restaurantPhone = $tenant->getSetting('restaurant_phone');

        if (!$restaurantPhone) {
            return [
                'response' => 'Por el momento no tenemos un número disponible, pero puedes escribirnos aquí mismo y te atendemos enseguida. 😊',
                'response_type' => 'text',
            ];
        }

        $cleanPhone = preg_replace('/[^0-9+]/', '', $restaurantPhone);
        if (!str_starts_with($cleanPhone, '+')) {
            $cleanPhone = '+' . $cleanPhone;
        }

        return [
            'response' => '📞 Aquí tienes nuestro número directo:',
            'response_type' => 'cta_url',
            'cta_body' => '📞 Nuestro equipo está listo para atenderte. ¡Llámanos cuando quieras!',
            'cta_button_text' => 'Llamar ahora',
            'cta_url' => "tel:{$cleanPhone}",
        ];
    }

    private function showCategoriesList(MenuService $menuService, ChatSession $session): array
    {
        $categories = $menuService->getCategories();

        if (empty($categories)) {
            return [
                'response' => 'Disculpa, el menú no está disponible en este momento. Intenta de nuevo en unos minutos. 🙏',
                'response_type' => 'text',
            ];
        }

        // Build interactive list sections
        $rows = [];
        foreach ($categories as $cat) {
            $rows[] = [
                'id' => 'cat_' . $cat['id'],
                'title' => substr($cat['name'], 0, 24),
                'description' => isset($cat['description']) ? substr($cat['description'], 0, 72) : '',
            ];
        }

        $sections = [
            [
                'title' => 'Categorías',
                'rows' => $rows,
            ],
        ];

        return [
            'response' => '🍽️ Aquí está nuestro menú. Elige una categoría para ver los productos:',
            'response_type' => 'list',
            'list_button_text' => 'Ver categorías',
            'list_sections' => $sections,
            'context_data' => array_merge($session->context_data ?? [], ['retry_count' => 0]),
        ];
    }

    private function showWebMenuLink(ChatSession $session, $tenant): array
    {
        $tokenService = app(MenuTokenService::class);
        $token = $tokenService->generateMenuToken(
            $tenant->id,
            $session->id,
            $session->customer_phone,
        );
        $menuUrl = $tokenService->buildMenuUrl($token);

        return [
            'response' => '🍽️ Explora nuestro menú y agrega lo que más te guste:',
            'response_type' => 'cta_url',
            'cta_body' => '🍽️ Explora nuestro menú y agrega lo que más te guste:',
            'cta_button_text' => 'Ver menú',
            'cta_url' => $menuUrl,
            'next_state' => 'cart_review',
            'context_data' => array_merge($session->context_data ?? [], ['retry_count' => 0, 'web_menu_token' => $token]),
        ];
    }

    private function showCategoryItems(array $category, ChatSession $session, MenuService $menuService): array
    {
        $items = $category['items'] ?? [];

        // Filter out unavailable items (relevant for external menu sources)
        $items = array_values(array_filter($items, fn ($item) => ($item['is_available'] ?? true)));

        if (empty($items)) {
            $result = $this->showCategoriesList($menuService, $session);
            $result['response'] = "La categoria {$category['name']} no tiene items disponibles.\n\n" .
                'Selecciona otra categoria:';
            return $result;
        }

        // Build interactive list rows — all items in a single list message (no separate messages per item)
        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'id' => 'item_' . $item['id'],
                'title' => mb_substr($item['name'], 0, 24),
                'description' => mb_substr($this->itemListDescription($item), 0, 72),
            ];
        }

        // WhatsApp list sections support up to 10 rows each; split into multiple sections if needed
        $sections = [];
        foreach (array_chunk($rows, 10) as $i => $chunk) {
            $sections[] = [
                'title' => $i === 0 ? $category['name'] : $category['name'] . ' (' . ($i + 1) . ')',
                'rows' => $chunk,
            ];
        }

        return [
            'response' => "Productos de *{$category['name']}*:",
            'response_type' => 'list',
            'list_button_text' => 'Ver productos',
            'list_sections' => $sections,
            'next_state' => 'item_selection',
            'context_data' => array_merge($session->context_data ?? [], [
                'last_viewed_category' => $category['id'],
                'current_category_items' => $items,
                'retry_count' => 0,
            ]),
        ];
    }

    private function itemListDescription(array $item): string
    {
        $tenant = app('tenant');
        $modifiers = $item['modifiers'] ?? [];

        if (!empty($modifiers['variant_groups'])) {
            $prices = [];
            foreach ($modifiers['variant_groups'] as $group) {
                foreach ($group['options'] ?? [] as $opt) {
                    $p = (float) ($opt['price'] ?? 0);
                    $prices[] = $tenant ? $tenant->applyTax($p) : $p;
                }
            }
            if ($prices) {
                $priceText = 'Desde RD$' . number_format(min($prices), 0, '.', ',');
            }
        }

        if (!isset($priceText)) {
            $p = (float) ($item['price'] ?? 0);
            $priceText = 'RD$' . number_format($tenant ? $tenant->applyTax($p) : $p, 0, '.', ',');
        }

        if (!empty($item['description'])) {
            $desc = mb_strlen($item['description']) > 45
                ? mb_substr($item['description'], 0, 42) . '...'
                : $item['description'];
            return "{$priceText} - {$desc}";
        }

        return $priceText;
    }
}
