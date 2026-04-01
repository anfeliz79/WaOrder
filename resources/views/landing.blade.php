<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WaOrder — Pedidos por WhatsApp para tu Restaurante</title>
    <meta name="description" content="Recibe pedidos de tu restaurante directamente por WhatsApp. Chatbot automatizado, menu digital, delivery tracking y mas.">
    <meta property="og:title" content="WaOrder — Pedidos por WhatsApp para tu Restaurante">
    <meta property="og:description" content="Automatiza los pedidos de tu restaurante con un chatbot de WhatsApp inteligente.">
    <meta property="og:type" content="website">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/js/landing.js'])
</head>
<body class="bg-white text-gray-900 font-[Inter]" x-data="{ mobileMenu: false, billing: 'monthly' }" x-init="window.waWhatsApp = '{{ config('app.whatsapp_contact', '18091234567') }}'">

    {{-- Navigation --}}
    <nav class="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur-lg border-b border-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 flex items-center justify-between h-16">
            <a href="/" class="flex items-center gap-2.5">
                <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-lg flex items-center justify-center">
                    <span class="text-white font-bold text-sm">W</span>
                </div>
                <span class="font-bold text-lg text-gray-900">WaOrder</span>
            </a>
            <div class="hidden md:flex items-center gap-8">
                <a href="#features" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Funciones</a>
                <a href="#how-it-works" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Como Funciona</a>
                <a href="#pricing" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">Precios</a>
                <a href="#faq" class="text-sm text-gray-600 hover:text-gray-900 transition-colors">FAQ</a>
            </div>
            <div class="hidden md:flex items-center gap-3">
                <a href="https://wa.me/{{ config('app.whatsapp_contact', '18091234567') }}?text={{ urlencode('Hola, me interesa WaOrder. Quisiera mas informacion.') }}" target="_blank" class="text-sm font-medium text-green-600 hover:text-green-700 px-4 py-2 flex items-center gap-1.5">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.5.5 0 00.611.611l4.458-1.495A11.96 11.96 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.3 0-4.438-.663-6.24-1.804l-.436-.268-2.646.887.887-2.646-.268-.436A9.96 9.96 0 012 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
                    Contacto
                </a>
                <a href="/login" class="text-sm font-medium text-gray-700 hover:text-gray-900 px-4 py-2">Iniciar Sesion</a>
                <a href="/register" class="text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 px-5 py-2.5 rounded-lg transition-colors">Registrate</a>
            </div>
            <button @click="mobileMenu = !mobileMenu" class="md:hidden p-2 text-gray-600">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path x-show="!mobileMenu" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path x-show="mobileMenu" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        {{-- Mobile menu --}}
        <div x-show="mobileMenu" x-transition class="md:hidden bg-white border-t border-gray-100 px-4 py-4 space-y-3">
            <a href="#features" class="block text-sm text-gray-600 py-2" @click="mobileMenu = false">Funciones</a>
            <a href="#how-it-works" class="block text-sm text-gray-600 py-2" @click="mobileMenu = false">Como Funciona</a>
            <a href="#pricing" class="block text-sm text-gray-600 py-2" @click="mobileMenu = false">Precios</a>
            <a href="#faq" class="block text-sm text-gray-600 py-2" @click="mobileMenu = false">FAQ</a>
            <hr class="border-gray-100">
            <a href="https://wa.me/{{ config('app.whatsapp_contact', '18091234567') }}?text={{ urlencode('Hola, me interesa WaOrder.') }}" target="_blank" class="block text-sm font-medium text-green-600 py-2">Contactar por WhatsApp</a>
            <a href="/login" class="block text-sm font-medium text-gray-700 py-2">Iniciar Sesion</a>
            <a href="/register" class="block text-sm font-medium text-center text-white bg-indigo-600 rounded-lg py-2.5">Registrate</a>
        </div>
    </nav>

    {{-- Hero --}}
    <section class="pt-28 pb-20 sm:pt-36 sm:pb-28">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center">
            <div class="inline-flex items-center gap-2 bg-indigo-50 text-indigo-700 text-xs font-medium px-3 py-1.5 rounded-full mb-6">
                <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full animate-pulse"></span>
                Plataforma SaaS para restaurantes
            </div>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-gray-900 leading-tight">
                Recibe pedidos por<br>
                <span class="bg-gradient-to-r from-indigo-600 to-violet-600 bg-clip-text text-transparent">WhatsApp</span>
                automaticamente
            </h1>
            <p class="mt-6 text-lg sm:text-xl text-gray-500 max-w-2xl mx-auto leading-relaxed">
                Un chatbot inteligente que toma pedidos, gestiona el menu, rastrea entregas y cobra a tus clientes. Todo desde el WhatsApp que ya usan.
            </p>
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="/register" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200">
                    Empezar ahora
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                </a>
                <a href="https://wa.me/{{ config('app.whatsapp_contact', '18091234567') }}?text={{ urlencode('Hola, quiero saber mas sobre WaOrder para mi restaurante.') }}" target="_blank"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-3.5 bg-green-500 text-white font-semibold rounded-xl hover:bg-green-600 transition-all shadow-lg shadow-green-200">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.5.5 0 00.611.611l4.458-1.495A11.96 11.96 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.3 0-4.438-.663-6.24-1.804l-.436-.268-2.646.887.887-2.646-.268-.436A9.96 9.96 0 012 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
                    Solicitar asistencia
                </a>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section id="features" class="py-20 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="text-center mb-14">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Todo lo que necesitas</h2>
                <p class="mt-4 text-lg text-gray-500">Una plataforma completa para digitalizar los pedidos de tu restaurante.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @php
                $features = [
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />', 'title' => 'Chatbot WhatsApp', 'desc' => 'Un bot inteligente que guia al cliente por el menu, toma el pedido y confirma. Sin apps, sin formularios.'],
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />', 'title' => 'Menu Digital', 'desc' => 'Menu con categorias, modificadores, precios e imagenes. Configurable desde el panel admin.'],
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />', 'title' => 'Multi-Sucursal', 'desc' => 'Gestiona multiples sucursales con zonas de entrega, precios independientes y equipo por sucursal.'],
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />', 'title' => 'Tracking de Delivery', 'desc' => 'App movil para mensajeros con notificaciones push, estados en tiempo real y contacto directo.'],
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />', 'title' => 'Pagos Online', 'desc' => 'Cobro con tarjeta via Cardnet integrado al flujo del pedido. Tambien efectivo y transferencia.'],
                    ['icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />', 'title' => 'Panel Admin', 'desc' => 'Dashboard completo con ordenes, clientes, estadisticas, configuracion y gestion de equipo.'],
                ];
                @endphp
                @foreach($features as $feature)
                <div class="bg-white rounded-2xl p-6 border border-gray-100 hover:border-indigo-100 hover:shadow-lg hover:shadow-indigo-50 transition-all duration-300">
                    <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">{!! $feature['icon'] !!}</svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ $feature['title'] }}</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">{{ $feature['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- How It Works --}}
    <section id="how-it-works" class="py-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="text-center mb-14">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Asi de facil</h2>
                <p class="mt-4 text-lg text-gray-500">En 3 pasos tu restaurante esta recibiendo pedidos por WhatsApp.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @php
                $steps = [
                    ['num' => '1', 'title' => 'Registrate', 'desc' => 'Crea tu cuenta, configura tu restaurante y sube tu menu. Toma menos de 10 minutos.'],
                    ['num' => '2', 'title' => 'Conecta WhatsApp', 'desc' => 'Conecta tu numero de WhatsApp Business. Nosotros configuramos el chatbot automaticamente.'],
                    ['num' => '3', 'title' => 'Recibe Pedidos', 'desc' => 'Tus clientes escriben al WhatsApp, el bot toma el pedido y tu lo ves en el panel admin.'],
                ];
                @endphp
                @foreach($steps as $step)
                <div class="text-center">
                    <div class="w-14 h-14 bg-indigo-600 text-white rounded-2xl flex items-center justify-center text-xl font-bold mx-auto mb-5">
                        {{ $step['num'] }}
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ $step['title'] }}</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">{{ $step['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Pricing --}}
    <section id="pricing" class="py-20 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="text-center mb-10">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Precios simples</h2>
                <p class="mt-4 text-lg text-gray-500">Escoge el plan que se adapte a tu negocio. Sin costos ocultos.</p>
                {{-- Billing toggle --}}
                <div class="mt-8 inline-flex items-center bg-white border border-gray-200 rounded-xl p-1">
                    <button @click="billing = 'monthly'"
                        :class="billing === 'monthly' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                        class="px-5 py-2 text-sm font-medium rounded-lg transition-all">
                        Mensual
                    </button>
                    <button @click="billing = 'annual'"
                        :class="billing === 'annual' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                        class="px-5 py-2 text-sm font-medium rounded-lg transition-all">
                        Anual
                        <span class="ml-1 text-xs opacity-75">(-17%)</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-{{ min(count($plans), 3) }} gap-8 max-w-5xl mx-auto">
                @foreach($plans as $index => $plan)
                @php
                    $isPopular = $index === 1 && count($plans) > 1;
                @endphp
                <div class="relative bg-white rounded-2xl border {{ $isPopular ? 'border-indigo-200 shadow-xl shadow-indigo-100 ring-2 ring-indigo-600' : 'border-gray-200 shadow-sm' }} overflow-hidden">
                    @if($isPopular)
                    <div class="bg-indigo-600 text-white text-xs font-semibold text-center py-1.5">Mas Popular</div>
                    @endif
                    <div class="p-8">
                        <h3 class="text-xl font-bold text-gray-900">{{ $plan->name }}</h3>
                        <p class="mt-2 text-sm text-gray-500">{{ $plan->description }}</p>
                        <div class="mt-6 flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold text-gray-900"
                                x-text="billing === 'annual' && {{ $plan->price_annual ? 'true' : 'false' }}
                                    ? '{{ $plan->price_annual ? number_format($plan->price_annual / 12, 0) : number_format($plan->price_monthly, 0) }}'
                                    : '{{ $plan->price_monthly > 0 ? number_format($plan->price_monthly, 0) : 'Gratis' }}'">
                                {{ $plan->price_monthly > 0 ? number_format($plan->price_monthly, 0) : 'Gratis' }}
                            </span>
                            @if($plan->price_monthly > 0)
                            <span class="text-sm text-gray-500">{{ $plan->currency ?? 'USD' }}/mes</span>
                            @endif
                        </div>

                        <a href="/register?plan={{ $plan->slug }}"
                            class="mt-8 block w-full text-center py-3 rounded-xl text-sm font-semibold transition-all
                            {{ $isPopular
                                ? 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-lg shadow-indigo-200'
                                : 'bg-gray-900 text-white hover:bg-gray-800' }}">
                            Empezar
                        </a>

                        @php
                            $check = '<svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>';
                        @endphp
                        <ul class="mt-8 space-y-3">
                            <li class="flex items-center gap-3 text-sm text-gray-600">
                                {!! $check !!}
                                {{ $plan->max_branches ? ($plan->max_branches . ' ' . ($plan->max_branches === 1 ? 'sucursal' : 'sucursales')) : 'Sucursales ilimitadas' }}
                            </li>
                            <li class="flex items-center gap-3 text-sm text-gray-600">
                                {!! $check !!}
                                {{ $plan->max_menu_items ? ('Hasta ' . $plan->max_menu_items . ' items de menu') : 'Items de menu ilimitados' }}
                            </li>
                            <li class="flex items-center gap-3 text-sm text-gray-600">
                                {!! $check !!}
                                {{ $plan->max_drivers ? ($plan->max_drivers . ' ' . ($plan->max_drivers === 1 ? 'mensajero' : 'mensajeros')) : 'Mensajeros ilimitados' }}
                            </li>
                            <li class="flex items-center gap-3 text-sm text-gray-600">
                                {!! $check !!}
                                {{ $plan->max_orders_per_month ? (number_format($plan->max_orders_per_month) . ' ordenes/mes') : 'Ordenes ilimitadas' }}
                            </li>
                            <li class="flex items-center gap-3 text-sm text-gray-600">
                                {!! $check !!}
                                {{ $plan->max_users ? ($plan->max_users . ' ' . ($plan->max_users === 1 ? 'usuario' : 'usuarios')) : 'Usuarios ilimitados' }}
                            </li>
                            @if($plan->whatsapp_bot_enabled)
                            <li class="flex items-center gap-3 text-sm text-gray-600">
                                <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                Bot de WhatsApp
                            </li>
                            @endif
                            @if($plan->ai_enabled)
                            <li class="flex items-center gap-3 text-sm text-gray-600">
                                <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                Reconocimiento IA
                            </li>
                            @endif
                            @if($plan->external_menu_enabled)
                            <li class="flex items-center gap-3 text-sm text-gray-600">
                                <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                Menu externo (API)
                            </li>
                            @endif
                        </ul>

                        @if($plan->support_addon_available || $plan->delivery_app_addon_available)
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Add-ons disponibles</p>
                            @if($plan->support_addon_available)
                            <p class="text-sm text-gray-500">Soporte premium +{{ number_format($plan->support_addon_price, 0) }} {{ $plan->currency ?? 'USD' }}/mes</p>
                            @endif
                            @if($plan->delivery_app_addon_available)
                            <p class="text-sm text-gray-500">App Delivery +{{ number_format($plan->delivery_app_addon_price, 0) }} {{ $plan->currency ?? 'USD' }}/mes</p>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="py-20">
        <div class="max-w-3xl mx-auto px-4 sm:px-6">
            <div class="text-center mb-12">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Preguntas frecuentes</h2>
            </div>
            <div class="space-y-4" x-data="{ open: null }">
                @php
                $faqs = [
                    ['q' => 'Necesito un numero de WhatsApp especial?', 'a' => 'Necesitas un numero con WhatsApp Business API. Si ya tienes un WhatsApp Business regular, podemos ayudarte a migrar. El proceso toma unos minutos.'],
                    ['q' => 'Que metodos de pago aceptan mis clientes?', 'a' => 'Tus clientes pueden pagar con efectivo, transferencia bancaria o tarjeta de credito/debito via Cardnet (integracion directa en el flujo de WhatsApp).'],
                    ['q' => 'Puedo tener varias sucursales?', 'a' => 'Si. Dependiendo de tu plan puedes configurar multiples sucursales, cada una con su propia zona de entrega, delivery fee y equipo.'],
                    ['q' => 'Como se cobra la suscripcion?', 'a' => 'La suscripcion se cobra mensual o anualmente con tarjeta de credito/debito via Cardnet. Puedes cambiar de plan o cancelar en cualquier momento.'],
                    ['q' => 'Que pasa si cancelo?', 'a' => 'Tu cuenta seguira activa hasta el final del periodo facturado. No se realizan cobros adicionales. Puedes reactivar en cualquier momento.'],
                    ['q' => 'Como puedo contactarlos para mas informacion?', 'a' => 'Puedes escribirnos directamente por WhatsApp haciendo clic en el boton "Solicitar asistencia" o en "Contacto" en la barra de navegacion. Respondemos en minutos.'],
                ];
                @endphp
                @foreach($faqs as $index => $faq)
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <button @click="open = open === {{ $index }} ? null : {{ $index }}"
                        class="w-full flex items-center justify-between px-6 py-4 text-left text-sm font-medium text-gray-900 hover:bg-gray-50 transition-colors">
                        <span>{{ $faq['q'] }}</span>
                        <svg :class="open === {{ $index }} ? 'rotate-180' : ''" class="w-5 h-5 text-gray-400 transition-transform shrink-0 ml-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="open === {{ $index }}" x-collapse>
                        <div class="px-6 pb-4 text-sm text-gray-500 leading-relaxed">{{ $faq['a'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="py-20 bg-indigo-600">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center">
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Listo para automatizar tus pedidos?</h2>
            <p class="mt-4 text-lg text-indigo-100">Registrate y configura tu restaurante en minutos.</p>
            <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="/register" class="inline-flex items-center gap-2 px-8 py-3.5 bg-white text-indigo-600 font-semibold rounded-xl hover:bg-indigo-50 transition-all">
                    Crear mi cuenta
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                </a>
                <a href="https://wa.me/{{ config('app.whatsapp_contact', '18091234567') }}?text={{ urlencode('Hola, necesito asistencia con WaOrder.') }}" target="_blank"
                    class="inline-flex items-center gap-2 px-8 py-3.5 bg-green-500 text-white font-semibold rounded-xl hover:bg-green-600 transition-all">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.5.5 0 00.611.611l4.458-1.495A11.96 11.96 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.3 0-4.438-.663-6.24-1.804l-.436-.268-2.646.887.887-2.646-.268-.436A9.96 9.96 0 012 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
                    Solicitar asistencia
                </a>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="py-12 bg-gray-900">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-sm">W</span>
                    </div>
                    <span class="font-bold text-lg text-white">WaOrder</span>
                </div>
                <div class="flex items-center gap-6 text-sm text-gray-400">
                    <a href="#features" class="hover:text-white transition-colors">Funciones</a>
                    <a href="#pricing" class="hover:text-white transition-colors">Precios</a>
                    <a href="https://wa.me/{{ config('app.whatsapp_contact', '18091234567') }}" target="_blank" class="hover:text-green-400 transition-colors">WhatsApp</a>
                    <a href="/login" class="hover:text-white transition-colors">Login</a>
                </div>
                <p class="text-sm text-gray-500">&copy; {{ date('Y') }} WaOrder. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

</body>
</html>
