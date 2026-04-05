<script setup>
import { ref, watch } from 'vue'
import { Link, Head, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { Receipt, AlertTriangle } from 'lucide-vue-next'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    subscriptions: Object,
    plans: Array,
    filters: Object,
})

const statusFilter = ref(props.filters?.status || '')
const planFilter = ref(props.filters?.plan_id || '')

const applyFilters = () => {
    router.get('/superadmin/subscriptions', {
        status: statusFilter.value,
        plan_id: planFilter.value,
    }, { preserveState: true, replace: true })
}

watch(statusFilter, applyFilters)
watch(planFilter, applyFilters)

const extend = (subscription) => {
    router.post(`/superadmin/subscriptions/${subscription.id}/extend`, {}, { preserveScroll: true })
}

const cancel = (subscription) => {
    if (window.confirm(`¿Estás seguro de cancelar la suscripción de "${subscription.tenant?.name}"? El bot se desactivará.`)) {
        router.post(`/superadmin/subscriptions/${subscription.id}/cancel`, {}, { preserveScroll: true })
    }
}

const reactivate = (subscription) => {
    router.post(`/superadmin/subscriptions/${subscription.id}/reactivate`, {}, { preserveScroll: true })
}

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

const isAlert = (status) => status === 'past_due' || status === 'pending_payment'

const canReactivate = (status) => status === 'cancelled' || status === 'suspended'

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

const billingPeriodLabel = (period) => {
    return period === 'annual' ? 'Anual' : 'Mensual'
}
</script>

<template>
    <Head title="Suscripciones" />

    <div>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Suscripciones</h1>
            <p class="text-sm text-gray-500">
                {{ subscriptions.meta?.total || subscriptions.total || 0 }} suscripciones registradas
            </p>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3 mb-6">
            <select
                v-model="statusFilter"
                class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
            >
                <option value="">Todos los estados</option>
                <option value="active">Activa</option>
                <option value="trialing">Prueba</option>
                <option value="pending_payment">Pago pendiente</option>
                <option value="past_due">Vencida</option>
                <option value="cancelled">Cancelada</option>
                <option value="suspended">Suspendida</option>
                <option value="expired">Expirada</option>
            </select>
            <select
                v-model="planFilter"
                class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
            >
                <option value="">Todos los planes</option>
                <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                    {{ plan.name }}
                </option>
            </select>
        </div>

        <!-- Table -->
        <div v-if="subscriptions.data.length" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Restaurante</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Plan</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Precio</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Período actual</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Creada</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="sub in subscriptions.data"
                            :key="sub.id"
                            class="hover:bg-gray-50/50 transition-colors"
                            :class="isAlert(sub.status) ? 'bg-red-50/30' : ''"
                        >
                            <!-- Restaurante -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <AlertTriangle
                                        v-if="isAlert(sub.status)"
                                        class="w-4 h-4 text-red-500 shrink-0"
                                    />
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ sub.tenant?.name || '—' }}</p>
                                        <p class="text-xs text-gray-400">{{ sub.tenant?.slug || '' }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Plan -->
                            <td class="px-6 py-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ sub.plan?.name || '—' }}</p>
                                    <p class="text-xs text-gray-400">{{ billingPeriodLabel(sub.billing_period) }}</p>
                                </div>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="statusBadgeClass(sub.status)"
                                >
                                    {{ statusLabel(sub.status) }}
                                </span>
                            </td>

                            <!-- Precio -->
                            <td class="px-6 py-4 text-sm text-gray-700">
                                {{ formatPrice(sub.price, sub.plan?.currency) }}
                            </td>

                            <!-- Período actual -->
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <span v-if="sub.current_period_start || sub.current_period_end">
                                    {{ formatDate(sub.current_period_start) }} — {{ formatDate(sub.current_period_end) }}
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </td>

                            <!-- Creada -->
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ formatDate(sub.created_at) }}
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Extender -->
                                    <button
                                        @click="extend(sub)"
                                        class="px-3 py-1.5 text-xs font-medium rounded-lg text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors"
                                        title="Extender +1 mes"
                                    >
                                        +1 mes
                                    </button>

                                    <!-- Reactivar (si está cancelled o suspended) -->
                                    <button
                                        v-if="canReactivate(sub.status)"
                                        @click="reactivate(sub)"
                                        class="px-3 py-1.5 text-xs font-medium rounded-lg text-green-700 bg-green-50 hover:bg-green-100 transition-colors"
                                        title="Reactivar suscripción"
                                    >
                                        Reactivar
                                    </button>

                                    <!-- Cancelar (si no está ya cancelada o expirada) -->
                                    <button
                                        v-if="sub.status !== 'cancelled' && sub.status !== 'expired'"
                                        @click="cancel(sub)"
                                        class="px-3 py-1.5 text-xs font-medium rounded-lg text-red-700 bg-red-50 hover:bg-red-100 transition-colors"
                                        title="Cancelar suscripción"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="subscriptions.links && subscriptions.links.length > 3" class="px-6 py-3 border-t border-gray-200 flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Mostrando {{ subscriptions.meta?.from || subscriptions.from }} a {{ subscriptions.meta?.to || subscriptions.to }} de {{ subscriptions.meta?.total || subscriptions.total }} resultados
                </p>
                <nav class="flex items-center gap-1">
                    <template v-for="(link, i) in subscriptions.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="px-3 py-1.5 text-sm rounded-lg transition-colors"
                            :class="link.active
                                ? 'bg-[#0052FF] text-white font-medium'
                                : 'text-gray-600 hover:bg-gray-100'"
                            v-html="link.label"
                            preserve-state
                        />
                        <span
                            v-else
                            class="px-3 py-1.5 text-sm text-gray-300"
                            v-html="link.label"
                        />
                    </template>
                </nav>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
            <Receipt class="w-12 h-12 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">No hay suscripciones</h3>
            <p class="text-sm text-gray-500">
                {{ filters?.status || filters?.plan_id ? 'No se encontraron suscripciones con los filtros aplicados.' : 'Aún no hay suscripciones registradas en la plataforma.' }}
            </p>
        </div>
    </div>
</template>
