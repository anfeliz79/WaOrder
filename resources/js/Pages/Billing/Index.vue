<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
    CreditCard, ArrowUpRight, X, Check, AlertTriangle, Clock,
    Store, UtensilsCrossed, Truck, Users, ShoppingBag,
    Wallet, Building2, Headphones, Smartphone, Loader2, Pencil, ShieldCheck
} from 'lucide-vue-next'
import axios from 'axios'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    subscription: Object,
    paymentMethod: Object,
    invoices: Array,
    plans: Array,
    usage: Object,
    availablePaymentMethods: { type: Array, default: () => [] },
    publicKey: String,
    checkoutScriptBase: String,
    bankAccounts: { type: Array, default: () => [] },
})

const page = usePage()
const showChangePlanModal = ref(false)
const showCancelModal = ref(false)
const showPaymentMethodModal = ref(false)
const cancelReason = ref('')
const selectedPlanId = ref(null)
const togglingAddon = ref(null)
const changingPlan = ref(false)
const cancelling = ref(false)

// ── Payment method change state ─────────────────────────────────────────────
const selectedPaymentMethod = ref(null)
const cardState = ref('idle') // idle | loading_script | ready | processing | success | error
const cardError = ref('')
const scriptLoaded = ref(false)
const paypalLoading = ref(false)
const paypalError = ref(null)
const bankTransferLoading = ref(false)

const plan = computed(() => props.subscription?.plan)
const limits = computed(() => plan.value || {})

const availableAddons = computed(() => {
    if (!plan.value) return []
    const addons = []
    if (plan.value.support_addon_available) {
        addons.push({
            type: 'support',
            label: 'Soporte Premium',
            description: 'Soporte tecnico prioritario con tiempo de respuesta garantizado',
            icon: Headphones,
            price: parseFloat(plan.value.support_addon_price) || 0,
            isActive: props.subscription?.addons?.some(a => a.addon_type === 'support' && a.is_active) || false,
        })
    }
    if (plan.value.delivery_app_addon_available) {
        addons.push({
            type: 'delivery_app',
            label: 'App de Delivery',
            description: 'Aplicacion movil para tus mensajeros con tracking en tiempo real',
            icon: Smartphone,
            price: parseFloat(plan.value.delivery_app_addon_price) || 0,
            isActive: props.subscription?.addons?.some(a => a.addon_type === 'delivery_app' && a.is_active) || false,
        })
    }
    return addons
})

const toggleAddon = async (addonType, currentlyActive) => {
    togglingAddon.value = addonType

    // For PayPal addon activation, use fetch to detect if approval is needed
    if (!currentlyActive && props.subscription?.payment_method === 'paypal') {
        try {
            const response = await fetch('/billing/addon/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ addon_type: addonType, action: 'activate' }),
            })
            const data = await response.json()
            if (data.paypal_approval_url) {
                openPayPalPopup(data.paypal_approval_url)
                return // Don't reset togglingAddon — postMessage handler will do it
            }
            // No approval needed (shouldn't happen for PayPal, but handle gracefully)
            router.reload({ onFinish: () => { togglingAddon.value = null } })
        } catch {
            togglingAddon.value = null
            router.reload()
        }
        return
    }

    router.post('/billing/addon/toggle', {
        addon_type: addonType,
        action: currentlyActive ? 'deactivate' : 'activate',
    }, {
        preserveScroll: true,
        onFinish: () => { togglingAddon.value = null },
    })
}

const statusBadge = computed(() => {
    const map = {
        active: { label: 'Activo', class: 'bg-green-100 text-green-700' },
        trialing: { label: 'Prueba', class: 'bg-blue-100 text-blue-700' },
        past_due: { label: 'Pago pendiente', class: 'bg-amber-100 text-amber-700' },
        cancelled: { label: 'Cancelado', class: 'bg-red-100 text-red-700' },
        suspended: { label: 'Suspendido', class: 'bg-red-100 text-red-700' },
        pending_payment: { label: 'Pago pendiente', class: 'bg-amber-100 text-amber-700' },
    }
    return map[props.subscription?.status] || { label: 'Sin plan', class: 'bg-gray-100 text-gray-700' }
})

const formatPrice = (price) => {
    if (!price || parseFloat(price) === 0) return 'Gratis'
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP', maximumFractionDigits: 0 }).format(price)
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('es-DO', { day: 'numeric', month: 'short', year: 'numeric' })
}

const usagePercent = (current, max) => {
    if (!max) return 0
    return Math.min(100, Math.round((current / max) * 100))
}

const changePlan = async () => {
    if (!selectedPlanId.value || changingPlan.value) return
    changingPlan.value = true

    // For PayPal, use fetch to detect approval redirect
    if (isPayPal.value) {
        try {
            const response = await fetch('/billing/change-plan', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ plan_id: selectedPlanId.value }),
            })
            const data = await response.json()
            if (data.paypal_approval_url) {
                openPayPalPopup(data.paypal_approval_url)
                return // postMessage handler resets state
            }
            // No approval needed (downgrade) — reload
            showChangePlanModal.value = false
            changingPlan.value = false
            router.reload()
        } catch {
            changingPlan.value = false
            router.reload()
        }
        return
    }

    router.post('/billing/change-plan', { plan_id: selectedPlanId.value }, {
        preserveScroll: true,
        onSuccess: () => { showChangePlanModal.value = false },
        onFinish: () => { changingPlan.value = false },
    })
}

const cancelSubscription = () => {
    if (cancelling.value) return
    cancelling.value = true
    router.post('/billing/cancel', { reason: cancelReason.value }, {
        preserveScroll: true,
        onSuccess: () => { showCancelModal.value = false; cancelReason.value = '' },
        onFinish: () => { cancelling.value = false },
    })
}

const isPayPal = computed(() => props.subscription?.payment_method === 'paypal')
const isBankTransfer = computed(() => props.subscription?.payment_method === 'bank_transfer')

const reactivate = () => {
    router.post('/billing/reactivate', {}, { preserveScroll: true })
}

const invoiceStatusBadge = (status) => {
    const map = {
        paid: { label: 'Pagado', class: 'bg-green-100 text-green-700' },
        pending: { label: 'Pendiente', class: 'bg-amber-100 text-amber-700' },
        failed: { label: 'Fallido', class: 'bg-red-100 text-red-700' },
        draft: { label: 'Borrador', class: 'bg-gray-100 text-gray-600' },
        refunded: { label: 'Reembolsado', class: 'bg-blue-100 text-blue-700' },
    }
    return map[status] || { label: status, class: 'bg-gray-100 text-gray-600' }
}

// ── Addon messaging based on payment method ─────────────────────────────────
const addonNote = computed(() => {
    const method = props.subscription?.payment_method
    if (method === 'paypal') return 'Activar un addon redirige a PayPal para aprobar el ajuste en tu suscripcion.'
    if (method === 'bank_transfer') return 'El costo del addon se incluira en tu proximo ciclo de facturacion.'
    if (method === 'cardnet') return 'El addon se activara inmediatamente y se cargara a tu tarjeta.'
    return null
})

// ── PayPal popup helper ─────────────────────────────────────────────────────
const openPayPalPopup = (url) => {
    const w = 500, h = 700
    const left = (screen.width - w) / 2
    const top = (screen.height - h) / 2
    return window.open(url, 'PayPalPopup', `width=${w},height=${h},left=${left},top=${top},scrollbars=yes`)
}

onMounted(() => {
    window.addEventListener('message', handlePayPalMessage)
})

onUnmounted(() => {
    window.removeEventListener('message', handlePayPalMessage)
})

const handlePayPalMessage = (event) => {
    if (event.data?.type !== 'paypal-complete') return
    // Reload page to reflect changes
    paypalLoading.value = false
    togglingAddon.value = null
    changingPlan.value = false
    showPaymentMethodModal.value = false
    showChangePlanModal.value = false
    router.reload()
}

// ── Payment method icon map ─────────────────────────────────────────────────
const methodIconMap = { cardnet: CreditCard, bank_transfer: Building2, paypal: Wallet }
const getMethodIcon = (slug) => methodIconMap[slug] || Wallet
const getMethodLabel = (slug) => ({ cardnet: 'Tarjeta de credito', bank_transfer: 'Transferencia Bancaria', paypal: 'PayPal' })[slug] || slug

// ── Change Payment Method Logic ─────────────────────────────────────────────
const openPaymentMethodModal = () => {
    showPaymentMethodModal.value = true
    selectedPaymentMethod.value = null
    cardState.value = 'idle'
    cardError.value = ''
    paypalError.value = null
}

const selectPaymentMethodTab = (slug) => {
    selectedPaymentMethod.value = slug
    if (slug === 'cardnet' && !scriptLoaded.value && props.publicKey) {
        loadCheckoutScript()
    }
}

// Cardnet PWCheckout
const loadCheckoutScript = () => {
    if (cardState.value !== 'idle') return
    cardState.value = 'loading_script'
    const script = document.createElement('script')
    script.src = `${props.checkoutScriptBase}/Scripts/PWCheckout.js?key=${props.publicKey}`
    script.async = true
    script.onload = initCheckout
    script.onerror = () => {
        cardState.value = 'error'
        cardError.value = 'No se pudo cargar el modulo de pago.'
    }
    document.head.appendChild(script)
}

const initCheckout = () => {
    if (!window.PWCheckout) {
        cardState.value = 'error'
        cardError.value = 'Error al inicializar el modulo de pago.'
        return
    }
    window.PWCheckout.SetProperties({
        name: 'WaOrder',
        button_label: 'Guardar tarjeta',
        currency: plan.value?.currency || 'DOP',
        amount: '0.00',
        form_id: 'billing-cardnet-form',
        lang: 'ESP',
    })
    window.PWCheckout.AddActionButton('billing-cardnet-pay-btn')
    window.PWCheckout.Bind('tokenCreated', handleTokenCreated)
    window.PWCheckout.Bind('tokenError', () => {
        cardState.value = 'error'
        cardError.value = 'No se pudo procesar la tarjeta. Verifica los datos e intenta de nuevo.'
    })
    scriptLoaded.value = true
    cardState.value = 'ready'
}

const handleTokenCreated = async (tokenObj) => {
    cardState.value = 'processing'
    cardError.value = ''
    try {
        const response = await axios.post('/billing/payment-method/tokenize', {
            token_id: tokenObj.TokenId,
            brand: tokenObj.Brand,
            last4: tokenObj.Last4,
            expiry_month: tokenObj.CardExpMonth,
            expiry_year: tokenObj.CardExpYear,
        })
        if (response.data.success) {
            cardState.value = 'success'
            showPaymentMethodModal.value = false
            router.reload()
        }
    } catch (err) {
        cardState.value = 'error'
        cardError.value = err.response?.data?.message || 'Error al guardar la tarjeta.'
    }
}

// Bank transfer switch
const switchToBankTransfer = () => {
    bankTransferLoading.value = true
    router.post('/billing/payment-method/bank-transfer', {}, {
        preserveScroll: true,
        onSuccess: () => { showPaymentMethodModal.value = false },
        onFinish: () => { bankTransferLoading.value = false },
    })
}

// PayPal switch
const switchToPayPal = async () => {
    paypalLoading.value = true
    paypalError.value = null
    try {
        const response = await fetch('/billing/payment-method/paypal', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            },
        })
        const data = await response.json()
        if (data.approval_url) {
            openPayPalPopup(data.approval_url)
        } else {
            paypalError.value = data.error || 'Error al conectar con PayPal'
            paypalLoading.value = false
        }
    } catch (error) {
        paypalError.value = 'Error de conexion. Intenta de nuevo.'
        paypalLoading.value = false
    }
}
</script>

<template>
    <Head title="Facturacion" />

    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Facturacion</h1>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left column -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Current Plan -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Plan Actual</h2>
                        <span :class="['px-2.5 py-1 text-xs font-medium rounded-full', statusBadge.class]">
                            {{ statusBadge.label }}
                        </span>
                    </div>
                    <div v-if="plan" class="space-y-3">
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-bold text-gray-900">{{ plan.name }}</span>
                            <span class="text-lg text-gray-500">{{ formatPrice(subscription?.price) }}/{{ subscription?.billing_period === 'annual' ? 'anual' : 'mes' }}</span>
                        </div>
                        <div v-if="subscription?.status === 'trialing'" class="flex items-center gap-2 text-sm text-blue-600">
                            <Clock class="w-4 h-4" />
                            Prueba hasta {{ formatDate(subscription.trial_ends_at) }}
                        </div>
                        <div v-else-if="subscription?.current_period_end" class="text-sm text-gray-500">
                            Proximo cobro: {{ formatDate(subscription.current_period_end) }}
                        </div>
                        <div class="flex gap-2 mt-4">
                            <button @click="showChangePlanModal = true"
                                class="px-4 py-2 text-sm font-medium text-[#0052FF] bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                Cambiar Plan
                            </button>
                            <button v-if="subscription?.status === 'cancelled'" @click="reactivate"
                                class="px-4 py-2 text-sm font-medium text-green-600 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                                Reactivar
                            </button>
                            <button v-else-if="subscription?.isActive || subscription?.status === 'active' || subscription?.status === 'trialing'"
                                @click="showCancelModal = true"
                                class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                                Cancelar
                            </button>
                        </div>
                    </div>
                    <div v-else class="text-gray-500">No tienes un plan activo.</div>
                </div>

                <!-- Usage -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Uso del Plan</h2>
                    <div class="space-y-4">
                        <div v-for="item in [
                            { label: 'Sucursales', icon: Store, current: usage.branches, max: limits.max_branches },
                            { label: 'Items de Menu', icon: UtensilsCrossed, current: usage.menu_items, max: limits.max_menu_items },
                            { label: 'Mensajeros', icon: Truck, current: usage.drivers, max: limits.max_drivers },
                            { label: 'Usuarios', icon: Users, current: usage.users, max: limits.max_users },
                            { label: 'Ordenes este mes', icon: ShoppingBag, current: usage.orders_this_month, max: limits.max_orders_per_month },
                        ]" :key="item.label">
                            <div class="flex items-center justify-between text-sm mb-1">
                                <div class="flex items-center gap-2 text-gray-600">
                                    <component :is="item.icon" class="w-4 h-4 text-gray-400" />
                                    {{ item.label }}
                                </div>
                                <span class="font-medium text-gray-900">{{ item.current }} / {{ item.max ?? '∞' }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div :class="[
                                    'h-2 rounded-full transition-all',
                                    usagePercent(item.current, item.max) >= 90 ? 'bg-red-500' :
                                    usagePercent(item.current, item.max) >= 70 ? 'bg-amber-500' : 'bg-[#0052FF]'
                                ]" :style="{ width: usagePercent(item.current, item.max) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Addons -->
                <div v-if="availableAddons.length" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Addons</h2>
                    <div class="space-y-3">
                        <div v-for="addon in availableAddons" :key="addon.type"
                            :class="[
                                'flex items-center justify-between p-4 rounded-xl border transition-all',
                                addon.isActive ? 'border-[#0052FF]/30 bg-blue-50/50' : 'border-gray-200 bg-white'
                            ]">
                            <div class="flex items-center gap-3 min-w-0">
                                <div :class="[
                                    'w-10 h-10 rounded-lg flex items-center justify-center shrink-0',
                                    addon.isActive ? 'bg-[#0052FF]/10' : 'bg-gray-100'
                                ]">
                                    <component :is="addon.icon" :class="['w-5 h-5', addon.isActive ? 'text-[#0052FF]' : 'text-gray-400']" />
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-gray-900">{{ addon.label }}</span>
                                        <span v-if="addon.isActive" class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-green-100 text-green-700">ACTIVO</span>
                                    </div>
                                    <p class="text-xs text-gray-500 truncate">{{ addon.description }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 shrink-0 ml-3">
                                <span class="text-sm font-bold text-gray-900">{{ formatPrice(addon.price) }}/mes</span>
                                <button @click="toggleAddon(addon.type, addon.isActive)"
                                    :disabled="togglingAddon === addon.type || !subscription?.status || !['active', 'trialing'].includes(subscription.status)"
                                    :class="[
                                        'px-3 py-1.5 text-xs font-medium rounded-lg transition-all whitespace-nowrap',
                                        addon.isActive
                                            ? 'text-red-600 bg-red-50 hover:bg-red-100'
                                            : 'text-white bg-[#0052FF] hover:bg-[#0047DB]',
                                        (togglingAddon === addon.type) && 'opacity-60 cursor-wait'
                                    ]">
                                    <Loader2 v-if="togglingAddon === addon.type" class="w-3.5 h-3.5 animate-spin" />
                                    <template v-else>{{ addon.isActive ? 'Desactivar' : 'Activar' }}</template>
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-if="addonNote" class="mt-3 text-xs text-gray-400">{{ addonNote }}</p>
                </div>

                <!-- Invoice History -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Historial de Facturacion</h2>
                    <div v-if="invoices.length" class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100">
                                    <th class="text-left py-2 text-gray-500 font-medium">Fecha</th>
                                    <th class="text-left py-2 text-gray-500 font-medium">Descripcion</th>
                                    <th class="text-right py-2 text-gray-500 font-medium">Monto</th>
                                    <th class="text-right py-2 text-gray-500 font-medium">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="invoice in invoices" :key="invoice.id" class="border-b border-gray-50">
                                    <td class="py-3 text-gray-600">{{ formatDate(invoice.created_at) }}</td>
                                    <td class="py-3 text-gray-900">{{ invoice.description || invoice.type }}</td>
                                    <td class="py-3 text-right font-medium text-gray-900">{{ formatPrice(invoice.total) }}</td>
                                    <td class="py-3 text-right">
                                        <span :class="['px-2 py-0.5 text-xs font-medium rounded-full', invoiceStatusBadge(invoice.status).class]">
                                            {{ invoiceStatusBadge(invoice.status).label }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-gray-500">No hay facturas aun.</p>
                </div>
            </div>

            <!-- Right column -->
            <div class="space-y-6">
                <!-- Payment Method -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Metodo de Pago</h2>
                        <button v-if="availablePaymentMethods.length > 1"
                            @click="openPaymentMethodModal"
                            class="flex items-center gap-1 text-xs font-medium text-[#0052FF] hover:text-[#0047DB] transition-colors">
                            <Pencil class="w-3.5 h-3.5" />
                            Cambiar
                        </button>
                    </div>

                    <!-- Card (Cardnet) -->
                    <div v-if="paymentMethod?.type === 'cardnet' || (paymentMethod && !paymentMethod.type)" class="flex items-center gap-3">
                        <div class="w-12 h-8 bg-gray-100 rounded flex items-center justify-center">
                            <CreditCard class="w-5 h-5 text-gray-500" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                {{ paymentMethod.brand || 'Tarjeta' }} **** {{ paymentMethod.last_four }}
                            </p>
                            <p v-if="paymentMethod.expiry" class="text-xs text-gray-500">Exp. {{ paymentMethod.expiry }}</p>
                        </div>
                    </div>

                    <!-- PayPal -->
                    <div v-else-if="paymentMethod?.type === 'paypal'" class="flex items-center gap-3">
                        <div class="w-12 h-8 bg-blue-50 rounded flex items-center justify-center">
                            <Wallet class="w-5 h-5 text-[#0070ba]" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">PayPal</p>
                            <p v-if="paymentMethod.paypal_subscription_id" class="text-xs text-gray-500">
                                ID: {{ paymentMethod.paypal_subscription_id }}
                            </p>
                        </div>
                    </div>

                    <!-- Bank Transfer -->
                    <div v-else-if="paymentMethod?.type === 'bank_transfer'" class="flex items-center gap-3">
                        <div class="w-12 h-8 bg-gray-100 rounded flex items-center justify-center">
                            <Building2 class="w-5 h-5 text-gray-500" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Transferencia Bancaria</p>
                            <p class="text-xs text-gray-500">Pago verificado manualmente</p>
                        </div>
                    </div>

                    <!-- No payment method -->
                    <div v-else class="text-sm text-gray-500">
                        <p>No hay metodo de pago registrado.</p>
                        <button v-if="availablePaymentMethods.length"
                            @click="openPaymentMethodModal"
                            class="mt-2 text-xs font-medium text-[#0052FF] hover:underline">
                            Configurar metodo de pago
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ CHANGE PAYMENT METHOD MODAL ═══ -->
        <div v-if="showPaymentMethodModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="showPaymentMethodModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 max-h-[85vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-gray-900">Cambiar Metodo de Pago</h3>
                    <button @click="showPaymentMethodModal = false" class="p-1 text-gray-400 hover:text-gray-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Method tabs -->
                <div class="flex rounded-xl border border-gray-200 p-1 mb-5 gap-1">
                    <button
                        v-for="method in availablePaymentMethods"
                        :key="method.slug"
                        @click="selectPaymentMethodTab(method.slug)"
                        :class="['flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-sm font-medium transition',
                                 selectedPaymentMethod === method.slug ? 'bg-[#0052FF] text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50']"
                    >
                        <component :is="getMethodIcon(method.slug)" class="w-4 h-4" />
                        {{ getMethodLabel(method.slug) }}
                    </button>
                </div>

                <!-- Cardnet -->
                <template v-if="selectedPaymentMethod === 'cardnet'">
                    <div v-if="cardError" class="mb-4 p-3 bg-red-50 border border-red-100 rounded-xl flex items-start gap-2">
                        <AlertTriangle class="w-4 h-4 text-red-500 mt-0.5 shrink-0" />
                        <p class="text-sm text-red-700">{{ cardError }}</p>
                    </div>

                    <form id="billing-cardnet-form">
                        <input type="hidden" name="PWToken" id="PWToken" />
                    </form>

                    <div v-if="cardState === 'loading_script'" class="flex items-center justify-center py-8 gap-2 text-gray-500">
                        <Loader2 class="w-5 h-5 animate-spin" />
                        <span class="text-sm">Cargando modulo de pago...</span>
                    </div>
                    <div v-else-if="cardState === 'processing'" class="flex items-center justify-center py-8 gap-2 text-[#0052FF]">
                        <Loader2 class="w-5 h-5 animate-spin" />
                        <span class="text-sm font-medium">Guardando tarjeta...</span>
                    </div>
                    <div v-else-if="cardState === 'ready'">
                        <button id="billing-cardnet-pay-btn" type="button"
                            class="w-full py-3 px-6 bg-[#0052FF] text-white font-semibold rounded-xl hover:bg-[#0047DB] transition-all text-sm shadow-lg shadow-blue-200 flex items-center justify-center gap-2">
                            <CreditCard class="w-4 h-4" />
                            Ingresar datos de tarjeta
                        </button>
                    </div>
                    <div v-else class="flex items-center justify-center py-8 gap-2 text-gray-500">
                        <Loader2 class="w-5 h-5 animate-spin" />
                        <span class="text-sm">Inicializando...</span>
                    </div>

                    <div class="mt-4 flex items-center justify-center gap-1.5 text-gray-400">
                        <ShieldCheck class="w-4 h-4" />
                        <p class="text-xs">Pago seguro procesado por Cardnet</p>
                    </div>
                </template>

                <!-- Bank Transfer -->
                <template v-else-if="selectedPaymentMethod === 'bank_transfer'">
                    <div class="text-center py-4">
                        <div class="w-14 h-14 bg-gray-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                            <Building2 class="w-7 h-7 text-gray-500" />
                        </div>
                        <h4 class="font-semibold text-gray-900">Transferencia Bancaria</h4>
                        <p class="text-sm text-gray-500 mt-1">Los pagos futuros seran via transferencia bancaria con verificacion manual.</p>
                    </div>
                    <button @click="switchToBankTransfer" :disabled="bankTransferLoading"
                        class="w-full py-3 px-6 bg-[#0052FF] text-white font-semibold rounded-xl hover:bg-[#0047DB] transition-all text-sm flex items-center justify-center gap-2 disabled:opacity-50">
                        <Loader2 v-if="bankTransferLoading" class="w-4 h-4 animate-spin" />
                        <Check v-else class="w-4 h-4" />
                        {{ bankTransferLoading ? 'Cambiando...' : 'Cambiar a Transferencia' }}
                    </button>
                </template>

                <!-- PayPal -->
                <template v-else-if="selectedPaymentMethod === 'paypal'">
                    <div class="text-center py-4">
                        <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                            <Wallet class="w-7 h-7 text-blue-600" />
                        </div>
                        <h4 class="font-semibold text-gray-900">PayPal</h4>
                        <p class="text-sm text-gray-500 mt-1">Seras redirigido a PayPal para vincular tu cuenta.</p>
                    </div>
                    <button @click="switchToPayPal" :disabled="paypalLoading"
                        class="w-full py-3 px-4 bg-[#0070ba] hover:bg-[#003087] text-white font-semibold rounded-xl transition-colors flex items-center justify-center gap-2 disabled:opacity-50">
                        <span v-if="paypalLoading" class="animate-spin w-5 h-5 border-2 border-white/30 border-t-white rounded-full"></span>
                        <span v-else>Vincular PayPal</span>
                    </button>
                    <p v-if="paypalError" class="text-sm text-red-600 text-center mt-3">{{ paypalError }}</p>
                </template>

                <!-- No selection yet -->
                <div v-else class="text-center py-8 text-sm text-gray-400">
                    Selecciona un metodo de pago arriba
                </div>
            </div>
        </div>

        <!-- ═══ CHANGE PLAN MODAL ═══ -->
        <div v-if="showChangePlanModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="showChangePlanModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 max-h-[80vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Cambiar Plan</h3>
                    <button @click="showChangePlanModal = false" class="p-1 text-gray-400 hover:text-gray-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <div class="space-y-3">
                    <label v-for="p in plans" :key="p.id"
                        :class="[
                            'block border rounded-xl p-4 cursor-pointer transition-all',
                            selectedPlanId === p.id ? 'border-[#0052FF] bg-blue-50' : 'border-gray-200 hover:border-gray-300',
                            subscription?.plan?.id === p.id ? 'ring-2 ring-green-200' : ''
                        ]">
                        <input type="radio" v-model="selectedPlanId" :value="p.id" class="sr-only" />
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-semibold text-gray-900">{{ p.name }}</span>
                                <span v-if="subscription?.plan?.id === p.id" class="ml-2 text-xs text-green-600">(actual)</span>
                            </div>
                            <span class="font-bold text-gray-900">{{ formatPrice(p.price_monthly) }}/mes</span>
                        </div>
                    </label>
                </div>
                <p v-if="isPayPal" class="mt-4 text-xs text-gray-400">
                    Si el nuevo plan tiene un precio mayor, seras redirigido a PayPal para aprobar el cambio.
                </p>
                <div class="mt-4 flex justify-end gap-3">
                    <button @click="showChangePlanModal = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
                    <button @click="changePlan" :disabled="!selectedPlanId || selectedPlanId === subscription?.plan?.id || changingPlan"
                        :class="[
                            'px-6 py-2 text-sm font-medium text-white bg-[#0052FF] rounded-lg hover:bg-[#0047DB] disabled:opacity-40 transition-colors',
                            changingPlan && 'cursor-wait'
                        ]">
                        <Loader2 v-if="changingPlan" class="w-4 h-4 animate-spin inline mr-1" />
                        {{ changingPlan ? 'Procesando...' : 'Confirmar Cambio' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══ CANCEL MODAL ═══ -->
        <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="showCancelModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                        <AlertTriangle class="w-5 h-5 text-red-600" />
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Cancelar Suscripcion</h3>
                </div>
                <p class="text-sm text-gray-600 mb-4">
                    Tu cuenta seguira activa hasta el {{ formatDate(subscription?.current_period_end) }}. No se realizaran mas cobros.
                </p>
                <textarea v-model="cancelReason" rows="2" placeholder="Razon de cancelacion (opcional)"
                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 mb-4"></textarea>
                <div class="flex justify-end gap-3">
                    <button @click="showCancelModal = false" class="px-4 py-2 text-sm text-gray-600">No cancelar</button>
                    <button @click="cancelSubscription" :disabled="cancelling"
                        :class="[
                            'px-6 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors',
                            cancelling && 'opacity-60 cursor-wait'
                        ]">
                        <Loader2 v-if="cancelling" class="w-4 h-4 animate-spin inline mr-1" />
                        {{ cancelling ? 'Cancelando...' : 'Confirmar Cancelacion' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
