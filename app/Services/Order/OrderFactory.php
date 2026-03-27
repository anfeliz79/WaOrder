<?php

namespace App\Services\Order;

use App\Models\Branch;
use App\Models\ChatSession;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Tenant;
use App\Services\Branch\BranchRouter;
use Illuminate\Support\Facades\DB;

class OrderFactory
{
    public function __construct(
        private OrderNumberGenerator $numberGenerator,
    ) {}

    public function createFromSession(ChatSession $session, Tenant $tenant): Order
    {
        return DB::transaction(function () use ($session, $tenant) {
            $cartData = $session->cart_data;
            $info = $session->collected_info;

            // Resolve branch
            $branchId = $info['branch_id'] ?? null;
            if (!$branchId) {
                $branchId = app(BranchRouter::class)->getDefaultBranch($tenant->id)?->id;
            }
            $branch = $branchId ? Branch::find($branchId) : null;

            $deliveryFee = 0;
            if (($info['delivery_type'] ?? 'delivery') === 'delivery') {
                // Branch-specific delivery fee with tenant fallback
                $deliveryFee = (float) ($branch?->getSetting('delivery_fee')
                    ?? $tenant->getSetting('delivery_fee', 0));
            }

            $subtotal = (float) ($cartData['subtotal'] ?? 0);
            $taxAmount = $tenant->extractTax($subtotal);
            $total = $subtotal + $deliveryFee;

            $order = Order::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branchId,
                'customer_id' => $session->customer_id,
                'order_number' => $this->numberGenerator->generate($tenant->id),
                'status' => 'confirmed',
                'delivery_type' => $info['delivery_type'] ?? 'delivery',
                'delivery_address' => $info['address'] ?? null,
                'delivery_latitude' => $info['latitude'] ?? null,
                'delivery_longitude' => $info['longitude'] ?? null,
                'customer_name' => $info['name'] ?? 'Cliente',
                'customer_phone' => $session->customer_phone,
                'payment_method' => $info['payment_method'] ?? 'cash',
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'tax' => $taxAmount,
                'total' => $total,
                'notes' => $info['notes'] ?? null,
                'confirmed_at' => now(),
                'estimated_ready_at' => now()->addMinutes(
                    (int) ($tenant->getSetting('estimated_time', 30))
                ),
            ]);

            // Create order items
            foreach ($cartData['items'] ?? [] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => isset($item['menu_item_id']) && MenuItem::where('id', $item['menu_item_id'])->exists()
                        ? $item['menu_item_id']
                        : null,
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'modifiers' => $item['modifiers'] ?? null,
                    'subtotal' => $item['subtotal'],
                ]);
            }

            // Log initial status
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => 'confirmed',
                'changed_by_type' => 'system',
                'note' => 'Orden creada desde WhatsApp',
            ]);

            // Update customer stats
            if ($session->customer) {
                $session->customer->incrementOrderStats($total);
                if ($info['name'] ?? null) {
                    $session->customer->update(['name' => $info['name']]);
                }
                if ($info['address'] ?? null) {
                    $session->customer->update(['default_address' => $info['address']]);
                }
            }

            return $order;
        });
    }
}
