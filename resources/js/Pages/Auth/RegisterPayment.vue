<script setup>
import { ref, onMounted, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { CreditCard, CheckCircle, AlertCircle, Loader2, ShieldCheck, ArrowLeft } from 'lucide-vue-next'
import axios from 'axios'

const props = defineProps({
    plan: Object,
    publicKey: String,
    checkoutScriptBase: String,
})

const state = ref('idle') // idle | loading_script | ready | processing | success | error
const errorMessage = ref('')
const scriptLoaded = ref(false)

const planPrice = computed(() => {
    const price = parseFloat(props.plan?.price_monthly || 0)
    const currency = props.plan?.currency || 'DOP'
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(price)
})

onMounted(() => {
    if (!props.publicKey) {
        state.value = 'error'
        errorMessage.value = 'No hay credenciales de pago configuradas. Contacta soporte.'
        return
    }
    loadCheckoutScript()
})

const loadCheckoutScript = () => {
    state.value = 'loading_script'

    const script = document.createElement('script')
    script.src = `${props.checkoutScriptBase}/Scripts/PWCheckout.js?key=${props.publicKey}`
    script.async = true
    script.onload = initCheckout
    script.onerror = () => {
        state.value = 'error'
        errorMessage.value = 'No se pudo cargar el módulo de pago. Intenta recargar la página.'
    }
    document.head.appendChild(script)
}

const initCheckout = () => {
    if (!window.PWCheckout) {
        state.value = 'error'
        errorMessage.value = 'Error al inicializar el módulo de pago.'
        return
    }

    window.PWCheckout.SetProperties({
        name: 'WaOrder',
        button_label: `Pagar ${planPrice.value}/mes`,
        currency: props.plan?.currency || 'DOP',
        amount: String(parseFloat(props.plan?.price_monthly || 0).toFixed(2)),
        form_id: 'cardnet-form',
        lang: 'ESP',
        description: `Suscripción ${props.plan?.name}`,
    })

    window.PWCheckout.AddActionButton('cardnet-pay-btn')

    window.PWCheckout.Bind('tokenCreated', handleTokenCreated)
    window.PWCheckout.Bind('tokenError', handleTokenError)

    scriptLoaded.value = true
    state.value = 'ready'
}

const handleTokenCreated = async (tokenObj) => {
    state.value = 'processing'
    errorMessage.value = ''

    try {
        const response = await axios.post('/register/payment/tokenize', {
            token_id:     tokenObj.TokenId,
            brand:        tokenObj.Brand,
            last4:        tokenObj.Last4,
            expiry_month: tokenObj.CardExpMonth,
            expiry_year:  tokenObj.CardExpYear,
        })

        if (response.data.success) {
            state.value = 'success'
            setTimeout(() => {
                router.visit(response.data.redirect || '/setup')
            }, 1500)
        }
    } catch (err) {
        state.value = 'error'
        errorMessage.value = err.response?.data?.message
            || 'Error al procesar el pago. Intenta de nuevo.'
    }
}

const handleTokenError = (err) => {
    state.value = 'error'
    errorMessage.value = 'No se pudo procesar la tarjeta. Verifica los datos e intenta de nuevo.'
    console.error('PWCheckout tokenError:', err)
}

const retry = () => {
    state.value = 'ready'
    errorMessage.value = ''
}
</script>

<template>
    <Head title="Activar Suscripción — WaOrder" />

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-indigo-50 flex items-center justify-center py-12 px-4">
        <div class="w-full max-w-md">

            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-2.5">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-xl flex items-center justify-center">
                        <span class="text-white font-bold text-lg">W</span>
                    </div>
                    <span class="font-bold text-2xl text-gray-900">WaOrder</span>
                </div>
            </div>

            <!-- Success state -->
            <div v-if="state === 'success'" class="bg-white rounded-2xl shadow-xl border border-gray-100 p-8 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <CheckCircle class="w-8 h-8 text-green-600" />
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">¡Pago exitoso!</h2>
                <p class="text-gray-500 text-sm">Redirigiendo a la configuración inicial…</p>
            </div>

            <!-- Payment card -->
            <div v-else class="bg-white rounded-2xl shadow-xl border border-gray-100 p-8">

                <!-- Header -->
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center">
                        <CreditCard class="w-5 h-5 text-indigo-600" />
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Activar Suscripción</h2>
                        <p class="text-sm text-gray-500">Ingresa los datos de tu tarjeta para comenzar</p>
                    </div>
                </div>

                <!-- Plan summary -->
                <div class="bg-indigo-50 rounded-xl p-4 mb-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm font-semibold text-indigo-900">Plan {{ plan?.name }}</p>
                            <p class="text-xs text-indigo-600 mt-0.5">Facturación mensual</p>
                        </div>
                        <p class="text-xl font-bold text-indigo-700">{{ planPrice }}<span class="text-sm font-normal">/mes</span></p>
                    </div>
                </div>

                <!-- Error message -->
                <div v-if="state === 'error' && errorMessage" class="mb-4 p-3 bg-red-50 border border-red-100 rounded-xl flex items-start gap-2">
                    <AlertCircle class="w-4 h-4 text-red-500 mt-0.5 shrink-0" />
                    <p class="text-sm text-red-700">{{ errorMessage }}</p>
                </div>

                <!-- Hidden form required by PWCheckout.js -->
                <form id="cardnet-form">
                    <input type="hidden" name="PWToken" id="PWToken" />
                </form>

                <!-- Loading script -->
                <div v-if="state === 'loading_script'" class="flex items-center justify-center py-6 gap-2 text-gray-500">
                    <Loader2 class="w-5 h-5 animate-spin" />
                    <span class="text-sm">Cargando módulo de pago…</span>
                </div>

                <!-- Processing -->
                <div v-else-if="state === 'processing'" class="flex items-center justify-center py-6 gap-2 text-indigo-600">
                    <Loader2 class="w-5 h-5 animate-spin" />
                    <span class="text-sm font-medium">Procesando pago…</span>
                </div>

                <!-- Pay button (managed by PWCheckout.js) -->
                <div v-else-if="state === 'ready'">
                    <button
                        id="cardnet-pay-btn"
                        type="button"
                        class="w-full py-3 px-6 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition-all text-sm shadow-lg shadow-indigo-200 flex items-center justify-center gap-2"
                    >
                        <CreditCard class="w-4 h-4" />
                        Ingresar datos de tarjeta
                    </button>
                </div>

                <!-- Retry after error -->
                <div v-else-if="state === 'error'">
                    <button
                        @click="retry"
                        type="button"
                        class="w-full py-3 px-6 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition-all text-sm"
                    >
                        Intentar de nuevo
                    </button>
                </div>

                <!-- Security note -->
                <div class="mt-4 flex items-center justify-center gap-1.5 text-gray-400">
                    <ShieldCheck class="w-4 h-4" />
                    <p class="text-xs">Pago seguro procesado por Cardnet</p>
                </div>
            </div>

            <!-- Back link -->
            <div v-if="state !== 'success'" class="text-center mt-4">
                <a href="/register" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft class="w-3.5 h-3.5" />
                    Volver al registro
                </a>
            </div>
        </div>
    </div>
</template>
