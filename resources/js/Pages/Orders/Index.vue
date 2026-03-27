<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePageAutoRefresh } from '@/Composables/usePageAutoRefresh';
import {
    ClipboardList, ChefHat, CheckCircle, Truck, PackageCheck, MapPin, Send, Pencil,
    DollarSign, Clock, XCircle, TrendingUp, Calendar, Store,
} from 'lucide-vue-next';
import AppBadge from '@/Components/AppBadge.vue';
import AppButton from '@/Components/AppButton.vue';
import AppModal from '@/Components/AppModal.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import StatCard from '@/Components/StatCard.vue';
import { getStatusLabel } from '@/Utils/orderStatus';
import { formatCurrency, getInitials, getAvatarColor } from '@/Utils/formatters';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    orders: Object,
    stats: Object,
    statusCounts: Object,
    filters: Object,
    drivers: Array,
});

const statusFilter = ref(props.filters?.status || '');
const dateFilter = ref(props.filters?.date || new Date().toISOString().slice(0, 10));

const statuses = [
    { key: '', label: 'Todos', variant: 'gray' },
    { key: 'confirmed', label: 'Confirmado', variant: 'blue' },
    { key: 'in_preparation', label: 'Preparando', variant: 'amber' },
    { key: 'ready', label: 'Listo', variant: 'emerald' },
    { key: 'out_for_delivery', label: 'En camino', variant: 'violet' },
    { key: 'delivered', label: 'Entregado', variant: 'gray' },
    { key: 'cancelled', label: 'Cancelado', variant: 'red' },
];

const totalStatusCount = computed(() => {
    return Object.values(props.statusCounts || {}).reduce((a, b) => a + b, 0);
});

const getCount = (key) => {
    if (!key) return totalStatusCount.value;
    return props.statusCounts?.[key] || 0;
};

const navigate = (params) => {
    router.get('/orders', { ...params }, { preserveState: true, preserveScroll: true });
};

const applyFilter = (key) => {
    statusFilter.value = key;
    navigate({ status: key, date: dateFilter.value });
};

const changeDate = (e) => {
    dateFilter.value = e.target.value;
    navigate({ status: statusFilter.value, date: e.target.value });
};

const updateStatus = (orderId, action) => {
    router.put(`/orders/${orderId}/status`, { action }, {
        preserveScroll: true,
        onSuccess: () => router.reload(),
    });
};

const assignDriver = (orderId, event) => {
    const driverId = event.target.value;
    if (!driverId) return;
    router.post(`/orders/${orderId}/assign-driver`, { driver_id: driverId }, {
        preserveScroll: true,
        onSuccess: () => { event.target.value = ''; },
    });
};

const isAssignable = (status) => status === 'ready';

const badgeVariant = (status) => {
    const map = { confirmed: 'blue', in_preparation: 'amber', ready: 'emerald', out_for_delivery: 'violet', delivered: 'gray', cancelled: 'red' };
    return map[status] || 'gray';
};

const editingAddress = ref(null);
const addressValue = ref('');
const sendingToDriver = ref(null);

// Cancellation
const cancelTarget = ref(null);
const cancelForm = useForm({ action: 'cancel', cancellation_reason: '' });

const openCancel = (order) => {
    cancelTarget.value = order;
    cancelForm.cancellation_reason = '';
};

const submitCancel = () => {
    cancelForm.put(`/orders/${cancelTarget.value.id}/status`, {
        preserveScroll: true,
        onSuccess: () => {
            cancelTarget.value = null;
            router.reload();
        },
    });
};

const openAddressEdit = (order) => {
    editingAddress.value = order.id;
    addressValue.value = order.delivery_address || '';
};

const saveAddress = () => {
    router.patch(`/orders/${editingAddress.value}/delivery-address`, {
        delivery_address: addressValue.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { editingAddress.value = null; },
    });
};

const sendToDriver = (orderId) => {
    sendingToDriver.value = orderId;
    router.post(`/orders/${orderId}/send-to-driver`, {}, {
        preserveScroll: true,
        onFinish: () => { sendingToDriver.value = null; },
    });
};

const actionConfig = {
    confirmed: { action: 'prepare', label: 'Preparar', icon: ChefHat, variant: 'primary' },
    in_preparation: { action: 'ready', label: 'Listo', icon: CheckCircle, variant: 'success' },
    out_for_delivery: { action: 'deliver', label: 'Entregado', icon: PackageCheck, variant: 'secondary' },
};

const canCancel = (status) => ['confirmed', 'in_preparation', 'ready', 'out_for_delivery'].includes(status);

// Keep orders list fresh — skip refresh when a modal is open to avoid resetting user input
usePageAutoRefresh(30, () => !editingAddress.value && !cancelTarget.value);
</script>

<template>
    <div>
        <!-- KPI Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <StatCard title="Ordenes" :value="stats?.total || 0" :icon="ClipboardList" color="primary" />
            <StatCard title="Ingresos" :value="formatCurrency(stats?.revenue || 0)" :icon="DollarSign" color="emerald" />
            <StatCard title="Entregadas" :value="stats?.delivered || 0" :icon="PackageCheck" color="blue" />
            <StatCard title="Canceladas" :value="stats?.cancelled || 0" :icon="XCircle" color="red" />
            <StatCard title="Tiempo prom." :value="stats?.avg_time ? stats.avg_time + ' min' : 'N/A'" :icon="Clock" color="amber" />
        </div>

        <!-- Date filter + Status tabs -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-4 mb-6">
            <div class="flex items-center gap-2">
                <Calendar class="w-4 h-4 text-gray-400" />
                <input type="date" :value="dateFilter" @change="changeDate"
                       class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500" />
            </div>
            <div class="flex flex-wrap gap-2 flex-1">
                <button
                    v-for="s in statuses" :key="s.key"
                    @click="applyFilter(s.key)"
                    class="px-3.5 py-1.5 rounded-full text-sm font-medium transition-all duration-150 border flex items-center gap-1.5"
                    :class="statusFilter === s.key
                        ? 'bg-gradient-to-r from-primary-600 to-primary-500 text-white border-transparent shadow-sm'
                        : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                >
                    {{ s.label }}
                    <span v-if="getCount(s.key) > 0"
                          class="inline-flex items-center justify-center min-w-[20px] h-5 rounded-full text-xs font-bold px-1.5"
                          :class="statusFilter === s.key ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600'">
                        {{ getCount(s.key) }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Desktop table -->
        <div class="bg-surface rounded-xl border border-gray-200 shadow-sm overflow-hidden hidden md:block">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="bg-primary-50/50">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Mensajero</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="order in orders?.data" :key="order.id" class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <Link :href="`/orders/${order.id}`" class="text-primary-600 hover:text-primary-700 font-semibold text-sm font-mono">
                                {{ order.order_number }}
                            </Link>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                     :class="getAvatarColor(order.customer_name)">
                                    {{ getInitials(order.customer_name) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ order.customer_name }}</p>
                                    <p class="text-xs text-gray-400">{{ order.customer_phone }}</p>
                                    <div v-if="order.delivery_type === 'delivery'" class="flex items-center gap-1 mt-0.5">
                                        <MapPin class="w-3 h-3 text-gray-400 shrink-0" />
                                        <p class="text-xs text-gray-400 truncate max-w-[180px]">{{ order.delivery_address || 'Sin direccion' }}</p>
                                        <button @click="openAddressEdit(order)" class="text-gray-300 hover:text-primary-500 transition-colors shrink-0">
                                            <Pencil class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="font-semibold text-sm text-gray-900">{{ formatCurrency(order.total) }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <AppBadge :variant="badgeVariant(order.status)" dot>
                                {{ getStatusLabel(order.status) }}
                            </AppBadge>
                            <p v-if="order.status === 'cancelled' && order.cancellation_reason" class="text-xs text-red-400 mt-1 max-w-[160px] truncate" :title="order.cancellation_reason">
                                {{ order.cancellation_reason }}
                            </p>
                        </td>
                        <td class="px-6 py-4">
                            <template v-if="order.delivery_type === 'pickup'">
                                <span class="inline-flex items-center gap-1 text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5">
                                    <Store class="w-3 h-3" /> Pickup
                                </span>
                            </template>
                            <template v-else-if="isAssignable(order.status) && drivers?.length">
                                <select @change="assignDriver(order.id, $event)"
                                        class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 text-gray-700 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500">
                                    <option value="">Asignar y enviar...</option>
                                    <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                                        {{ driver.name }}
                                    </option>
                                </select>
                            </template>
                            <template v-else-if="order.driver_id">
                                <span class="text-sm text-gray-700 flex items-center gap-1">
                                    🛵 {{ order.driver?.name || 'Asignado' }}
                                </span>
                            </template>
                            <template v-else>
                                <span class="text-xs text-gray-300">—</span>
                            </template>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <AppButton
                                    v-if="order.status === 'out_for_delivery' && order.driver_id"
                                    size="xs" variant="secondary"
                                    @click="sendToDriver(order.id)"
                                    :disabled="sendingToDriver === order.id"
                                    title="Reenviar pedido al mensajero"
                                >
                                    <Send class="w-3.5 h-3.5" />
                                </AppButton>
                                <AppButton
                                    v-if="canCancel(order.status)"
                                    size="xs" variant="danger"
                                    @click="openCancel(order)"
                                    title="Cancelar orden"
                                >
                                    <XCircle class="w-3.5 h-3.5" />
                                </AppButton>
                                <AppButton
                                    v-if="order.status === 'ready' && order.delivery_type === 'pickup'"
                                    size="xs" variant="success"
                                    @click="updateStatus(order.id, 'pickup')"
                                >
                                    <Store class="w-3.5 h-3.5" /> Entregar
                                </AppButton>
                                <AppButton
                                    v-else-if="actionConfig[order.status]"
                                    size="xs"
                                    :variant="actionConfig[order.status].variant"
                                    @click="updateStatus(order.id, actionConfig[order.status].action)"
                                >
                                    <component :is="actionConfig[order.status].icon" class="w-3.5 h-3.5" />
                                    {{ actionConfig[order.status].label }}
                                </AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <AppEmptyState
                v-if="!orders?.data?.length"
                :icon="ClipboardList"
                title="Sin ordenes"
                description="No hay ordenes para la fecha seleccionada"
            />
        </div>

        <!-- Mobile cards -->
        <div class="md:hidden space-y-3">
            <div v-for="order in orders?.data" :key="order.id"
                 class="bg-surface rounded-xl border border-gray-200 shadow-sm p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold"
                             :class="getAvatarColor(order.customer_name)">
                            {{ getInitials(order.customer_name) }}
                        </div>
                        <div>
                            <Link :href="`/orders/${order.id}`" class="font-semibold text-sm text-gray-900 font-mono">
                                #{{ order.order_number }}
                            </Link>
                            <p class="text-xs text-gray-500">{{ order.customer_name }}</p>
                        </div>
                    </div>
                    <AppBadge :variant="badgeVariant(order.status)" size="xs" dot>
                        {{ getStatusLabel(order.status) }}
                    </AppBadge>
                </div>
                <div v-if="order.delivery_type === 'delivery'" class="flex items-center gap-1 mb-2">
                    <MapPin class="w-3 h-3 text-gray-400 shrink-0" />
                    <p class="text-xs text-gray-400 truncate flex-1">{{ order.delivery_address || 'Sin direccion' }}</p>
                    <button @click="openAddressEdit(order)" class="text-gray-300 hover:text-primary-500 transition-colors shrink-0">
                        <Pencil class="w-3 h-3" />
                    </button>
                </div>
                <!-- Driver assignment (mobile) - only when ready and not pickup -->
                <div v-if="order.delivery_type === 'pickup'" class="mb-2">
                    <span class="inline-flex items-center gap-1 text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5">
                        <Store class="w-3 h-3" /> Pickup
                    </span>
                </div>
                <div v-else-if="isAssignable(order.status) && drivers?.length" class="mb-2">
                    <select @change="assignDriver(order.id, $event)"
                            class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 text-gray-700">
                        <option value="">Asignar mensajero y enviar...</option>
                        <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                            {{ driver.name }}
                        </option>
                    </select>
                </div>
                <div v-else-if="order.driver_id && order.status === 'out_for_delivery'" class="mb-2">
                    <span class="text-xs text-gray-500">🛵 {{ order.driver?.name }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-semibold text-gray-900">{{ formatCurrency(order.total) }}</span>
                    <div class="flex items-center gap-2">
                        <AppButton v-if="canCancel(order.status)" size="xs" variant="danger" @click="openCancel(order)">
                            <XCircle class="w-3.5 h-3.5" />
                        </AppButton>
                        <AppButton
                            v-if="order.status === 'out_for_delivery' && order.driver_id"
                            size="xs" variant="secondary"
                            @click="sendToDriver(order.id)" :disabled="sendingToDriver === order.id"
                        >
                            <Send class="w-3.5 h-3.5" />
                        </AppButton>
                        <AppButton
                            v-if="order.status === 'ready' && order.delivery_type === 'pickup'"
                            size="xs" variant="success"
                            @click="updateStatus(order.id, 'pickup')"
                        >
                            <Store class="w-3.5 h-3.5" /> Entregar
                        </AppButton>
                        <AppButton
                            v-else-if="actionConfig[order.status]"
                            size="xs" :variant="actionConfig[order.status].variant"
                            @click="updateStatus(order.id, actionConfig[order.status].action)"
                        >
                            <component :is="actionConfig[order.status].icon" class="w-3.5 h-3.5" />
                            {{ actionConfig[order.status].label }}
                        </AppButton>
                    </div>
                </div>
                <p v-if="order.status === 'cancelled' && order.cancellation_reason" class="text-xs text-red-400 mt-2 italic">
                    Razon: {{ order.cancellation_reason }}
                </p>
            </div>

            <AppEmptyState
                v-if="!orders?.data?.length"
                :icon="ClipboardList"
                title="Sin ordenes"
                description="No hay ordenes para la fecha seleccionada"
            />
        </div>

        <!-- Address edit modal -->
        <AppModal :show="!!editingAddress" title="Direccion de entrega" @close="editingAddress = null">
            <textarea v-model="addressValue" rows="3"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 outline-none"
                placeholder="Direccion de entrega..." />
            <template #footer>
                <AppButton variant="secondary" size="sm" @click="editingAddress = null">Cancelar</AppButton>
                <AppButton variant="primary" size="sm" @click="saveAddress">Guardar</AppButton>
            </template>
        </AppModal>

        <!-- Cancel order modal -->
        <AppModal :show="!!cancelTarget" title="Cancelar Orden" @close="cancelTarget = null">
            <div class="space-y-4">
                <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                    <p class="text-sm text-red-800">
                        Vas a cancelar la orden <strong>#{{ cancelTarget?.order_number }}</strong> de <strong>{{ cancelTarget?.customer_name }}</strong>.
                        Esta accion notificara al cliente.
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Razon de la cancelacion *</label>
                    <textarea v-model="cancelForm.cancellation_reason" rows="3"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 outline-none"
                        placeholder="Ej: Cliente solicito cancelacion, producto no disponible, direccion incorrecta..." />
                    <p v-if="cancelForm.errors.cancellation_reason" class="text-red-500 text-xs mt-1">{{ cancelForm.errors.cancellation_reason }}</p>
                </div>
            </div>
            <template #footer>
                <AppButton variant="secondary" size="sm" @click="cancelTarget = null">Volver</AppButton>
                <AppButton variant="danger" size="sm" @click="submitCancel" :loading="cancelForm.processing"
                           :disabled="!cancelForm.cancellation_reason.trim()">
                    Cancelar Orden
                </AppButton>
            </template>
        </AppModal>
    </div>
</template>
