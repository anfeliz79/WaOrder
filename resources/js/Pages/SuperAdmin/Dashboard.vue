<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import {
    Store, UserCheck, Users, ShoppingCart, UsersRound,
    ArrowUpRight, ExternalLink
} from 'lucide-vue-next'

const props = defineProps({
    stats: { type: Object, required: true }
})

const statCards = computed(() => [
    {
        label: 'Total Restaurantes',
        value: props.stats.total_tenants,
        icon: Store,
        color: 'amber',
        bgClass: 'bg-amber-50',
        iconBgClass: 'bg-amber-100',
        iconClass: 'text-amber-600',
        valueClass: 'text-amber-700',
    },
    {
        label: 'Restaurantes Activos',
        value: props.stats.active_tenants,
        icon: UserCheck,
        color: 'emerald',
        bgClass: 'bg-emerald-50',
        iconBgClass: 'bg-emerald-100',
        iconClass: 'text-emerald-600',
        valueClass: 'text-emerald-700',
    },
    {
        label: 'Total Usuarios',
        value: props.stats.total_users,
        icon: Users,
        color: 'indigo',
        bgClass: 'bg-indigo-50',
        iconBgClass: 'bg-indigo-100',
        iconClass: 'text-indigo-600',
        valueClass: 'text-indigo-700',
    },
    {
        label: 'Total Pedidos',
        value: props.stats.total_orders,
        icon: ShoppingCart,
        color: 'blue',
        bgClass: 'bg-blue-50',
        iconBgClass: 'bg-blue-100',
        iconClass: 'text-blue-600',
        valueClass: 'text-blue-700',
    },
    {
        label: 'Total Clientes',
        value: props.stats.total_customers,
        icon: UsersRound,
        color: 'purple',
        bgClass: 'bg-purple-50',
        iconBgClass: 'bg-purple-100',
        iconClass: 'text-purple-600',
        valueClass: 'text-purple-700',
    },
])

const orderStatusMap = {
    confirmed: { label: 'Confirmado', class: 'bg-blue-100 text-blue-800' },
    in_preparation: { label: 'En Preparación', class: 'bg-yellow-100 text-yellow-800' },
    ready: { label: 'Listo', class: 'bg-purple-100 text-purple-800' },
    out_for_delivery: { label: 'En Camino', class: 'bg-orange-100 text-orange-800' },
    delivered: { label: 'Entregado', class: 'bg-green-100 text-green-800' },
    cancelled: { label: 'Cancelado', class: 'bg-red-100 text-red-800' },
}

const planMap = {
    free: { label: 'Free', class: 'bg-gray-100 text-gray-700' },
    starter: { label: 'Starter', class: 'bg-blue-100 text-blue-700' },
    pro: { label: 'Pro', class: 'bg-amber-100 text-amber-700' },
}

const formatDate = (dateStr) => {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString('es-DO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const formatCurrency = (amount) => {
    if (amount == null) return 'RD$ 0.00'
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'DOP',
    }).format(amount)
}

const getOrderStatus = (status) => orderStatusMap[status] || { label: status, class: 'bg-gray-100 text-gray-700' }
const getPlan = (plan) => planMap[plan] || { label: plan || 'N/A', class: 'bg-gray-100 text-gray-700' }
</script>

<template>
    <SuperAdminLayout title="Dashboard">
        <!-- Stats cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            <div
                v-for="card in statCards"
                :key="card.label"
                class="bg-white rounded-xl border border-gray-200 p-5"
            >
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-medium text-gray-500">{{ card.label }}</span>
                    <div :class="[card.iconBgClass, 'w-9 h-9 rounded-lg flex items-center justify-center']">
                        <component :is="card.icon" :class="[card.iconClass, 'w-5 h-5']" />
                    </div>
                </div>
                <div :class="[card.valueClass, 'text-3xl font-bold']">
                    {{ card.value?.toLocaleString('es-DO') ?? 0 }}
                </div>
            </div>
        </div>

        <!-- Recent Tenants -->
        <div class="bg-white rounded-xl border border-gray-200 mb-8">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">Restaurantes Recientes</h2>
                <Link
                    href="/superadmin/tenants"
                    class="text-sm text-amber-600 hover:text-amber-700 font-medium flex items-center gap-1"
                >
                    Ver todos
                    <ArrowUpRight class="w-4 h-4" />
                </Link>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Creado</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr
                            v-for="tenant in stats.recent_tenants"
                            :key="tenant.id"
                            class="hover:bg-gray-50 transition-colors"
                        >
                            <td class="px-6 py-3 font-medium text-gray-900">{{ tenant.name }}</td>
                            <td class="px-6 py-3 text-gray-500 font-mono text-xs">{{ tenant.slug }}</td>
                            <td class="px-6 py-3">
                                <span :class="[getPlan(tenant.subscription_plan).class, 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium']">
                                    {{ getPlan(tenant.subscription_plan).label }}
                                </span>
                            </td>
                            <td class="px-6 py-3">
                                <span
                                    :class="[
                                        tenant.is_active
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-red-100 text-red-700',
                                        'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium'
                                    ]"
                                >
                                    {{ tenant.is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-gray-500">{{ formatDate(tenant.created_at) }}</td>
                            <td class="px-6 py-3">
                                <Link
                                    :href="`/superadmin/tenants/${tenant.id}/edit`"
                                    class="text-amber-600 hover:text-amber-700 font-medium inline-flex items-center gap-1"
                                >
                                    Editar
                                    <ExternalLink class="w-3.5 h-3.5" />
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!stats.recent_tenants?.length">
                            <td colspan="6" class="px-6 py-8 text-center text-gray-400">
                                No hay restaurantes registrados aún.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-lg font-semibold text-gray-800">Últimos Pedidos</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Restaurante</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr
                            v-for="order in stats.recent_orders"
                            :key="order.id"
                            class="hover:bg-gray-50 transition-colors"
                        >
                            <td class="px-6 py-3 font-mono text-xs text-gray-700">#{{ order.id }}</td>
                            <td class="px-6 py-3 font-medium text-gray-900">{{ order.tenant?.name ?? '—' }}</td>
                            <td class="px-6 py-3">
                                <span :class="[getOrderStatus(order.status).class, 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium']">
                                    {{ getOrderStatus(order.status).label }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-gray-700 font-medium">{{ formatCurrency(order.total) }}</td>
                            <td class="px-6 py-3 text-gray-500">{{ formatDate(order.created_at) }}</td>
                        </tr>
                        <tr v-if="!stats.recent_orders?.length">
                            <td colspan="5" class="px-6 py-8 text-center text-gray-400">
                                No hay pedidos registrados aún.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SuperAdminLayout>
</template>
