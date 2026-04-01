<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Clock,
    ChefHat,
    CheckCircle,
    Truck,
    PackageCheck,
    XCircle,
    ChevronDown,
    ChevronUp,
} from 'lucide-vue-next';
import ConsoleLayout from '@/Layouts/ConsoleLayout.vue';
import ConsoleOrderCard from '@/Components/ConsoleOrderCard.vue';
import AppModal from '@/Components/AppModal.vue';
import AppButton from '@/Components/AppButton.vue';
import { usePageAutoRefresh } from '@/Composables/usePageAutoRefresh';
import { formatCurrency } from '@/Utils/formatters';

defineOptions({ layout: ConsoleLayout });

const props = defineProps({
    activeOrders: { type: Array, default: () => [] },
    completedOrders: { type: Array, default: () => [] },
    statusCounts: { type: Object, default: () => ({}) },
    drivers: { type: Array, default: () => [] },
});

// Auto-refresh every 15 seconds
const cancelModalOpen = ref(false);
usePageAutoRefresh(15, () => !cancelModalOpen.value);

// Column definitions
const columns = [
    { key: 'confirmed', label: 'Nuevos', icon: Clock, color: 'blue', borderColor: 'border-blue-400' },
    { key: 'in_preparation', label: 'Preparando', icon: ChefHat, color: 'amber', borderColor: 'border-amber-400' },
    { key: 'ready', label: 'Listos', icon: CheckCircle, color: 'emerald', borderColor: 'border-emerald-400' },
    { key: 'out_for_delivery', label: 'En Camino', icon: Truck, color: 'violet', borderColor: 'border-violet-400' },
];

// Group active orders by status
const ordersByStatus = computed(() => {
    const groups = {};
    for (const col of columns) {
        groups[col.key] = props.activeOrders.filter(o => o.status === col.key);
    }
    return groups;
});

// Mobile tab
const activeTab = ref('confirmed');

// Completed section
const showCompleted = ref(false);
const deliveredOrders = computed(() => props.completedOrders.filter(o => o.status === 'delivered'));
const cancelledOrders = computed(() => props.completedOrders.filter(o => o.status === 'cancelled'));

// Cancel modal
const cancelTarget = ref(null);
const cancellationReason = ref('');

const openCancelModal = (order) => {
    cancelTarget.value = order;
    cancellationReason.value = '';
    cancelModalOpen.value = true;
};

const closeCancelModal = () => {
    cancelTarget.value = null;
    cancellationReason.value = '';
    cancelModalOpen.value = false;
};

const submitCancel = () => {
    if (!cancelTarget.value || !cancellationReason.value.trim()) return;

    router.put(`/orders/${cancelTarget.value.id}/status`, {
        action: 'cancel',
        cancellation_reason: cancellationReason.value.trim(),
    }, {
        preserveScroll: true,
        onSuccess: () => closeCancelModal(),
    });
};

// Stats
const totalActive = computed(() => props.activeOrders.length);
const totalToday = computed(() => {
    return Object.values(props.statusCounts).reduce((sum, c) => sum + c, 0);
});
const revenueToday = computed(() => {
    const delivered = props.completedOrders.filter(o => o.status === 'delivered');
    return delivered.reduce((sum, o) => sum + Number(o.total), 0);
});
</script>

<template>
    <div>
        <!-- Stats bar -->
        <div class="flex items-center gap-4 mb-4 overflow-x-auto">
            <div class="flex items-center gap-2 px-3 py-2 bg-white rounded-lg shadow-sm border border-gray-200 whitespace-nowrap">
                <div class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></div>
                <span class="text-xs text-gray-500">Activas</span>
                <span class="text-sm font-bold text-gray-900">{{ totalActive }}</span>
            </div>
            <div class="flex items-center gap-2 px-3 py-2 bg-white rounded-lg shadow-sm border border-gray-200 whitespace-nowrap">
                <span class="text-xs text-gray-500">Hoy</span>
                <span class="text-sm font-bold text-gray-900">{{ totalToday }}</span>
            </div>
            <div class="flex items-center gap-2 px-3 py-2 bg-white rounded-lg shadow-sm border border-gray-200 whitespace-nowrap">
                <span class="text-xs text-gray-500">Ingresos</span>
                <span class="text-sm font-bold text-emerald-600">{{ formatCurrency(revenueToday) }}</span>
            </div>
        </div>

        <!-- Mobile: Tab navigation -->
        <div class="lg:hidden flex gap-1 mb-4 overflow-x-auto pb-1">
            <button
                v-for="col in columns"
                :key="col.key"
                @click="activeTab = col.key"
                class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors"
                :class="activeTab === col.key
                    ? `bg-${col.color}-50 text-${col.color}-700 ring-1 ring-${col.color}-200`
                    : 'bg-white text-gray-600 hover:bg-gray-50'"
            >
                <component :is="col.icon" class="w-4 h-4" />
                {{ col.label }}
                <span v-if="ordersByStatus[col.key]?.length"
                      class="ml-1 px-1.5 py-0.5 text-xs font-bold rounded-full"
                      :class="`bg-${col.color}-100 text-${col.color}-700`">
                    {{ ordersByStatus[col.key].length }}
                </span>
            </button>
        </div>

        <!-- Mobile: Active tab orders -->
        <div class="lg:hidden space-y-3 mb-6">
            <div v-if="ordersByStatus[activeTab]?.length === 0" class="text-center py-12 text-gray-400 text-sm">
                Sin ordenes en esta columna
            </div>
            <ConsoleOrderCard
                v-for="order in ordersByStatus[activeTab]"
                :key="order.id"
                :order="order"
                :drivers="drivers"
                @cancel="openCancelModal"
            />
        </div>

        <!-- Desktop: Kanban columns -->
        <div class="hidden lg:grid lg:grid-cols-4 gap-4 mb-6">
            <div v-for="col in columns" :key="col.key" class="flex flex-col">
                <!-- Column header -->
                <div class="flex items-center gap-2 mb-3 px-1">
                    <div class="w-1 h-5 rounded-full" :class="`bg-${col.color}-400`"></div>
                    <component :is="col.icon" class="w-4 h-4" :class="`text-${col.color}-500`" />
                    <span class="text-sm font-semibold text-gray-700">{{ col.label }}</span>
                    <span v-if="ordersByStatus[col.key]?.length"
                          class="ml-auto px-2 py-0.5 text-xs font-bold rounded-full"
                          :class="`bg-${col.color}-100 text-${col.color}-700`">
                        {{ ordersByStatus[col.key].length }}
                    </span>
                </div>

                <!-- Column body -->
                <div class="flex-1 space-y-3 min-h-[200px] rounded-xl p-2 bg-gray-200/50 border-t-2" :class="col.borderColor">
                    <div v-if="ordersByStatus[col.key]?.length === 0" class="flex items-center justify-center h-32 text-gray-400 text-xs">
                        Sin ordenes
                    </div>
                    <ConsoleOrderCard
                        v-for="order in ordersByStatus[col.key]"
                        :key="order.id"
                        :order="order"
                        :drivers="drivers"
                        @cancel="openCancelModal"
                    />
                </div>
            </div>
        </div>

        <!-- Completed orders (collapsible) -->
        <div class="mt-6">
            <button
                @click="showCompleted = !showCompleted"
                class="flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors mb-3"
            >
                <component :is="showCompleted ? ChevronUp : ChevronDown" class="w-4 h-4" />
                Completadas hoy ({{ completedOrders.length }})
            </button>

            <div v-if="showCompleted" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div v-for="order in completedOrders" :key="order.id"
                     class="flex items-center gap-3 px-4 py-3 bg-white rounded-lg border border-gray-200 opacity-75">
                    <component
                        :is="order.status === 'delivered' ? PackageCheck : XCircle"
                        class="w-4 h-4 shrink-0"
                        :class="order.status === 'delivered' ? 'text-gray-400' : 'text-red-400'"
                    />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-bold text-gray-600">#{{ order.order_number }}</span>
                            <span class="text-xs text-gray-400">{{ order.customer_name }}</span>
                        </div>
                    </div>
                    <span class="text-sm font-semibold text-gray-600">{{ formatCurrency(order.total) }}</span>
                </div>
            </div>
        </div>

        <!-- Cancel Modal -->
        <AppModal :show="cancelModalOpen" @close="closeCancelModal" title="Cancelar orden">
            <div v-if="cancelTarget" class="space-y-4">
                <p class="text-gray-600">
                    Cancelar orden <strong class="font-mono">#{{ cancelTarget.order_number }}</strong> de
                    <strong>{{ cancelTarget.customer_name }}</strong>?
                </p>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Razon de cancelacion *</label>
                    <textarea
                        v-model="cancellationReason"
                        rows="3"
                        class="w-full rounded-lg border-gray-300 focus:ring-red-500 focus:border-red-500 text-sm"
                        placeholder="Escribe la razon de cancelacion..."
                    ></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <AppButton variant="ghost" @click="closeCancelModal">Volver</AppButton>
                    <AppButton
                        variant="danger"
                        @click="submitCancel"
                        :disabled="!cancellationReason.trim()"
                    >
                        Cancelar orden
                    </AppButton>
                </div>
            </div>
        </AppModal>
    </div>
</template>
