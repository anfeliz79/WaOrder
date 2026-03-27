<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class PublicOrderStatusController extends Controller
{
    public function show(Request $request, string $orderNumber)
    {
        $request->validate([
            'phone' => 'required|string|min:4|max:4',
        ]);

        $order = Order::where('order_number', $orderNumber)
            ->where('customer_phone', 'like', '%' . $request->phone)
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Orden no encontrada'], 404);
        }

        return response()->json([
            'order_number' => $order->order_number,
            'status' => $order->status,
            'delivery_type' => $order->delivery_type,
            'total' => $order->total,
            'confirmed_at' => $order->confirmed_at?->toISOString(),
            'estimated_ready_at' => $order->estimated_ready_at?->toISOString(),
        ]);
    }
}
