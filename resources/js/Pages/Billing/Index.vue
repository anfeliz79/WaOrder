<script setup>
import { ref, computed } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import {
    CreditCard, ArrowUpRight, X, Check, AlertTriangle, Clock,
    Store, UtensilsCrossed, Truck, Users, ShoppingBag
} from 'lucide-vue-next'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    subscription: Object,
    paymentMethod: Object,
    invoices: Array,
    plans: Array,
    usage: Object,
})

const page = usePage()
const showChangePlanModal = ref(false)
const showCancelModal = ref(false)
const cancelReason = ref('')
const selectedPlanId = ref(null)

const plan = computed(() => props.subscription?.plan)
const limits = computed(() => plan.value || {})

const statusBadge = computed(() => {
    const map = {
        active: { label: 'Activo', class: 'bg-green-100 text-green-700' },
        trialing: { label: 'Prueba', class: 'bg-blue-100 text-blue-700' },
        past_due: { label: 'Pago pendiente', class: 'bg-amber-100 text-amber-700' },
        cancelled: { label: 'Cancelado', class: 'bg-red-100 text-red-700' },
        suspended: { label: 'Suspendido', class: 'bg-red-100 text-red-700' },
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

const changePlan = () => {
    if (!selectedPlanId.value) return
    router.post('/billing/change-plan', { plan_id: selectedPlanId.value }, {
        preserveScroll: true,
        onSuccess: () => { showChangePlanModal.value = false },
    })
}

const cancelSubscription = () => {
    router.post('/billing/cancel', { reason: cancelReason.value }, {
        preserveScroll: true,
        onSuccess: () => { showCancelModal.value = false; cancelReason.value = '' },
    })
}

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
                                class="px-4 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
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
                                    usagePercent(item.current, item.max) >= 70 ? 'bg-amber-500' : 'bg-indigo-500'
                                ]" :style="{ width: usagePercent(item.current, item.max) + '%' }"></div>
                            </div>
                        </div>
                    </div>
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
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Metodo de Pago</h2>
                    <div v-if="paymentMethod" class="flex items-center gap-3">
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
                    <div v-else class="text-sm text-gray-500">
                        No hay metodo de pago registrado.
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Plan Modal -->
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
                            selectedPlanId === p.id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300',
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
                <div class="mt-6 flex justify-end gap-3">
                    <button @click="showChangePlanModal = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
                    <button @click="changePlan" :disabled="!selectedPlanId || selectedPlanId === subscription?.plan?.id"
                        class="px-6 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-40 transition-colors">
                        Confirmar Cambio
                    </button>
                </div>
            </div>
        </div>

        <!-- Cancel Modal -->
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
                    <button @click="cancelSubscription"
                        class="px-6 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                        Confirmar Cancelacion
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
