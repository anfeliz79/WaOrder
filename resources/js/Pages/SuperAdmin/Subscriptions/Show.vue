<script setup>
import { ref, computed } from 'vue'
import { Link, Head, router, useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import {
    ArrowLeft, Receipt, Calendar, CreditCard, Clock,
    AlertTriangle, CheckCircle, XCircle, FileText, Save, RefreshCw
} from 'lucide-vue-next'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    subscription: Object,
    plans: Array,
})

const sub = computed(() => props.subscription)

// ── Change Plan Modal ──
const showChangePlan = ref(false)
const changePlanForm = useForm({
    plan_id: sub.value.plan_id,
    billing_period: sub.value.billing_period || 'monthly',
})

const selectedPlan = computed(() => {
    return props.plans.find(p => p.id === changePlanForm.plan_id)
})

const newPrice = computed(() => {
    if (!selectedPlan.value) return 0
    return changePlanForm.billing_period === 'annual'
        ? selectedPlan.value.price_annual
        : selectedPlan.value.price_monthly
})

const submitChangePlan = () => {
    changePlanForm.post(`/superadmin/subscriptions/${sub.value.id}/change-plan`, {
        preserveScroll: true,
        onSuccess: () => { showChangePlan.value = false },
    })
}

// ── Admin Notes ──
const notesForm = useForm({
    admin_notes: sub.value.admin_notes || '',
})

const saveNotes = () => {
    notesForm.put(`/superadmin/subscriptions/${sub.value.id}/notes`, {
        preserveScroll: true,
    })
}

// ── Actions ──
const extend = () => {
    router.post(`/superadmin/subscriptions/${sub.value.id}/extend`, {}, { preserveScroll: true })
}

const cancel = () => {
    if (window.confirm(`¿Cancelar suscripción de "${sub.value.tenant?.name}"?`)) {
        router.post(`/superadmin/subscriptions/${sub.value.id}/cancel`, {}, { preserveScroll: true })
    }
}

const reactivate = () => {
    router.post(`/superadmin/subscriptions/${sub.value.id}/reactivate`, {}, { preserveScroll: true })
}

// ── Helpers ──
const statusBadgeClass = (status) => {
    const map = {
        active: 'bg-green-100 text-green-700',
        trialing: 'bg-blue-100 text-blue-700',
        pending_payment: 'bg-yellow-100 text-yellow-700',
        past_due: 'bg-red-100 text-red-700',
        cancelled: 'bg-gray-100 text-gray-600',
        suspended: 'bg-orange-100 text-orange-700',
        expired: 'bg-gray-200 text-gray-500',
    }
    return map[status] || 'bg-gray-100 text-gray-600'
}

const statusLabel = (status) => {
    const map = {
        active: 'Activa',
        trialing: 'Prueba',
        pending_payment: 'Pago pendiente',
        past_due: 'Vencida',
        cancelled: 'Cancelada',
        suspended: 'Suspendida',
        expired: 'Expirada',
    }
    return map[status] || status
}

const canReactivate = computed(() => sub.value.status === 'cancelled' || sub.value.status === 'suspended')

const formatPrice = (price, currency) => {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: currency || 'DOP',
        minimumFractionDigits: 2,
    }).format(price || 0)
}

const formatDate = (date) => {
    if (!date) return '—'
    return new Intl.DateTimeFormat('es-DO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(date))
}

const formatDateTime = (date) => {
    if (!date) return '—'
    return new Intl.DateTimeFormat('es-DO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date))
}

const billingPeriodLabel = (period) => period === 'annual' ? 'Anual' : 'Mensual'

const daysRemaining = computed(() => {
    if (!sub.value.current_period_end) return null
    return Math.ceil((new Date(sub.value.current_period_end) - new Date()) / (1000 * 60 * 60 * 24))
})

const invoiceStatusClass = (status) => {
    const map = {
        paid: 'bg-green-100 text-green-700',
        pending: 'bg-yellow-100 text-yellow-700',
        failed: 'bg-red-100 text-red-700',
        refunded: 'bg-gray-100 text-gray-600',
    }
    return map[status] || 'bg-gray-100 text-gray-600'
}

const invoiceStatusLabel = (status) => {
    const map = { paid: 'Pagada', pending: 'Pendiente', failed: 'Fallida', refunded: 'Reembolsada' }
    return map[status] || status
}
</script>

<template>
    <Head :title="`Suscripción — ${sub.tenant?.name}`" />

    <div>
        <!-- Back + header -->
        <div class="mb-6">
            <Link href="/superadmin/subscriptions" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors mb-3">
                <ArrowLeft class="w-4 h-4" />
                Volver a suscripciones
            </Link>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ sub.tenant?.name || '—' }}</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Suscripción #{{ sub.id }}</p>
                </div>
                <span
                    class="inline-flex items-center self-start px-3 py-1 rounded-full text-sm font-medium"
                    :class="statusBadgeClass(sub.status)"
                >
                    {{ statusLabel(sub.status) }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left column -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Subscription Info Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                            <Receipt class="w-4 h-4 text-[#0052FF]" />
                            Detalles de suscripción
                        </h2>
                    </div>
                    <div class="p-6">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Plan actual</dt>
                                <dd class="mt-1 text-sm font-medium text-gray-900">{{ sub.plan?.name || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Período de facturación</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ billingPeriodLabel(sub.billing_period) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Precio</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ formatPrice(sub.price, sub.plan?.currency) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Creada</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ formatDateTime(sub.created_at) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Period Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                            <Calendar class="w-4 h-4 text-[#0052FF]" />
                            Período actual
                        </h2>
                    </div>
                    <div class="p-6">
                        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-4">
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Inicio</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ formatDate(sub.current_period_start) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Fin</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ formatDate(sub.current_period_end) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Días restantes</dt>
                                <dd class="mt-1">
                                    <span v-if="daysRemaining !== null"
                                          class="text-sm font-medium"
                                          :class="daysRemaining <= 3 ? 'text-red-600' : daysRemaining <= 7 ? 'text-amber-600' : 'text-green-600'">
                                        {{ daysRemaining > 0 ? daysRemaining : 0 }} días
                                    </span>
                                    <span v-else class="text-sm text-gray-400">—</span>
                                </dd>
                            </div>
                        </dl>

                        <!-- Trial info -->
                        <div v-if="sub.trial_ends_at" class="mt-4 p-3 bg-blue-50 rounded-lg">
                            <p class="text-sm text-blue-700">
                                <Clock class="w-4 h-4 inline mr-1" />
                                Prueba gratuita hasta {{ formatDate(sub.trial_ends_at) }}
                            </p>
                        </div>

                        <!-- Grace period info -->
                        <div v-if="sub.grace_period_ends_at" class="mt-4 p-3 bg-amber-50 rounded-lg">
                            <p class="text-sm text-amber-700">
                                <AlertTriangle class="w-4 h-4 inline mr-1" />
                                Período de gracia hasta {{ formatDate(sub.grace_period_ends_at) }}
                            </p>
                        </div>

                        <!-- Cancelled info -->
                        <div v-if="sub.cancelled_at" class="mt-4 p-3 bg-red-50 rounded-lg">
                            <p class="text-sm text-red-700">
                                <XCircle class="w-4 h-4 inline mr-1" />
                                Cancelada el {{ formatDateTime(sub.cancelled_at) }}
                                <span v-if="sub.cancellation_reason" class="block mt-1 text-red-600">
                                    Razón: {{ sub.cancellation_reason }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Invoices Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                            <FileText class="w-4 h-4 text-[#0052FF]" />
                            Historial de pagos
                        </h2>
                    </div>
                    <div v-if="sub.invoices?.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-6 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Fecha</th>
                                    <th class="px-6 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Descripción</th>
                                    <th class="px-6 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Monto</th>
                                    <th class="px-6 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="inv in sub.invoices" :key="inv.id" class="hover:bg-gray-50/50">
                                    <td class="px-6 py-3 text-sm text-gray-600">{{ formatDate(inv.paid_at || inv.created_at) }}</td>
                                    <td class="px-6 py-3 text-sm text-gray-700">{{ inv.description || inv.type || '—' }}</td>
                                    <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ formatPrice(inv.total, inv.currency) }}</td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="invoiceStatusClass(inv.status)">
                                            {{ invoiceStatusLabel(inv.status) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="p-8 text-center">
                        <FileText class="w-8 h-8 text-gray-300 mx-auto mb-2" />
                        <p class="text-sm text-gray-500">No hay pagos registrados</p>
                    </div>
                </div>
            </div>

            <!-- Right column — actions + notes -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-base font-semibold text-gray-900">Acciones</h2>
                    </div>
                    <div class="p-4 space-y-2">
                        <button
                            @click="extend"
                            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors"
                        >
                            <Calendar class="w-4 h-4" />
                            Extender +1 mes
                        </button>

                        <button
                            @click="showChangePlan = true"
                            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-purple-700 bg-purple-50 hover:bg-purple-100 transition-colors"
                        >
                            <RefreshCw class="w-4 h-4" />
                            Cambiar plan
                        </button>

                        <button
                            v-if="canReactivate"
                            @click="reactivate"
                            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-green-700 bg-green-50 hover:bg-green-100 transition-colors"
                        >
                            <CheckCircle class="w-4 h-4" />
                            Reactivar
                        </button>

                        <button
                            v-if="sub.status !== 'cancelled' && sub.status !== 'expired'"
                            @click="cancel"
                            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-red-700 bg-red-50 hover:bg-red-100 transition-colors"
                        >
                            <XCircle class="w-4 h-4" />
                            Cancelar suscripción
                        </button>
                    </div>
                </div>

                <!-- Admin Notes -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-base font-semibold text-gray-900">Notas internas</h2>
                    </div>
                    <div class="p-4">
                        <textarea
                            v-model="notesForm.admin_notes"
                            rows="5"
                            placeholder="Notas privadas sobre esta suscripción..."
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none resize-none"
                        />
                        <button
                            @click="saveNotes"
                            :disabled="notesForm.processing"
                            class="mt-2 w-full flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-[#0052FF] hover:bg-[#0041CC] disabled:opacity-50 transition-colors"
                        >
                            <Save class="w-4 h-4" />
                            Guardar notas
                        </button>
                    </div>
                </div>

                <!-- Tenant quick info -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-base font-semibold text-gray-900">Restaurante</h2>
                    </div>
                    <div class="p-4 space-y-3">
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wider">Nombre</p>
                            <p class="text-sm font-medium text-gray-900 mt-0.5">{{ sub.tenant?.name || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wider">Slug</p>
                            <p class="text-sm text-gray-700 mt-0.5">{{ sub.tenant?.slug || '—' }}</p>
                        </div>
                        <Link
                            v-if="sub.tenant?.id"
                            :href="`/superadmin/tenants/${sub.tenant.id}/edit`"
                            class="block text-center px-4 py-2 text-sm font-medium rounded-lg text-[#0052FF] bg-blue-50 hover:bg-blue-100 transition-colors"
                        >
                            Ver restaurante
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Plan Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showChangePlan" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/40" @click="showChangePlan = false" />
                    <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 z-10">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Cambiar plan</h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                                <select
                                    v-model="changePlanForm.plan_id"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
                                >
                                    <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                        {{ plan.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Período</label>
                                <select
                                    v-model="changePlanForm.billing_period"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
                                >
                                    <option value="monthly">Mensual</option>
                                    <option value="annual">Anual</option>
                                </select>
                            </div>

                            <div v-if="selectedPlan" class="p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600">
                                    Nuevo precio: <span class="font-semibold text-gray-900">{{ formatPrice(newPrice, selectedPlan.currency) }}</span>
                                    <span class="text-gray-400"> / {{ changePlanForm.billing_period === 'annual' ? 'año' : 'mes' }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-3 mt-6">
                            <button
                                @click="showChangePlan = false"
                                class="flex-1 px-4 py-2.5 text-sm font-medium rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors"
                            >
                                Cancelar
                            </button>
                            <button
                                @click="submitChangePlan"
                                :disabled="changePlanForm.processing"
                                class="flex-1 px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-[#0052FF] hover:bg-[#0041CC] disabled:opacity-50 transition-colors"
                            >
                                Cambiar plan
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
