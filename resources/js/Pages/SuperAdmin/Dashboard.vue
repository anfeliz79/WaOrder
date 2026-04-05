<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import {
    Store, UserCheck, Users, ShoppingCart, UsersRound,
    ArrowUpRight, ExternalLink, AlertTriangle, Info, CheckCircle
} from 'lucide-vue-next'

const props = defineProps({
    stats:  { type: Object, required: true },
    alerts: { type: Array,  default: () => [] },
})

const alertConfig = {
    critical: {
        containerClass: 'bg-red-50 border-red-200',
        iconClass: 'text-red-500',
        textClass: 'text-red-800',
        linkClass: 'text-red-700 underline hover:text-red-900',
        icon: AlertTriangle,
    },
    warning: {
        containerClass: 'bg-yellow-50 border-yellow-200',
        iconClass: 'text-yellow-500',
        textClass: 'text-yellow-800',
        linkClass: 'text-yellow-700 underline hover:text-yellow-900',
        icon: AlertTriangle,
    },
    info: {
        containerClass: 'bg-blue-50 border-blue-200',
        iconClass: 'text-blue-500',
        textClass: 'text-blue-800',
        linkClass: 'text-blue-700 underline hover:text-blue-900',
        icon: Info,
    },
}

const getAlertConfig = (level) => alertConfig[level] ?? alertConfig.info

const statCards = computed(() => [
    {
        label: 'Total Restaurantes',
        value: props.stats.total_tenants,
        icon: Store,
        color: 'blue',
        bgClass: 'bg-blue-50',
        iconBgClass: 'bg-blue-100',
        iconClass: 'text-[#0052FF]',
        valueClass: 'text-[#0047DB]',
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
        color: 'blue',
        bgClass: 'bg-blue-50',
        iconBgClass: 'bg-blue-100',
        iconClass: 'text-[#0052FF]',
        valueClass: 'text-[#0047DB]',
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

// Bar chart helpers
const barHeight = (count, maxValue) => {
    if (maxValue === 0) return 1
    const pct = Math.round((count / maxValue) * 100)
    return pct < 1 ? 1 : pct
}

const tenantsMax = computed(() => {
    const months = props.stats.tenants_by_month ?? []
    return Math.max(...months.map(m => m.count), 1)
})

const ordersMax = computed(() => {
    const months = props.stats.orders_by_month ?? []
    return Math.max(...months.map(m => m.count), 1)
})

const planColorMap = {
    free:    { badge: 'bg-gray-100 text-gray-700',    bar: 'bg-gray-400' },
    starter: { badge: 'bg-blue-100 text-blue-700',    bar: 'bg-blue-500' },
    pro:     { badge: 'bg-amber-100 text-amber-700',  bar: 'bg-amber-500' },
}

const getPlanStyle = (slug) => planColorMap[slug] ?? { badge: 'bg-gray-100 text-gray-700', bar: 'bg-gray-400' }
</script>

<template>
    <SuperAdminLayout title="Dashboard">
        <!-- Alerts Centro -->
        <div class="mb-6">
            <!-- All clear banner -->
            <div
                v-if="alerts.length === 0"
                class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-xl px-5 py-3.5"
            >
                <CheckCircle class="w-5 h-5 text-green-500 shrink-0" />
                <span class="text-sm font-medium text-green-800">Todo en orden — No hay alertas activas</span>
            </div>

            <!-- Alert list -->
            <div v-else class="space-y-2">
                <div
                    v-for="(alert, index) in alerts"
                    :key="index"
                    :class="[getAlertConfig(alert.level).containerClass, 'flex items-center gap-3 border rounded-xl px-5 py-3.5']"
                >
                    <component
                        :is="getAlertConfig(alert.level).icon"
                        :class="[getAlertConfig(alert.level).iconClass, 'w-5 h-5 shrink-0']"
                    />
                    <span :class="[getAlertConfig(alert.level).textClass, 'text-sm font-medium flex-1']">
                        {{ alert.message }}
                    </span>
                    <Link
                        v-if="alert.action_url"
                        :href="alert.action_url"
                        :class="[getAlertConfig(alert.level).linkClass, 'text-sm font-medium whitespace-nowrap']"
                    >
                        {{ alert.action_label }}
                    </Link>
                </div>
            </div>
        </div>

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
                    class="text-sm text-[#0052FF] hover:text-[#0047DB] font-medium flex items-center gap-1"
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
                                    class="text-[#0052FF] hover:text-[#0047DB] font-medium inline-flex items-center gap-1"
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

        <!-- Metrics row: bar charts + plan distribution -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">

            <!-- Chart: Nuevos Restaurantes por mes -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-800 mb-4">Nuevos Restaurantes</h2>
                <p class="text-xs text-gray-400 mb-4">Últimos 6 meses</p>
                <div class="flex items-end gap-2 h-32">
                    <template v-if="stats.tenants_by_month?.length">
                        <div
                            v-for="item in stats.tenants_by_month"
                            :key="item.month"
                            class="flex-1 flex flex-col items-center gap-1"
                        >
                            <span class="text-xs font-medium text-gray-600">{{ item.count }}</span>
                            <div class="w-full flex items-end" style="height: 80px">
                                <div
                                    :style="{ height: barHeight(item.count, tenantsMax) + '%' }"
                                    class="w-full bg-[#00D1FF] rounded-t transition-all duration-300 min-h-px"
                                ></div>
                            </div>
                            <span class="text-xs text-gray-400">{{ item.month }}</span>
                        </div>
                    </template>
                    <p v-else class="text-sm text-gray-400 w-full text-center">Sin datos</p>
                </div>
            </div>

            <!-- Chart: Pedidos por mes -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-800 mb-4">Pedidos por Mes</h2>
                <p class="text-xs text-gray-400 mb-4">Últimos 6 meses</p>
                <div class="flex items-end gap-2 h-32">
                    <template v-if="stats.orders_by_month?.length">
                        <div
                            v-for="item in stats.orders_by_month"
                            :key="item.month"
                            class="flex-1 flex flex-col items-center gap-1"
                        >
                            <span class="text-xs font-medium text-gray-600">{{ item.count }}</span>
                            <div class="w-full flex items-end" style="height: 80px">
                                <div
                                    :style="{ height: barHeight(item.count, ordersMax) + '%' }"
                                    class="w-full bg-blue-500 rounded-t transition-all duration-300 min-h-px"
                                ></div>
                            </div>
                            <span class="text-xs text-gray-400">{{ item.month }}</span>
                        </div>
                    </template>
                    <p v-else class="text-sm text-gray-400 w-full text-center">Sin datos</p>
                </div>
            </div>

            <!-- Plan distribution -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-800 mb-4">Distribución de Planes</h2>
                <p class="text-xs text-gray-400 mb-4">Suscripciones activas</p>
                <div class="space-y-3">
                    <template v-if="stats.plan_distribution?.length">
                        <div
                            v-for="plan in stats.plan_distribution"
                            :key="plan.slug"
                            class="flex items-center justify-between"
                        >
                            <div class="flex items-center gap-2">
                                <div :class="[getPlanStyle(plan.slug).bar, 'w-3 h-3 rounded-full flex-shrink-0']"></div>
                                <span class="text-sm font-medium text-gray-700">{{ plan.name }}</span>
                            </div>
                            <span :class="[getPlanStyle(plan.slug).badge, 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold']">
                                {{ plan.count }} {{ plan.count === 1 ? 'restaurante' : 'restaurantes' }}
                            </span>
                        </div>
                    </template>
                    <p v-else class="text-sm text-gray-400">Sin suscripciones activas</p>
                </div>
            </div>

        </div>
    </SuperAdminLayout>
</template>
