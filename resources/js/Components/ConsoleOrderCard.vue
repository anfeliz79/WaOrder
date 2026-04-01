<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Phone,
    MapPin,
    Bike,
    ShoppingBag,
    Clock,
    Send,
    XCircle,
    ChevronRight,
    User,
    Truck,
} from 'lucide-vue-next';
import AppBadge from '@/Components/AppBadge.vue';
import { formatCurrency, formatRelativeTime } from '@/Utils/formatters';

const props = defineProps({
    order: Object,
    drivers: { type: Array, default: () => [] },
});

const emit = defineEmits(['cancel']);

const isDelivery = computed(() => props.order.delivery_type === 'delivery');

const itemSummary = computed(() => {
    const items = props.order.items || [];
    const count = items.reduce((sum, i) => sum + i.quantity, 0);
    if (items.length <= 2) {
        return items.map(i => `${i.quantity}x ${i.name}`).join(', ');
    }
    return `${count} items`;
});

const timeElapsed = computed(() => formatRelativeTime(props.order.confirmed_at || props.order.created_at));

const actionConfig = computed(() => {
    switch (props.order.status) {
        case 'confirmed':
            return { label: 'Preparar', action: 'prepare', variant: 'amber' };
        case 'in_preparation':
            return { label: 'Listo', action: 'ready', variant: 'emerald' };
        case 'ready':
            if (!isDelivery.value) {
                return { label: 'Entregar', action: 'pickup', variant: 'emerald' };
            }
            return null; // Driver assignment handled separately
        case 'out_for_delivery':
            return { label: 'Entregado', action: 'deliver', variant: 'emerald' };
        default:
            return null;
    }
});

const updateStatus = (action) => {
    router.put(`/orders/${props.order.id}/status`, { action }, {
        preserveScroll: true,
    });
};

const assignDriver = (driverId) => {
    router.post(`/orders/${props.order.id}/assign-driver`, { driver_id: driverId }, {
        preserveScroll: true,
    });
};

const resendToDriver = () => {
    router.post(`/orders/${props.order.id}/send-to-driver`, {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow duration-200">
        <!-- Card header -->
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="font-mono font-bold text-gray-900 text-sm">#{{ order.order_number }}</span>
                <AppBadge :variant="isDelivery ? 'violet' : 'blue'" size="xs">
                    <component :is="isDelivery ? Bike : ShoppingBag" class="w-3 h-3 mr-0.5" />
                    {{ isDelivery ? 'Delivery' : 'Pickup' }}
                </AppBadge>
            </div>
            <div class="flex items-center gap-1 text-xs text-gray-400">
                <Clock class="w-3 h-3" />
                {{ timeElapsed }}
            </div>
        </div>

        <!-- Card body -->
        <div class="px-4 py-3 space-y-2">
            <!-- Customer -->
            <div class="flex items-center gap-2">
                <User class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                <span class="text-sm font-medium text-gray-900 truncate">{{ order.customer_name }}</span>
                <a :href="`tel:${order.customer_phone}`"
                   class="text-gray-400 hover:text-primary-600 transition-colors ml-auto shrink-0">
                    <Phone class="w-3.5 h-3.5" />
                </a>
            </div>

            <!-- Items -->
            <p class="text-xs text-gray-500 truncate pl-5.5">{{ itemSummary }}</p>

            <!-- Address (delivery only) -->
            <div v-if="isDelivery && order.delivery_address" class="flex items-start gap-2">
                <MapPin class="w-3.5 h-3.5 text-gray-400 shrink-0 mt-0.5" />
                <p class="text-xs text-gray-500 line-clamp-2">{{ order.delivery_address }}</p>
            </div>

            <!-- Driver info (if assigned) -->
            <div v-if="order.driver" class="flex items-center gap-2">
                <Truck class="w-3.5 h-3.5 text-violet-400 shrink-0" />
                <span class="text-xs text-violet-600 font-medium">{{ order.driver.name }}</span>
            </div>

            <!-- Total -->
            <div class="flex items-center justify-between pt-1 border-t border-gray-50">
                <span class="text-lg font-bold text-gray-900">{{ formatCurrency(order.total) }}</span>
                <AppBadge v-if="order.payment_method" :variant="order.payment_method === 'cash' ? 'emerald' : 'blue'" size="xs">
                    {{ order.payment_method === 'cash' ? 'Efectivo' : order.payment_method === 'transfer' ? 'Transfer' : 'Tarjeta' }}
                </AppBadge>
            </div>
        </div>

        <!-- Card actions -->
        <div class="px-4 py-3 bg-gray-50/80 border-t border-gray-100 space-y-2">
            <!-- Primary action button -->
            <button
                v-if="actionConfig"
                @click="updateStatus(actionConfig.action)"
                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold text-white transition-all duration-150 active:scale-[0.98]"
                :class="{
                    'bg-amber-500 hover:bg-amber-600 shadow-amber-500/25 shadow-sm': actionConfig.variant === 'amber',
                    'bg-emerald-500 hover:bg-emerald-600 shadow-emerald-500/25 shadow-sm': actionConfig.variant === 'emerald',
                }"
            >
                {{ actionConfig.label }}
                <ChevronRight class="w-4 h-4" />
            </button>

            <!-- Driver assignment (ready + delivery) -->
            <div v-if="order.status === 'ready' && isDelivery && !order.driver" class="space-y-1.5">
                <select
                    @change="assignDriver(Number($event.target.value)); $event.target.value = ''"
                    class="w-full text-sm rounded-lg border-gray-300 focus:ring-emerald-500 focus:border-emerald-500 py-2"
                >
                    <option value="" selected disabled>Asignar mensajero...</option>
                    <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                        {{ driver.name }}
                    </option>
                </select>
            </div>

            <!-- Resend to driver (out_for_delivery) -->
            <button
                v-if="order.status === 'out_for_delivery' && order.driver"
                @click="resendToDriver"
                class="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 transition-colors"
            >
                <Send class="w-3 h-3" />
                Reenviar al mensajero
            </button>

            <!-- Cancel button -->
            <button
                @click="$emit('cancel', order)"
                class="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-red-600 hover:bg-red-50 transition-colors"
            >
                <XCircle class="w-3 h-3" />
                Cancelar
            </button>
        </div>
    </div>
</template>
