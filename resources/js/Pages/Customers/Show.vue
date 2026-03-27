<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft, ShoppingBag, DollarSign, Star, TrendingUp,
    ChevronDown, ChevronUp, MapPin, Phone, Calendar,
    MessageSquare, ClipboardList, BarChart3, Clock,
    Package, CreditCard, Utensils,
} from 'lucide-vue-next';
import AppCard from '@/Components/AppCard.vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppButton from '@/Components/AppButton.vue';
import AppPagination from '@/Components/AppPagination.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import StatCard from '@/Components/StatCard.vue';
import { formatCurrency, formatRelativeTime, formatDate, getInitials, getAvatarColor } from '@/Utils/formatters';
import { getStatusLabel, getStatusClass, statusConfig } from '@/Utils/orderStatus';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    customer: Object,
    orders: Object,
    surveyResponses: Array,
    chatSessions: Array,
    stats: Object,
});

const activeTab = ref('orders');
const expandedOrder = ref(null);

const tabs = [
    { id: 'orders', label: 'Ordenes', icon: ClipboardList },
    { id: 'surveys', label: 'Encuestas', icon: Star },
    { id: 'conversations', label: 'Conversaciones', icon: MessageSquare },
    { id: 'timeline', label: 'Linea de tiempo', icon: Clock },
];

const toggleOrder = (orderId) => {
    expandedOrder.value = expandedOrder.value === orderId ? null : orderId;
};

// Build unified timeline from all data sources
const timeline = computed(() => {
    const events = [];

    // Orders
    props.orders?.data?.forEach(order => {
        events.push({
            type: 'order',
            date: order.created_at,
            title: `Orden #${order.order_number}`,
            description: `${order.items_count ?? order.items?.length ?? 0} items - ${formatCurrency(order.total)}`,
            status: order.status,
            icon: Package,
            color: 'primary',
        });
    });

    // Survey responses
    props.surveyResponses?.forEach(survey => {
        events.push({
            type: 'survey',
            date: survey.created_at,
            title: `Encuesta - Orden #${survey.order?.order_number ?? '?'}`,
            description: survey.comment || `Calificacion: ${survey.rating}/5`,
            rating: survey.rating,
            icon: Star,
            color: 'amber',
        });
    });

    // Chat sessions
    props.chatSessions?.forEach(session => {
        events.push({
            type: 'chat',
            date: session.created_at,
            title: `Conversacion`,
            description: `Estado: ${session.conversation_state || session.status} - Paso: ${session.conversation_state || 'N/A'}`,
            icon: MessageSquare,
            color: 'blue',
        });
    });

    return events.sort((a, b) => new Date(b.date) - new Date(a.date));
});

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

const timelineColorClass = (color) => {
    const map = {
        primary: 'bg-primary-100 text-primary-600',
        amber: 'bg-amber-100 text-amber-600',
        blue: 'bg-blue-100 text-blue-600',
    };
    return map[color] || 'bg-gray-100 text-gray-600';
};

const stateLabel = (state) => {
    const map = {
        greeting: 'Saludo',
        menu_browsing: 'Viendo menu',
        item_selection: 'Seleccionando items',
        cart_review: 'Revisando carrito',
        collecting_info: 'Datos de entrega',
        confirmation: 'Confirmacion',
        order_active: 'Orden activa',
        order_closed: 'Finalizado',
    };
    return map[state] || state || 'Desconocido';
};
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-6">
            <Link href="/customers" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition-colors mb-4">
                <ArrowLeft class="w-4 h-4" />
                Volver a clientes
            </Link>

            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="w-14 h-14 rounded-full flex items-center justify-center text-lg font-bold shrink-0 ring-2 ring-primary-500/20"
                     :class="getAvatarColor(customer.name)">
                    {{ getInitials(customer.name) }}
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl font-bold text-gray-900">{{ customer.name || 'Sin nombre' }}</h1>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-gray-500">
                        <span class="flex items-center gap-1">
                            <Phone class="w-3.5 h-3.5" />
                            {{ customer.phone }}
                        </span>
                        <span v-if="customer.default_address" class="flex items-center gap-1">
                            <MapPin class="w-3.5 h-3.5" />
                            {{ customer.default_address }}
                        </span>
                        <span class="flex items-center gap-1">
                            <Calendar class="w-3.5 h-3.5" />
                            Cliente desde {{ formatDate(customer.created_at) }}
                        </span>
                    </div>
                </div>
                <div v-if="customer.default_delivery_type" class="shrink-0">
                    <AppBadge :variant="customer.default_delivery_type === 'delivery' ? 'violet' : 'blue'" size="sm">
                        {{ customer.default_delivery_type === 'delivery' ? 'Delivery' : 'Pickup' }}
                    </AppBadge>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard
                title="Total ordenes"
                :value="stats.total_orders"
                :icon="ShoppingBag"
                color="primary"
            />
            <StatCard
                title="Total gastado"
                :value="formatCurrency(stats.total_spent)"
                :icon="DollarSign"
                color="emerald"
            />
            <StatCard
                title="Promedio por orden"
                :value="formatCurrency(stats.avg_order_value)"
                :icon="TrendingUp"
                color="blue"
            />
            <StatCard
                title="Calificacion"
                :value="stats.avg_rating ? stats.avg_rating + '/5' : 'N/A'"
                :icon="Star"
                color="amber"
            />
        </div>

        <!-- Favorite Items -->
        <div v-if="stats.favorite_items?.length" class="mb-6">
            <AppCard>
                <template #header>
                    <div class="flex items-center gap-2">
                        <Utensils class="w-4 h-4 text-primary-500" />
                        <h3 class="font-semibold text-gray-900">Items favoritos</h3>
                    </div>
                </template>
                <div class="flex flex-wrap gap-2">
                    <div v-for="item in stats.favorite_items" :key="item.name"
                         class="inline-flex items-center gap-2 bg-primary-50 text-primary-700 rounded-full px-3 py-1.5 text-sm">
                        <span class="font-medium">{{ item.name }}</span>
                        <span class="bg-primary-200/60 text-primary-800 text-xs font-bold rounded-full px-1.5 py-0.5">
                            x{{ item.total_quantity }}
                        </span>
                    </div>
                </div>
            </AppCard>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex gap-0 -mb-px overflow-x-auto">
                <button v-for="tab in tabs" :key="tab.id"
                        @click="activeTab = tab.id"
                        class="flex items-center gap-1.5 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
                        :class="activeTab === tab.id
                            ? 'border-primary-600 text-primary-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                    <component :is="tab.icon" class="w-4 h-4" />
                    {{ tab.label }}
                </button>
            </nav>
        </div>

        <!-- Orders Tab -->
        <div v-if="activeTab === 'orders'">
            <AppCard noPadding>
                <!-- Desktop table -->
                <table class="min-w-full divide-y divide-gray-200 hidden md:table">
                    <thead>
                        <tr class="bg-primary-50/50">
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Orden</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Fecha</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Items</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Pago</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-5 py-3 w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template v-for="order in orders?.data" :key="order.id">
                            <tr class="hover:bg-gray-50/50 cursor-pointer transition-colors border-l-3"
                                :class="statusBorderClass(order.status)"
                                @click="toggleOrder(order.id)">
                                <td class="px-5 py-3 text-sm font-semibold text-gray-900">#{{ order.order_number }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500">{{ formatDate(order.created_at) }}</td>
                                <td class="px-5 py-3 text-center">
                                    <AppBadge :variant="statusConfig[order.status]?.color || 'gray'" size="xs">
                                        {{ getStatusLabel(order.status) }}
                                    </AppBadge>
                                </td>
                                <td class="px-5 py-3 text-center text-sm text-gray-500">
                                    {{ order.items_count ?? order.items?.length ?? 0 }}
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <span class="text-xs text-gray-500 flex items-center justify-center gap-1">
                                        <CreditCard class="w-3 h-3" />
                                        {{ order.payment_method || 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right font-semibold text-sm text-gray-900">
                                    {{ formatCurrency(order.total) }}
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <component :is="expandedOrder === order.id ? ChevronUp : ChevronDown"
                                               class="w-4 h-4 text-gray-400" />
                                </td>
                            </tr>
                            <!-- Expanded items -->
                            <tr v-if="expandedOrder === order.id">
                                <td colspan="7" class="bg-gray-50/50 px-8 py-3">
                                    <div class="space-y-1.5">
                                        <div v-for="item in order.items" :key="item.id"
                                             class="flex items-center justify-between text-sm">
                                            <span class="text-gray-700">
                                                <span class="font-medium text-primary-600">{{ item.quantity }}x</span>
                                                {{ item.name }}
                                            </span>
                                            <span class="text-gray-500">{{ formatCurrency(item.subtotal) }}</span>
                                        </div>
                                        <div v-if="order.notes" class="pt-1.5 border-t border-gray-200 text-xs text-gray-400 italic">
                                            Nota: {{ order.notes }}
                                        </div>
                                        <div v-if="order.delivery_fee > 0" class="flex justify-between text-xs text-gray-400 pt-1">
                                            <span>Delivery</span>
                                            <span>{{ formatCurrency(order.delivery_fee) }}</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <!-- Mobile cards -->
                <div class="md:hidden divide-y divide-gray-100">
                    <div v-for="order in orders?.data" :key="'m-' + order.id"
                         class="p-4 border-l-3 cursor-pointer"
                         :class="statusBorderClass(order.status)"
                         @click="toggleOrder(order.id)">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-semibold text-sm text-gray-900">#{{ order.order_number }}</span>
                            <AppBadge :variant="statusConfig[order.status]?.color || 'gray'" size="xs">
                                {{ getStatusLabel(order.status) }}
                            </AppBadge>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500">{{ formatDate(order.created_at) }}</span>
                            <span class="font-semibold text-gray-900">{{ formatCurrency(order.total) }}</span>
                        </div>
                        <!-- Mobile expanded items -->
                        <div v-if="expandedOrder === order.id" class="mt-3 pt-3 border-t border-gray-200 space-y-1.5">
                            <div v-for="item in order.items" :key="item.id"
                                 class="flex items-center justify-between text-sm">
                                <span class="text-gray-700">
                                    <span class="font-medium text-primary-600">{{ item.quantity }}x</span>
                                    {{ item.name }}
                                </span>
                                <span class="text-gray-500">{{ formatCurrency(item.subtotal) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <AppEmptyState
                    v-if="!orders?.data?.length"
                    :icon="ClipboardList"
                    title="Sin ordenes"
                    description="Este cliente aun no tiene ordenes registradas"
                />
            </AppCard>

            <AppPagination :data="orders" :routePath="'/customers/' + customer.id" />
        </div>

        <!-- Surveys Tab -->
        <div v-if="activeTab === 'surveys'">
            <div class="grid gap-4 sm:grid-cols-2">
                <AppCard v-for="survey in surveyResponses" :key="survey.id" hoverable>
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                Orden #{{ survey.order?.order_number ?? '?' }}
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ formatDate(survey.created_at) }}</p>
                        </div>
                        <!-- Stars -->
                        <div class="flex items-center gap-0.5">
                            <Star v-for="i in 5" :key="i"
                                  class="w-4 h-4"
                                  :class="i <= survey.rating ? 'text-amber-400 fill-amber-400' : 'text-gray-200'" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div v-if="survey.food_quality" class="flex items-center justify-between text-sm">
                            <span class="text-gray-500">Calidad comida</span>
                            <span class="font-medium text-gray-700">{{ survey.food_quality }}</span>
                        </div>
                        <div v-if="survey.delivery_speed" class="flex items-center justify-between text-sm">
                            <span class="text-gray-500">Velocidad entrega</span>
                            <span class="font-medium text-gray-700">{{ survey.delivery_speed }}</span>
                        </div>
                        <div v-if="survey.comment" class="pt-2 border-t border-gray-100">
                            <p class="text-sm text-gray-600 italic">"{{ survey.comment }}"</p>
                        </div>
                    </div>
                </AppCard>
            </div>

            <AppEmptyState
                v-if="!surveyResponses?.length"
                :icon="Star"
                title="Sin encuestas"
                description="Este cliente aun no ha respondido encuestas"
            />
        </div>

        <!-- Conversations Tab -->
        <div v-if="activeTab === 'conversations'">
            <div class="space-y-3">
                <AppCard v-for="session in chatSessions" :key="session.id" hoverable>
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                <MessageSquare class="w-4 h-4 text-blue-600" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ stateLabel(session.conversation_state) }}</p>
                                <p class="text-xs text-gray-400">{{ formatRelativeTime(session.created_at) }}</p>
                            </div>
                        </div>
                        <AppBadge :variant="session.status === 'active' ? 'emerald' : 'gray'" size="xs">
                            {{ session.status === 'active' ? 'Activa' : 'Cerrada' }}
                        </AppBadge>
                    </div>

                    <div v-if="session.cart_data" class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-xs font-medium text-gray-500 mb-1.5">Carrito:</p>
                        <div class="space-y-1">
                            <div v-for="(item, idx) in (typeof session.cart_data === 'string' ? JSON.parse(session.cart_data) : session.cart_data)?.items?.slice(0, 5)"
                                 :key="idx"
                                 class="text-sm text-gray-600 flex justify-between">
                                <span>{{ item.quantity || 1 }}x {{ item.name || item.item_name || 'Item' }}</span>
                                <span v-if="item.subtotal" class="text-gray-400">{{ formatCurrency(item.subtotal) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-2 flex items-center gap-3 text-xs text-gray-400">
                        <span>Paso: {{ session.conversation_state || 'N/A' }}</span>
                        <span>{{ formatDate(session.created_at) }}</span>
                    </div>
                </AppCard>
            </div>

            <AppEmptyState
                v-if="!chatSessions?.length"
                :icon="MessageSquare"
                title="Sin conversaciones"
                description="Este cliente aun no tiene historial de conversaciones"
            />
        </div>

        <!-- Timeline Tab -->
        <div v-if="activeTab === 'timeline'">
            <div class="relative">
                <!-- Vertical line -->
                <div class="absolute left-5 top-0 bottom-0 w-px bg-gray-200"></div>

                <div class="space-y-4">
                    <div v-for="(event, idx) in timeline" :key="idx"
                         class="relative flex gap-4 pl-0 animate-fade-in"
                         :style="{ animationDelay: `${idx * 0.03}s`, opacity: 0 }">
                        <!-- Icon circle -->
                        <div class="relative z-10 w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                             :class="timelineColorClass(event.color)">
                            <component :is="event.icon" class="w-4 h-4" />
                        </div>

                        <!-- Content -->
                        <div class="flex-1 bg-surface border border-gray-200 rounded-lg p-3 shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ event.title }}</p>
                                    <p class="text-sm text-gray-500 mt-0.5">{{ event.description }}</p>
                                </div>
                                <div class="shrink-0 flex items-center gap-2">
                                    <AppBadge v-if="event.status" :variant="statusConfig[event.status]?.color || 'gray'" size="xs">
                                        {{ getStatusLabel(event.status) }}
                                    </AppBadge>
                                    <div v-if="event.rating" class="flex items-center gap-0.5">
                                        <Star v-for="i in 5" :key="i" class="w-3 h-3"
                                              :class="i <= event.rating ? 'text-amber-400 fill-amber-400' : 'text-gray-200'" />
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-400 mt-1.5">{{ formatRelativeTime(event.date) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <AppEmptyState
                v-if="!timeline.length"
                :icon="Clock"
                title="Sin actividad"
                description="No hay actividad registrada para este cliente"
            />
        </div>
    </div>
</template>
