<?php

namespace App\Services\Order;

use App\Models\Order;

class OrderNumberGenerator
{
    public function generate(int $tenantId): string
    {
        $lastOrder = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('order_number', 'like', 'ORD-%')
            ->orderByRaw("CAST(SUBSTRING(order_number, 5) AS UNSIGNED) DESC")
            ->first();

        $nextNumber = 1;
        if ($lastOrder) {
            $currentNumber = (int) substr($lastOrder->order_number, 4);
            $nextNumber = $currentNumber + 1;
        }

        return 'ORD-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
