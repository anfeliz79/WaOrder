<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estado del Pago — WaOrder</title>
    @vite(['resources/js/landing.js'])
</head>
<body class="bg-gray-50 font-[Inter] min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg border border-gray-100 p-8 text-center">
        @if($session->status === 'approved')
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Pago Exitoso</h1>
            <p class="mt-2 text-gray-500">Tu pedido #{{ $order->order_number ?? '' }} ha sido pagado correctamente.</p>
            <p class="mt-4 text-sm text-gray-400">Puedes cerrar esta ventana y volver a WhatsApp.</p>
        @elseif($session->status === 'rejected')
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Pago Cancelado</h1>
            <p class="mt-2 text-gray-500">El pago de tu pedido #{{ $order->order_number ?? '' }} no se completo.</p>
            <p class="mt-4 text-sm text-gray-400">Vuelve a WhatsApp para intentar de nuevo o elegir otro metodo de pago.</p>
        @elseif($session->status === 'expired')
            <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Enlace Expirado</h1>
            <p class="mt-2 text-gray-500">El enlace de pago ha expirado. Escribe al WhatsApp para generar uno nuevo.</p>
        @else
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 animate-pulse">
                <svg class="w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Procesando Pago</h1>
            <p class="mt-2 text-gray-500">Tu pago esta siendo procesado. Espera un momento...</p>
        @endif

        <div class="mt-8 pt-6 border-t border-gray-100">
            <div class="flex items-center justify-center gap-2">
                <div class="w-6 h-6 bg-gradient-to-br from-indigo-500 to-violet-600 rounded flex items-center justify-center">
                    <span class="text-white font-bold text-xs">W</span>
                </div>
                <span class="text-sm font-medium text-gray-400">WaOrder</span>
            </div>
        </div>
    </div>
</body>
</html>
