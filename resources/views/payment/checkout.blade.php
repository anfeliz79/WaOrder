<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pagar Pedido — {{ $tenant->name }}</title>
    @vite(['resources/js/landing.js'])
</head>
<body class="bg-gray-50 font-[Inter] min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
        <div class="text-center mb-6">
            <h1 class="text-xl font-bold text-gray-900">{{ $tenant->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">Pedido #{{ $order->order_number }}</p>
        </div>

        <div class="bg-gray-50 rounded-xl p-5 mb-6">
            <div class="flex justify-between text-sm mb-2">
                <span class="text-gray-500">Subtotal</span>
                <span class="text-gray-900">{{ number_format($order->subtotal, 2) }} {{ $tenant->currency }}</span>
            </div>
            @if($order->delivery_fee > 0)
            <div class="flex justify-between text-sm mb-2">
                <span class="text-gray-500">Delivery</span>
                <span class="text-gray-900">{{ number_format($order->delivery_fee, 2) }} {{ $tenant->currency }}</span>
            </div>
            @endif
            @if($order->tax > 0)
            <div class="flex justify-between text-sm mb-2">
                <span class="text-gray-500">Impuestos</span>
                <span class="text-gray-900">{{ number_format($order->tax, 2) }} {{ $tenant->currency }}</span>
            </div>
            @endif
            <hr class="my-3 border-gray-200">
            <div class="flex justify-between font-bold">
                <span class="text-gray-900">Total</span>
                <span class="text-gray-900">{{ number_format($order->total, 2) }} {{ $tenant->currency }}</span>
            </div>
        </div>

        <p class="text-sm text-gray-500 text-center">
            Redirigiendo al procesador de pago...
        </p>
        <p class="text-xs text-gray-400 text-center mt-2">
            Si no eres redirigido automaticamente, contacta al restaurante.
        </p>

        <div class="mt-8 pt-6 border-t border-gray-100 text-center">
            <div class="flex items-center justify-center gap-2">
                <div class="w-6 h-6 bg-gradient-to-br from-indigo-500 to-violet-600 rounded flex items-center justify-center">
                    <span class="text-white font-bold text-xs">W</span>
                </div>
                <span class="text-sm font-medium text-gray-400">Procesado por WaOrder</span>
            </div>
        </div>
    </div>
</body>
</html>
