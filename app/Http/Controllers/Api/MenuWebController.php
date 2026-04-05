<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppNotification;
use App\Models\ChatSession;
use App\Models\Tenant;
use App\Services\Conversation\Handlers\ModifierSelectionHandler;
use App\Services\Menu\MenuService;
use App\Services\Menu\MenuTokenService;
use App\Services\Session\SessionManager;
use App\Services\WhatsApp\MessageFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MenuWebController extends Controller
{
    public function __construct(
        private MenuTokenService $tokenService,
    ) {}

    /**
     * GET /api/public/menu/{token} - Get item data for web personalization page.
     */
    public function showItem(string $token): JsonResponse
    {
        $tokenData = $this->tokenService->validate($token);

        if (!$tokenData) {
            return response()->json(['error' => 'Token expirado o invalido. Vuelve a WhatsApp para generar un nuevo enlace.'], 404);
        }

        $this->tokenService->extend($token);

        $tenant = Tenant::find($tokenData['tenant_id']);
        if (!$tenant) {
            return response()->json(['error' => 'Negocio no encontrado.'], 404);
        }

        app()->instance('tenant', $tenant);

        // For item tokens, find the specific item
        if ($tokenData['type'] === 'item') {
            $menuService = app(MenuService::class);
            $item = $menuService->findItemById($tokenData['item_id']);
            if (!$item) {
                return response()->json(['error' => 'Producto no encontrado.'], 404);
            }

            return response()->json([
                'tenant' => [
                    'name' => $tenant->name,
                    'logo' => $tenant->logo_url ?? null,
                ],
                'item' => $item,
                'token_type' => 'item',
            ]);
        }

        return response()->json(['error' => 'Token invalido.'], 400);
    }

    /**
     * GET /api/public/menu/{token}/full - Get full menu for web browsing.
     */
    public function showFullMenu(string $token): JsonResponse
    {
        $tokenData = $this->tokenService->validate($token);

        if (!$tokenData) {
            return response()->json(['error' => 'Token expirado o invalido.'], 404);
        }

        $this->tokenService->extend($token);

        $tenant = Tenant::find($tokenData['tenant_id']);
        if (!$tenant) {
            return response()->json(['error' => 'Negocio no encontrado.'], 404);
        }

        app()->instance('tenant', $tenant);

        $menuService = app(MenuService::class);
        $categories = $menuService->getCategories();

        $theme = $tenant->getSetting('menu_theme', []);

        $restaurantPhone = $tenant->getSetting('restaurant_phone', '');
        $cleanPhone = preg_replace('/[^0-9]/', '', $restaurantPhone);

        $customerPhone = preg_replace('/[^0-9]/', '', $tokenData['phone'] ?? '');

        return response()->json([
            'tenant' => [
                'name' => $tenant->name,
                'whatsapp_phone' => $cleanPhone,
            ],
            'customer_phone' => $customerPhone,
            'theme' => [
                'primary_color' => $theme['primary_color'] ?? '#0052FF',
                'logo_url' => $theme['logo_url'] ?? null,
                'show_restaurant_name' => $theme['show_restaurant_name'] ?? true,
            ],
            'categories' => $categories,
            'token_type' => 'menu',
        ]);
    }

    /**
     * POST /api/public/menu/{token}/add - Add item to cart from web page.
     */
    public function addToCart(string $token, Request $request): JsonResponse
    {
        $tokenData = $this->tokenService->validate($token);

        if (!$tokenData) {
            return response()->json(['error' => 'Tu sesion ha expirado. Vuelve a WhatsApp para continuar.'], 404);
        }

        $validated = $request->validate([
            'item_id' => 'required',
            'quantity' => 'required|integer|min:1|max:20',
            'variants' => 'nullable|array',
            'variants.*.group_name' => 'required|string',
            'variants.*.option_name' => 'required|string',
            'variants.*.price' => 'required|numeric|min:0',
            'optionals' => 'nullable|array',
            'optionals.*.name' => 'required|string',
            'optionals.*.price' => 'required|numeric|min:0',
            'optionals.*.group' => 'required|string',
        ]);

        $tenant = Tenant::find($tokenData['tenant_id']);
        if (!$tenant) {
            return response()->json(['error' => 'Negocio no encontrado.'], 404);
        }

        app()->instance('tenant', $tenant);

        $session = ChatSession::where('id', $tokenData['session_id'])
            ->where('status', 'active')
            ->first();

        if (!$session) {
            return response()->json(['error' => 'Tu sesion de WhatsApp ha expirado. Envia un mensaje para iniciar de nuevo.'], 404);
        }

        // Find the menu item to validate
        $menuService = app(MenuService::class);
        $item = $menuService->findItemById($validated['item_id']);
        if (!$item) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        // Build modifier structure matching the existing cart format
        $selectedModifiers = [
            'variants' => [],
            'optionals' => [],
        ];

        foreach ($validated['variants'] ?? [] as $variant) {
            $selectedModifiers['variants'][$variant['group_name']] = [
                'name' => $variant['option_name'],
                'price' => (float) $variant['price'],
            ];
        }

        foreach ($validated['optionals'] ?? [] as $optional) {
            $selectedModifiers['optionals'][] = [
                'name' => $optional['name'],
                'price' => (float) $optional['price'],
                'group' => $optional['group'],
            ];
        }

        // Calculate unit price using existing logic
        $pendingItem = [
            'base_price' => $item['price'],
            'price' => $item['price'],
        ];
        $unitPrice = ModifierSelectionHandler::calculateUnitPrice($pendingItem, $selectedModifiers);
        $quantity = $validated['quantity'];

        // Update the session cart
        $cart = $session->cart_data ?? ['items' => [], 'subtotal' => 0, 'delivery_fee' => 0, 'total' => 0];

        // Check for duplicate (same item + same modifiers)
        $modifiersJson = json_encode($selectedModifiers);
        $found = false;
        foreach ($cart['items'] as &$cartItem) {
            if ($cartItem['menu_item_id'] == $item['id']
                && json_encode($cartItem['modifiers'] ?? []) === $modifiersJson) {
                $cartItem['quantity'] += $quantity;
                $cartItem['subtotal'] = $cartItem['quantity'] * $cartItem['unit_price'];
                $found = true;
                break;
            }
        }
        unset($cartItem);

        if (!$found) {
            $cart['items'][] = [
                'menu_item_id' => $item['id'],
                'name' => $item['name'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'modifiers' => $selectedModifiers,
                'subtotal' => $quantity * $unitPrice,
            ];
        }

        // Recalculate totals
        $cart['subtotal'] = array_sum(array_column($cart['items'], 'subtotal'));
        $cart['total'] = $cart['subtotal'] + ($cart['delivery_fee'] ?? 0);

        // Update session
        $sessionManager = app(SessionManager::class);
        $sessionManager->update($session, [
            'cart_data' => $cart,
            'conversation_state' => 'cart_review',
            'context_data' => array_merge($session->context_data ?? [], ['retry_count' => 0]),
        ]);

        // Send cart summary to WhatsApp
        $modDesc = MessageFactory::modifierDescription($selectedModifiers);
        $price = number_format($unitPrice * $quantity, 0, '.', ',');

        $message = "Agregado desde el menu web:\n{$quantity}x {$item['name']}"
            . ($modDesc ? " ({$modDesc})" : '')
            . " - RD\${$price}\n\n"
            . MessageFactory::cartSummaryText($cart['items'], $cart['subtotal'])
            . "\n\nQue deseas hacer?";

        $buttons = [
            ['id' => 'cart_add', 'title' => 'Agregar mas'],
            ['id' => 'cart_checkout', 'title' => 'Hacer pedido'],
            ['id' => 'cart_remove', 'title' => 'Eliminar item'],
        ];

        SendWhatsAppNotification::dispatch(
            $tokenData['tenant_id'],
            $tokenData['phone'],
            $message,
            $buttons,
        );

        Log::info('Item added to cart from web menu', [
            'session_id' => $session->id,
            'item' => $item['name'],
            'quantity' => $quantity,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Producto agregado al carrito. Revisa tu WhatsApp para continuar.',
            'cart' => [
                'items_count' => count($cart['items']),
                'total' => $cart['total'],
            ],
        ]);
    }
}
