<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    ShoppingBag,
    DollarSign,
    TrendingUp,
    XCircle,
    Clock,
    Truck,
    CreditCard,
    BarChart3,
    Users,
} from 'lucide-vue-next';
import StatCard from '@/Components/StatCard.vue';
import AppCard from '@/Components/AppCard.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import { formatCurrency } from '@/Utils/formatters';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    period: String,
    summary: Object,
    daily_orders: Array,
    top_items: Array,
    orders_by_status: Object,
    peak_hours: Array,
    customer_stats: Object,
    delivery_breakdown: Object,
    payment_breakdown: Object,
});

const periods = [
    { value: '7d', label: '7 dias' },
    { value: '30d', label: '30 dias' },
    { value: '90d', label: '90 dias' },
];

const switchPeriod = (period) => {
    router.get('/reports', { period }, { preserveState: true, preserveScroll: true });
};

// Chart computations
const maxDailyCount = computed(() => {
    if (!props.daily_orders?.length) return 1;
    return Math.max(...props.daily_orders.map(d => Number(d.count)), 1);
});

const maxDailyRevenue = computed(() => {
    if (!props.daily_orders?.length) return 1;
    return Math.max(...props.daily_orders.map(d => Number(d.revenue)), 1);
});

const chartMode = computed(() => 'count'); // could be toggled

// Status labels
const statusLabels = {
    confirmed: 'Confirmado',
    in_preparation: 'En preparacion',
    ready: 'Listo',
    out_for_delivery: 'En camino',
    delivered: 'Entregado',
    cancelled: 'Cancelado',
};

const statusColors = {
    confirmed: 'bg-blue-500',
    in_preparation: 'bg-amber-500',
    ready: 'bg-emerald-500',
    out_for_delivery: 'bg-violet-500',
    delivered: 'bg-gray-400',
    cancelled: 'bg-red-500',
};

const statusDotColors = {
    confirmed: 'bg-blue-500',
    in_preparation: 'bg-amber-500',
    ready: 'bg-emerald-500',
    out_for_delivery: 'bg-violet-500',
    delivered: 'bg-gray-400',
    cancelled: 'bg-red-500',
};

// Delivery type labels
const deliveryLabels = {
    delivery: 'Delivery',
    pickup: 'Recogida',
};

// Payment method labels
const paymentLabels = {
    cash: 'Efectivo',
    card: 'Tarjeta',
    transfer: 'Transferencia',
};

// Total for breakdown percentages
const deliveryTotal = computed(() => {
    if (!props.delivery_breakdown) return 0;
    return Object.values(props.delivery_breakdown).reduce((sum, v) => sum + v, 0);
});

const paymentTotal = computed(() => {
    if (!props.payment_breakdown) return 0;
    return Object.values(props.payment_breakdown).reduce((sum, v) => sum + v, 0);
});

// Total orders by status for bar widths
const statusTotal = computed(() => {
    if (!props.orders_by_status) return 0;
    return Object.values(props.orders_by_status).reduce((sum, v) => sum + v, 0);
});

// Format hour to human-readable
const formatHour = (hour) => {
    const h = Number(hour);
    if (h === 0) return '12 AM';
    if (h < 12) return `${h} AM`;
    if (h === 12) return '12 PM';
    return `${h - 12} PM`;
};

// Format short date for chart labels
const formatShortDate = (dateStr) => {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('es-ES', { month: 'short', day: 'numeric' });
};

// Max peak hour count
const maxPeakCount = computed(() => {
    if (!props.peak_hours?.length) return 1;
    return Math.max(...props.peak_hours.map(h => Number(h.count)), 1);
});
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Reportes</h2>
                <p class="text-sm text-gray-500 mt-0.5">Metricas de ventas y rendimiento</p>
            </div>

            <!-- Period selector -->
            <div class="flex bg-gray-100 rounded-lg p-1">
                <button
                    v-for="p in periods"
                    :key="p.value"
                    @click="switchPeriod(p.value)"
                    class="px-4 py-2 text-sm font-medium rounded-md transition-all duration-200"
                    :class="period === p.value
                        ? 'bg-white text-gray-900 shadow-sm'
                        : 'text-gray-500 hover:text-gray-700'"
                >
                    {{ p.label }}
                </button>
            </div>
        </div>

        <!-- Summary stat cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <StatCard
                title="Total pedidos"
                :value="summary.total_orders"
                :icon="ShoppingBag"
                color="primary"
            />
            <StatCard
                title="Ingresos totales"
                :value="formatCurrency(summary.total_revenue)"
                :icon="DollarSign"
                color="emerald"
            />
            <StatCard
                title="Valor promedio"
                :value="formatCurrency(summary.avg_order_value)"
                :icon="TrendingUp"
                color="violet"
            />
            <StatCard
                title="Tasa cancelacion"
                :value="`${summary.cancel_rate}%`"
                :icon="XCircle"
                color="amber"
            />
        </div>

        <!-- Daily chart -->
        <AppCard class="mb-8">
            <template #header>
                <div class="flex items-center gap-2">
                    <BarChart3 class="w-5 h-5 text-primary-500" />
                    <h3 class="text-lg font-semibold text-gray-900">Pedidos por dia</h3>
                </div>
            </template>

            <div v-if="daily_orders?.length" class="space-y-4">
                <!-- Chart -->
                <div class="flex items-end gap-1 h-48 overflow-x-auto pb-2">
                    <div
                        v-for="day in daily_orders"
                        :key="day.date"
                        class="flex-1 min-w-[28px] max-w-[48px] flex flex-col items-center group relative"
                    >
                        <!-- Tooltip -->
                        <div class="absolute bottom-full mb-2 hidden group-hover:block z-10">
                            <div class="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 shadow-lg whitespace-nowrap">
                                <p class="font-semibold">{{ formatShortDate(day.date) }}</p>
                                <p>{{ day.count }} pedidos</p>
                                <p>{{ formatCurrency(day.revenue) }}</p>
                            </div>
                        </div>
                        <!-- Bar -->
                        <div
                            class="w-full rounded-t-md bg-gradient-to-t from-primary-500 to-primary-400 hover:from-primary-600 hover:to-primary-500 transition-all duration-200 cursor-pointer"
                            :style="{ height: Math.max((Number(day.count) / maxDailyCount) * 100, 4) + '%' }"
                        ></div>
                        <!-- Label -->
                        <span class="text-[10px] text-gray-400 mt-1.5 truncate w-full text-center">
                            {{ formatShortDate(day.date) }}
                        </span>
                    </div>
                </div>

                <!-- Summary below chart -->
                <div class="flex items-center justify-between text-sm text-gray-500 pt-2 border-t border-gray-100">
                    <span>{{ daily_orders.length }} dias</span>
                    <span>Total: {{ formatCurrency(summary.total_revenue) }}</span>
                </div>
            </div>

            <AppEmptyState
                v-else
                :icon="BarChart3"
                title="Sin datos"
                description="No hay pedidos en el periodo seleccionado"
            />
        </AppCard>

        <!-- Two-column: Top items + Peak hours -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Top items -->
            <AppCard noPadding>
                <template #header>
                    <div class="flex items-center gap-2">
                        <TrendingUp class="w-5 h-5 text-emerald-500" />
                        <h3 class="text-lg font-semibold text-gray-900">Top productos</h3>
                    </div>
                </template>

                <div v-if="top_items?.length">
                    <div class="divide-y divide-gray-100">
                        <div
                            v-for="(item, idx) in top_items"
                            :key="item.name"
                            class="px-6 py-3.5 flex items-center gap-4 hover:bg-gray-50/50 transition-colors"
                        >
                            <!-- Rank -->
                            <span
                                class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                :class="idx < 3 ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ idx + 1 }}
                            </span>

                            <!-- Name + bar -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ item.name }}</p>
                                <div class="mt-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-500 transition-all duration-500"
                                        :style="{ width: (Number(item.total_qty) / Number(top_items[0].total_qty) * 100) + '%' }"
                                    ></div>
                                </div>
                            </div>

                            <!-- Stats -->
                            <div class="text-right shrink-0">
                                <p class="text-sm font-semibold text-gray-900">{{ item.total_qty }}</p>
                                <p class="text-xs text-gray-500">{{ formatCurrency(item.total_revenue) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="p-6">
                    <AppEmptyState
                        :icon="ShoppingBag"
                        title="Sin datos"
                        description="No hay items vendidos en este periodo"
                    />
                </div>
            </AppCard>

            <!-- Peak hours -->
            <AppCard>
                <template #header>
                    <div class="flex items-center gap-2">
                        <Clock class="w-5 h-5 text-violet-500" />
                        <h3 class="text-lg font-semibold text-gray-900">Horas pico</h3>
                    </div>
                </template>

                <div v-if="peak_hours?.length" class="space-y-4">
                    <div
                        v-for="(hour, idx) in peak_hours"
                        :key="hour.hour"
                        class="flex items-center gap-3"
                    >
                        <span class="text-sm font-medium text-gray-600 w-14 text-right shrink-0">
                            {{ formatHour(hour.hour) }}
                        </span>
                        <div class="flex-1 h-8 bg-gray-100 rounded-lg overflow-hidden relative">
                            <div
                                class="h-full rounded-lg transition-all duration-500"
                                :class="idx === 0 ? 'bg-gradient-to-r from-violet-400 to-violet-500' : 'bg-gradient-to-r from-violet-200 to-violet-300'"
                                :style="{ width: (Number(hour.count) / maxPeakCount * 100) + '%' }"
                            ></div>
                            <span class="absolute inset-0 flex items-center px-3 text-xs font-semibold"
                                  :class="(Number(hour.count) / maxPeakCount) > 0.4 ? 'text-white' : 'text-gray-600'">
                                {{ hour.count }} pedidos
                            </span>
                        </div>
                    </div>
                </div>

                <AppEmptyState
                    v-else
                    :icon="Clock"
                    title="Sin datos"
                    description="No hay datos de horas pico"
                />
            </AppCard>
        </div>

        <!-- Three-column: Status + Delivery + Payment breakdowns -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Orders by status -->
            <AppCard>
                <template #header>
                    <div class="flex items-center gap-2">
                        <BarChart3 class="w-5 h-5 text-blue-500" />
                        <h3 class="text-base font-semibold text-gray-900">Por estado</h3>
                    </div>
                </template>

                <div v-if="statusTotal > 0" class="space-y-3">
                    <div
                        v-for="(count, status) in orders_by_status"
                        :key="status"
                        class="flex items-center gap-3"
                    >
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="statusDotColors[status] || 'bg-gray-400'"></span>
                        <span class="text-sm text-gray-700 flex-1 truncate">{{ statusLabels[status] || status }}</span>
                        <span class="text-sm font-semibold text-gray-900">{{ count }}</span>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400 text-center py-4">Sin datos</p>
            </AppCard>

            <!-- Delivery type -->
            <AppCard>
                <template #header>
                    <div class="flex items-center gap-2">
                        <Truck class="w-5 h-5 text-cyan-500" />
                        <h3 class="text-base font-semibold text-gray-900">Tipo entrega</h3>
                    </div>
                </template>

                <div v-if="deliveryTotal > 0" class="space-y-4">
                    <div
                        v-for="(count, type) in delivery_breakdown"
                        :key="type"
                        class="space-y-1.5"
                    >
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-700">{{ deliveryLabels[type] || type }}</span>
                            <span class="font-semibold text-gray-900">{{ count }} <span class="text-gray-400 font-normal">({{ Math.round(count / deliveryTotal * 100) }}%)</span></span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-cyan-500 transition-all duration-500"
                                :style="{ width: (count / deliveryTotal * 100) + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400 text-center py-4">Sin datos</p>
            </AppCard>

            <!-- Payment method -->
            <AppCard>
                <template #header>
                    <div class="flex items-center gap-2">
                        <CreditCard class="w-5 h-5 text-amber-500" />
                        <h3 class="text-base font-semibold text-gray-900">Metodo de pago</h3>
                    </div>
                </template>

                <div v-if="paymentTotal > 0" class="space-y-4">
                    <div
                        v-for="(count, method) in payment_breakdown"
                        :key="method"
                        class="space-y-1.5"
                    >
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-700">{{ paymentLabels[method] || method }}</span>
                            <span class="font-semibold text-gray-900">{{ count }} <span class="text-gray-400 font-normal">({{ Math.round(count / paymentTotal * 100) }}%)</span></span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-amber-400 to-amber-500 transition-all duration-500"
                                :style="{ width: (count / paymentTotal * 100) + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400 text-center py-4">Sin datos</p>
            </AppCard>
        </div>

        <!-- Customer stats -->
        <AppCard>
            <template #header>
                <div class="flex items-center gap-2">
                    <Users class="w-5 h-5 text-primary-500" />
                    <h3 class="text-lg font-semibold text-gray-900">Clientes</h3>
                </div>
            </template>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div class="text-center">
                    <p class="text-3xl font-bold text-gray-900">{{ customer_stats.total }}</p>
                    <p class="text-sm text-gray-500 mt-1">Nuevos en el periodo</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl font-bold text-gray-900">{{ customer_stats.returning }}</p>
                    <p class="text-sm text-gray-500 mt-1">Clientes recurrentes</p>
                </div>
                <div class="text-center">
                    <p class="text-3xl font-bold text-gray-900">
                        {{ customer_stats.total > 0 ? Math.round(customer_stats.returning / customer_stats.total * 100) : 0 }}%
                    </p>
                    <p class="text-sm text-gray-500 mt-1">Tasa de retencion</p>
                </div>
            </div>
        </AppCard>
    </div>
</template>
