<?php

namespace App\Services\WhatsApp;

class MessageFactory
{
    public static function menuCategoriesText(array $categories): string
    {
        $text = "Nuestras categorias:\n";
        foreach ($categories as $i => $cat) {
            $text .= ($i + 1) . ". " . $cat['name'] . "\n";
        }
        $text .= "\nEscribe el numero o nombre de la categoria.";
        return $text;
    }

    public static function categoryItemsText(string $categoryName, array $items): string
    {
        $text = "{$categoryName} disponibles:\n";
        foreach ($items as $i => $item) {
            $priceText = self::itemPriceText($item);
            $desc = !empty($item['description']) ? " - {$item['description']}" : '';
            // Truncate description to keep messages manageable
            if (mb_strlen($desc) > 60) {
                $desc = mb_substr($desc, 0, 57) . '...';
            }
            $text .= ($i + 1) . ". {$item['name']} - {$priceText}{$desc}\n";
        }
        $text .= "\nEscribe el numero para agregar al pedido.";
        return $text;
    }

    private static function itemPriceText(array $item): string
    {
        $modifiers = $item['modifiers'] ?? [];
        if (!empty($modifiers['variant_groups'])) {
            $prices = [];
            foreach ($modifiers['variant_groups'] as $group) {
                foreach ($group['options'] ?? [] as $opt) {
                    $prices[] = self::priceWithTax((float) ($opt['price'] ?? 0));
                }
            }
            if ($prices) {
                $min = number_format(min($prices), 0, '.', ',');
                $max = number_format(max($prices), 0, '.', ',');
                return $min === $max ? "RD\${$min}" : "Desde RD\${$min}";
            }
        }
        return "RD\$" . number_format(self::priceWithTax($item['price']), 0, '.', ',');
    }

    private static function priceWithTax(float $price): float
    {
        $tenant = app('tenant');

        return $tenant ? $tenant->applyTax($price) : $price;
    }

    public static function itemImageCaption(array $item): string
    {
        $priceText = self::itemPriceText($item);
        $caption = "*{$item['name']}* - {$priceText}";
        if (!empty($item['description'])) {
            $desc = mb_strlen($item['description']) > 100
                ? mb_substr($item['description'], 0, 97) . '...'
                : $item['description'];
            $caption .= "\n{$desc}";
        }
        return $caption;
    }

    public static function cartSummaryText(array $cartItems, float $subtotal): string
    {
        $text = "Tu pedido actual:\n";
        foreach ($cartItems as $item) {
            $itemTotal = number_format($item['subtotal'], 0, '.', ',');
            $modDesc = self::modifierDescription($item['modifiers'] ?? []);
            $text .= "- {$item['quantity']}x {$item['name']}";
            if ($modDesc) {
                $text .= " ({$modDesc})";
            }
            $text .= ": RD\${$itemTotal}\n";
        }
        $text .= "\nSubtotal: RD\$" . number_format($subtotal, 0, '.', ',');
        return $text;
    }

    public static function modifierDescription(array $modifiers): string
    {
        $parts = [];
        foreach ($modifiers['variants'] ?? [] as $groupName => $selection) {
            $parts[] = $selection['name'];
        }
        foreach ($modifiers['optionals'] ?? [] as $opt) {
            $parts[] = $opt['name'];
        }
        return implode(', ', $parts);
    }

    public static function orderConfirmationText(array $orderData): string
    {
        $text = "Resumen de tu pedido:\n\n";
        foreach ($orderData['items'] as $item) {
            $itemTotal = number_format($item['subtotal'], 0, '.', ',');
            $modDesc = self::modifierDescription($item['modifiers'] ?? []);
            $text .= "- {$item['quantity']}x {$item['name']}";
            if ($modDesc) {
                $text .= " ({$modDesc})";
            }
            $text .= ": RD\${$itemTotal}\n";
        }

        $text .= "\nSubtotal: RD\$" . number_format($orderData['subtotal'], 0, '.', ',');

        if (($orderData['tax'] ?? 0) > 0) {
            $text .= "\nImpuestos incl.: RD\$" . number_format($orderData['tax'], 0, '.', ',');
        }

        if ($orderData['delivery_fee'] > 0) {
            $text .= "\nDelivery: RD\$" . number_format($orderData['delivery_fee'], 0, '.', ',');
        }

        $text .= "\nTotal: RD\$" . number_format($orderData['total'], 0, '.', ',');
        $text .= "\n\nNombre: {$orderData['name']}";
        $text .= "\nTipo: " . ($orderData['delivery_type'] === 'delivery' ? 'Delivery' : 'Pickup');

        if ($orderData['delivery_type'] === 'delivery' && !empty($orderData['address'])) {
            $text .= "\nDireccion: {$orderData['address']}";
        }

        $text .= "\nPago: " . self::paymentMethodLabel($orderData['payment_method']);

        return $text;
    }

    public static function paymentMethodLabel(string $key): string
    {
        $builtIn = [
            'cash' => 'Efectivo',
            'transfer' => 'Transferencia',
            'card_link' => 'Pago con link',
        ];

        if (isset($builtIn[$key])) {
            return $builtIn[$key];
        }

        $tenant = app('tenant');
        $name = $tenant->getSetting("payment.custom_methods.{$key}.name");

        return $name ?? ucfirst(str_replace('_', ' ', $key));
    }

    public static function variantGroupPrompt(string $itemName, array $group): string
    {
        $text = "Selecciona {$group['name']} para *{$itemName}*:\n";
        foreach ($group['options'] as $i => $opt) {
            $price = number_format(self::priceWithTax($opt['price']), 0, '.', ',');
            $text .= ($i + 1) . ". {$opt['name']} - RD\${$price}\n";
        }
        $text .= "\nEscribe el numero de tu eleccion.";
        return $text;
    }

    public static function optionalGroupPrompt(string $itemName, array $group): string
    {
        $text = "Deseas agregar {$group['name']} a *{$itemName}*?\n";
        foreach ($group['options'] as $i => $opt) {
            if ($opt['price'] > 0) {
                $priceLabel = '+RD$' . number_format(self::priceWithTax($opt['price']), 0, '.', ',');
            } else {
                $priceLabel = 'gratis';
            }
            $text .= ($i + 1) . ". {$opt['name']} ({$priceLabel})\n";
        }
        $text .= "\nEscribe los numeros separados por coma o 'no' para continuar.";
        return $text;
    }

    public static function modifierConfirmText(string $itemName, int $quantity, array $selectedModifiers, float $unitPrice): string
    {
        $parts = [];
        foreach ($selectedModifiers['variants'] ?? [] as $groupName => $sel) {
            $parts[] = $sel['name'];
        }
        foreach ($selectedModifiers['optionals'] ?? [] as $opt) {
            $parts[] = $opt['name'];
        }

        $desc = implode(' + ', $parts);
        $priceWithTax = self::priceWithTax($unitPrice);
        $unitFormatted = number_format($priceWithTax, 0, '.', ',');
        $totalFormatted = number_format($priceWithTax * $quantity, 0, '.', ',');

        $text = "*{$itemName}*";
        if ($desc) {
            $text .= " - {$desc}";
        }
        $text .= "\nPrecio: RD\${$unitFormatted}";
        if ($quantity > 1) {
            $text .= " x {$quantity} = RD\${$totalFormatted}";
        }
        $text .= "\n\nAgregar al carrito?";
        return $text;
    }

    public static function driverAssignmentBody(\App\Models\Order $order): string
    {
        $items = $order->items->map(function ($item) {
            $line = "  - {$item->quantity}x {$item->name}";
            $modDesc = $item->getModifiersSummary();
            if ($modDesc) {
                $line .= " ({$modDesc})";
            }
            return $line;
        })->join("\n");
        $total = number_format($order->total, 0, '.', ',');
        $paymentLabel = self::driverPaymentLabel($order->payment_method);

        $text = "\xF0\x9F\x9A\x80 *NUEVA ENTREGA ASIGNADA*\n\n"
            . "\xF0\x9F\x93\x8B Pedido *#{$order->order_number}*\n"
            . "{$items}\n\n"
            . "\xF0\x9F\x92\xB0 Total: RD\${$total}\n"
            . "\xF0\x9F\x92\xB3 Pago: {$paymentLabel}\n\n"
            . "\xF0\x9F\x91\xA4 Cliente: {$order->customer_name}\n"
            . "\xF0\x9F\x93\x9E WhatsApp: https://wa.me/{$order->customer_phone}\n\n"
            . "\xF0\x9F\x93\x8D Direccion: " . ($order->delivery_address ?? 'No especificada');

        if ($order->delivery_latitude && $order->delivery_longitude) {
            $mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . $order->delivery_latitude . ',' . $order->delivery_longitude;
            $text .= "\n\xF0\x9F\x97\xBA\xEF\xB8\x8F " . $mapUrl;
        } elseif ($order->delivery_address) {
            $mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($order->delivery_address);
            $text .= "\n\xF0\x9F\x97\xBA\xEF\xB8\x8F " . $mapUrl;
        }

        return $text;
    }

    public static function driverAssignmentButtons(int $orderId): array
    {
        return [
            ['id' => "drv_delivered_{$orderId}", 'title' => 'Marcar Entregado'],
            ['id' => "drv_call_{$orderId}", 'title' => 'Llamar Cliente'],
            ['id' => "drv_map_{$orderId}", 'title' => 'Ver Ubicacion'],
        ];
    }

    public static function driverDeliveryConfirmation(string $orderNumber): string
    {
        return "\xE2\x9C\x85 Pedido *#{$orderNumber}* marcado como entregado. Gracias!";
    }

    public static function driverCashReminder(string $orderNumber, float $total): string
    {
        $formatted = number_format($total, 0, '.', ',');
        return "\xF0\x9F\x92\xB5 Recuerda entregar el cobro de RD\${$formatted} del pedido #{$orderNumber} al negocio.";
    }

    public static function driverCustomerContact(string $customerName, string $customerPhone): string
    {
        return "\xF0\x9F\x91\xA4 *{$customerName}*\n\xF0\x9F\x93\x9E https://wa.me/{$customerPhone}";
    }

    public static function driverMapLink(string $address, ?float $latitude = null, ?float $longitude = null): string
    {
        if ($latitude && $longitude) {
            $mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . $latitude . ',' . $longitude;
        } else {
            $mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($address);
        }
        return "\xF0\x9F\x93\x8D *Ubicacion de entrega:*\n{$address}\n\n\xF0\x9F\x97\xBA\xEF\xB8\x8F {$mapUrl}";
    }

    private static function driverPaymentLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Efectivo (cobrar al entregar)',
            'transfer' => 'Transferencia (ya pagado)',
            'card_link' => 'Pago con link (ya pagado)',
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    public static function orderStatusText(string $orderNumber, string $status): string
    {
        $messages = [
            'confirmed' => "Tu pedido #{$orderNumber} ha sido confirmado. Te avisaremos cuando este en preparacion.",
            'in_preparation' => "Tu pedido #{$orderNumber} esta siendo preparado.",
            'ready' => "Tu pedido #{$orderNumber} esta listo.",
            'out_for_delivery' => "Tu pedido #{$orderNumber} esta en camino.",
            'delivered' => "Tu pedido #{$orderNumber} ha sido entregado. Gracias por tu compra! Escribe cuando quieras pedir de nuevo.",
            'cancelled' => "Tu pedido #{$orderNumber} ha sido cancelado. Puedes hacer un nuevo pedido cuando quieras.",
        ];

        return $messages[$status] ?? "Tu pedido #{$orderNumber} - estado: {$status}";
    }
}
