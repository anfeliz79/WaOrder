<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePageAutoRefresh } from '@/Composables/usePageAutoRefresh';
import { ShoppingBag, DollarSign, Flame, Clock, ArrowRight } from 'lucide-vue-next';
import StatCard from '@/Components/StatCard.vue';
import AppCard from '@/Components/AppCard.vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import { statusConfig, getStatusLabel } from '@/Utils/orderStatus';
import { formatCurrency, formatRelativeTime, getInitials, getAvatarColor } from '@/Utils/formatters';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    stats: Object,
    recentOrders: Array,
});

// Keep dashboard stats and recent orders fresh
usePageAutoRefresh(30);

const user = computed(() => usePage().props.auth?.user);

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Buenos dias';
    if (hour < 18) return 'Buenas tardes';
    return 'Buenas noches';
});

const formattedDate = computed(() => {
    return new Date().toLocaleDateString('es-ES', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
});

const badgeVariant = (status) => {
    const map = {
        confirmed: 'blue',
        in_preparation: 'amber',
        ready: 'emerald',
        out_for_delivery: 'violet',
        delivered: 'gray',
        cancelled: 'red',
    };
    return map[status] || 'gray';
};

const statusBorderClass = (status) => {
    const map = {
        confirmed: 'border-l-blue-500',
        in_preparation: 'border-l-amber-500',
        ready: 'border-l-emerald-500',
        out_for_delivery: 'border-l-violet-500',
        delivered: 'border-l-gray-400',
        cancelled: 'border-l-red-500',
    };
    return map[status] || 'border-l-gray-300';
};

const statusBarColorClass = (status) => {
    const map = {
        confirmed: 'bg-blue-500',
        in_preparation: 'bg-amber-500',
        ready: 'bg-emerald-500',
        out_for_delivery: 'bg-violet-500',
        delivered: 'bg-gray-400',
        cancelled: 'bg-red-500',
    };
    return map[status] || 'bg-gray-300';
};

const statusDistribution = computed(() => {
    if (!props.recentOrders?.length) return [];
    const counts = {};
    props.recentOrders.forEach(order => {
        counts[order.status] = (counts[order.status] || 0) + 1;
    });
    const total = props.recentOrders.length;
    const activeStatuses = ['confirmed', 'in_preparation', 'ready', 'out_for_delivery'];
    const hasActive = activeStatuses.some(s => counts[s] > 0);
    if (!hasActive) return [];

    return Object.entries(counts).map(([status, count]) => ({
        status,
        count,
        label: getStatusLabel(status),
        percentage: (count / total) * 100,
        colorClass: statusBarColorClass(status),
    }));
});
</script>

<template>
    <div>
        <!-- Greeting -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900">{{ greeting }}, {{ user?.name ?? 'Usuario' }}</h2>
            <p class="text-sm text-gray-500 mt-0.5 capitalize">{{ formattedDate }}</p>
        </div>

        <!-- Stats cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <StatCard
                title="Ordenes hoy"
                :value="stats?.orders_today ?? 0"
                :icon="ShoppingBag"
                color="primary"
            />
            <StatCard
                title="Revenue hoy"
                :value="formatCurrency(stats?.revenue_today)"
                :icon="DollarSign"
                color="emerald"
            />
            <StatCard
                title="Ordenes activas"
                :value="stats?.active_orders ?? 0"
                :icon="Flame"
                color="amber"
                :pulse="(stats?.active_orders ?? 0) > 0"
            />
            <StatCard
                title="Tiempo promedio"
                :value="`${stats?.avg_time ?? 0} min`"
                :icon="Clock"
                color="violet"
            />
        </div>

        <!-- Status distribution bar -->
        <div v-if="statusDistribution.length" class="mb-8">
            <div class="flex h-2 rounded-full overflow-hidden bg-gray-100">
                <div
                    v-for="seg in statusDistribution"
                    :key="seg.status"
                    :class="seg.colorClass"
                    :style="{ width: seg.percentage + '%' }"
                    :title="`${seg.label}: ${seg.count}`"
                    class="transition-all duration-300"
                />
            </div>
            <div class="flex flex-wrap gap-3 mt-2">
                <div v-for="seg in statusDistribution" :key="seg.status" class="flex items-center gap-1.5 text-xs text-gray-500">
                    <span class="w-2 h-2 rounded-full" :class="seg.colorClass"></span>
                    {{ seg.label }} ({{ seg.count }})
                </div>
            </div>
        </div>

        <!-- Recent orders -->
        <AppCard noPadding>
            <template #header>
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Ordenes recientes</h3>
                    <Link href="/orders" class="text-sm text-primary-600 hover:text-primary-700 font-medium flex items-center gap-1">
                        Ver todas
                        <ArrowRight class="w-3.5 h-3.5" />
                    </Link>
                </div>
            </template>

            <div class="divide-y divide-gray-100">
                <div v-for="order in recentOrders" :key="order.id"
                     class="px-6 py-4 flex items-center justify-between hover:bg-gray-50/50 transition-colors border-l-3"
                     :class="statusBorderClass(order.status)">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                             :class="getAvatarColor(order.customer_name)">
                            {{ getInitials(order.customer_name) }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <Link :href="`/orders/${order.id}`" class="font-semibold text-gray-900 hover:text-primary-600 transition-colors text-sm">
                                    #{{ order.order_number }}
                                </Link>
                                <span class="text-xs text-gray-400">{{ formatRelativeTime(order.created_at) }}</span>
                            </div>
                            <p class="text-sm text-gray-500 truncate">{{ order.customer_name }} · {{ order.items_count }} items</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <p class="font-semibold text-sm text-gray-900">{{ formatCurrency(order.total) }}</p>
                        <AppBadge :variant="badgeVariant(order.status)" size="xs">
                            {{ getStatusLabel(order.status) }}
                        </AppBadge>
                    </div>
                </div>
            </div>

            <AppEmptyState
                v-if="!recentOrders?.length"
                :icon="ShoppingBag"
                title="Sin ordenes aun"
                description="Las ordenes de WhatsApp apareceran aqui"
            />
        </AppCard>
    </div>
</template>
