<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft, Package, User, MapPin, Phone, Truck, Clock,
    DollarSign, CreditCard, FileText, ChefHat, CheckCircle,
    PackageCheck, XCircle, Send, Pencil, Store,
} from 'lucide-vue-next';
import AppCard from '@/Components/AppCard.vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppButton from '@/Components/AppButton.vue';
import AppModal from '@/Components/AppModal.vue';
import { formatCurrency, formatDate, formatRelativeTime } from '@/Utils/formatters';
import { getStatusLabel, statusConfig } from '@/Utils/orderStatus';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    order: Object,
});

const order = ref(props.order);

watch(() => props.order, (newOrder) => {
    order.value = newOrder;
}, { deep: true });

const badgeVariant = (status) => {
    const map = { confirmed: 'blue', in_preparation: 'amber', ready: 'emerald', out_for_delivery: 'violet', delivered: 'gray', cancelled: 'red' };
    return map[status] || 'gray';
};

const statusIcon = (status) => {
    const map = { confirmed: '🔵', in_preparation: '🟡', ready: '🟢', out_for_delivery: '🟠', delivered: '✅', cancelled: '🔴' };
    return map[status] || '⚪';
};

const changedByLabel = (type) => {
    const map = { system: 'Sistema', staff: 'Staff', customer: 'Cliente', driver: 'Mensajero' };
    return map[type] || type;
};

const paymentLabel = (method) => {
    const map = { cash: 'Efectivo', transfer: 'Transferencia', card_link: 'Tarjeta' };
    return map[method] || method;
};

// Actions
const actionConfig = {
    confirmed: { action: 'prepare', label: 'Preparar', icon: ChefHat, variant: 'primary' },
    in_preparation: { action: 'ready', label: 'Marcar Listo', icon: CheckCircle, variant: 'success' },
    out_for_delivery: { action: 'deliver', label: 'Marcar Entregado', icon: PackageCheck, variant: 'secondary' },
};

const updateStatus = (action) => {
    router.put(`/orders/${order.value.id}/status`, { action }, {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['order'] }),
    });
};

// Cancel
const showCancel = ref(false);
const cancelForm = useForm({ action: 'cancel', cancellation_reason: '' });

const submitCancel = () => {
    cancelForm.put(`/orders/${order.value.id}/status`, {
        preserveScroll: true,
        onSuccess: () => {
            showCancel.value = false;
            router.reload({ only: ['order'] });
        },
    });
};

const canCancel = computed(() => ['confirmed', 'in_preparation', 'ready', 'out_for_delivery'].includes(order.value.status));

const modifierSummary = (item) => {
    const mods = item.modifiers || {};
    const parts = [];
    for (const [, sel] of Object.entries(mods.variants || {})) {
        parts.push(sel.name);
    }
    for (const opt of mods.optionals || []) {
        parts.push(opt.name);
    }
    return parts.join(', ');
};

// Address edit
const editingAddress = ref(false);
const addressValue = ref(order.value.delivery_address || '');
const saveAddress = () => {
    router.patch(`/orders/${order.value.id}/delivery-address`, { delivery_address: addressValue.value }, {
        preserveScroll: true,
        onSuccess: () => { editingAddress.value = false; },
    });
};
</script>

<template>
    <div>
        <!-- Back -->
        <Link href="/orders" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition-colors mb-4">
            <ArrowLeft class="w-4 h-4" />
            Volver a ordenes
        </Link>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 font-mono">#{{ order.order_number }}</h1>
                    <AppBadge :variant="badgeVariant(order.status)" size="md" dot>
                        {{ getStatusLabel(order.status) }}
                    </AppBadge>
                </div>
                <p class="text-sm text-gray-500 mt-1">{{ formatDate(order.created_at) }} - {{ formatRelativeTime(order.created_at) }}</p>
            </div>
            <div class="flex items-center gap-2">
                <AppButton v-if="canCancel" size="sm" variant="danger" @click="showCancel = true">
                    <XCircle class="w-4 h-4" /> Cancelar
                </AppButton>
                <AppButton
                    v-if="order.status === 'ready' && order.delivery_type === 'pickup'"
                    size="sm" variant="success"
                    @click="updateStatus('pickup')"
                >
                    <Store class="w-4 h-4" /> Entregar al Cliente
                </AppButton>
                <AppButton
                    v-else-if="actionConfig[order.status]"
                    size="sm" :variant="actionConfig[order.status].variant"
                    @click="updateStatus(actionConfig[order.status].action)"
                >
                    <component :is="actionConfig[order.status].icon" class="w-4 h-4" />
                    {{ actionConfig[order.status].label }}
                </AppButton>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Order details -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Items card -->
                <AppCard>
                    <template #header>
                        <div class="flex items-center gap-2">
                            <Package class="w-4 h-4 text-primary-500" />
                            <h3 class="font-semibold text-gray-900">Productos ({{ order.items?.length || 0 }})</h3>
                        </div>
                    </template>

                    <div class="divide-y divide-gray-100">
                        <div v-for="item in order.items" :key="item.id" class="flex items-center justify-between py-3">
                            <div class="flex items-center gap-3">
                                <span class="w-8 h-8 rounded-lg bg-primary-50 flex items-center justify-center text-sm font-bold text-primary-600">
                                    {{ item.quantity }}x
                                </span>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ item.name }}</p>
                                    <p v-if="modifierSummary(item)" class="text-xs text-gray-500">{{ modifierSummary(item) }}</p>
                                    <p class="text-xs text-gray-400">{{ formatCurrency(item.unit_price) }} c/u</p>
                                </div>
                            </div>
                            <span class="text-sm font-semibold text-gray-700">{{ formatCurrency(item.subtotal) }}</span>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="border-t-2 border-gray-100 pt-3 mt-2 space-y-1.5">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Subtotal</span>
                            <span class="text-gray-700">{{ formatCurrency(order.subtotal) }}</span>
                        </div>
                        <div v-if="Number(order.delivery_fee) > 0" class="flex justify-between text-sm">
                            <span class="text-gray-500">Delivery</span>
                            <span class="text-gray-700">{{ formatCurrency(order.delivery_fee) }}</span>
                        </div>
                        <div v-if="Number(order.tax) > 0" class="flex justify-between text-sm">
                            <span class="text-gray-500">Impuesto</span>
                            <span class="text-gray-700">{{ formatCurrency(order.tax) }}</span>
                        </div>
                        <div class="flex justify-between text-lg font-bold pt-2 border-t border-gray-100">
                            <span class="text-gray-900">Total</span>
                            <span class="text-primary-600">{{ formatCurrency(order.total) }}</span>
                        </div>
                    </div>
                </AppCard>

                <!-- Timeline card -->
                <AppCard>
                    <template #header>
                        <div class="flex items-center gap-2">
                            <Clock class="w-4 h-4 text-primary-500" />
                            <h3 class="font-semibold text-gray-900">Historial de estados</h3>
                        </div>
                    </template>

                    <div class="relative pl-6">
                        <div class="absolute left-2 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                        <div v-for="(entry, idx) in order.status_history" :key="idx" class="relative pb-5 last:pb-0">
                            <div class="absolute -left-4 top-1 w-4 h-4 rounded-full border-2 border-white bg-gray-300 z-10"
                                 :class="idx === 0 ? 'bg-primary-500' : 'bg-gray-300'"></div>
                            <div class="ml-4">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ statusIcon(entry.to_status) }} {{ getStatusLabel(entry.to_status) }}
                                    </span>
                                    <span class="text-xs text-gray-400">{{ changedByLabel(entry.changed_by_type) }}</span>
                                </div>
                                <p class="text-xs text-gray-500">{{ formatDate(entry.created_at) }}</p>
                                <p v-if="entry.note" class="text-xs text-gray-400 mt-1 italic">{{ entry.note }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="!order.status_history?.length" class="text-sm text-gray-400 text-center py-4">
                        Sin historial de estados
                    </div>
                </AppCard>
            </div>

            <!-- Right: Info cards -->
            <div class="space-y-4">
                <!-- Customer card -->
                <AppCard>
                    <template #header>
                        <div class="flex items-center gap-2">
                            <User class="w-4 h-4 text-primary-500" />
                            <h3 class="font-semibold text-gray-900">Cliente</h3>
                        </div>
                    </template>
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <User class="w-4 h-4 text-gray-400" />
                            <span class="text-sm text-gray-700">{{ order.customer_name }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Phone class="w-4 h-4 text-gray-400" />
                            <a :href="`tel:${order.customer_phone}`" class="text-sm text-primary-600 hover:underline">
                                {{ order.customer_phone }}
                            </a>
                        </div>
                        <div v-if="order.customer" class="pt-2 border-t border-gray-100">
                            <Link :href="`/customers/${order.customer.id}`" class="text-xs text-primary-600 hover:underline">
                                Ver perfil del cliente
                            </Link>
                        </div>
                    </div>
                </AppCard>

                <!-- Delivery card -->
                <AppCard v-if="order.delivery_type === 'delivery'">
                    <template #header>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <MapPin class="w-4 h-4 text-primary-500" />
                                <h3 class="font-semibold text-gray-900">Entrega</h3>
                            </div>
                            <button @click="editingAddress = true" class="text-gray-400 hover:text-primary-500">
                                <Pencil class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </template>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-700">{{ order.delivery_address || 'Sin direccion' }}</p>
                        <div v-if="order.delivery_latitude && order.delivery_longitude">
                            <a :href="`https://www.google.com/maps/dir/?api=1&destination=${order.delivery_latitude},${order.delivery_longitude}`"
                               target="_blank"
                               class="text-xs text-primary-600 hover:underline flex items-center gap-1">
                                <MapPin class="w-3 h-3" /> Abrir en Google Maps
                            </a>
                        </div>
                    </div>
                </AppCard>

                <!-- Driver card -->
                <AppCard v-if="order.driver">
                    <template #header>
                        <div class="flex items-center gap-2">
                            <Truck class="w-4 h-4 text-primary-500" />
                            <h3 class="font-semibold text-gray-900">Mensajero</h3>
                        </div>
                    </template>
                    <div class="space-y-2">
                        <p class="text-sm font-medium text-gray-700">{{ order.driver.name }}</p>
                        <p class="text-xs text-gray-400">{{ order.driver.phone }}</p>
                    </div>
                </AppCard>

                <!-- Payment card -->
                <AppCard>
                    <template #header>
                        <div class="flex items-center gap-2">
                            <CreditCard class="w-4 h-4 text-primary-500" />
                            <h3 class="font-semibold text-gray-900">Pago</h3>
                        </div>
                    </template>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Metodo</span>
                            <AppBadge :variant="order.payment_method === 'cash' ? 'amber' : 'blue'" size="xs">
                                {{ paymentLabel(order.payment_method) }}
                            </AppBadge>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Total</span>
                            <span class="text-sm font-bold text-gray-900">{{ formatCurrency(order.total) }}</span>
                        </div>
                    </div>
                </AppCard>

                <!-- Notes card -->
                <AppCard v-if="order.notes">
                    <template #header>
                        <div class="flex items-center gap-2">
                            <FileText class="w-4 h-4 text-primary-500" />
                            <h3 class="font-semibold text-gray-900">Notas</h3>
                        </div>
                    </template>
                    <p class="text-sm text-gray-600 italic">{{ order.notes }}</p>
                </AppCard>

                <!-- Cancellation reason -->
                <AppCard v-if="order.status === 'cancelled' && order.cancellation_reason">
                    <template #header>
                        <div class="flex items-center gap-2">
                            <XCircle class="w-4 h-4 text-red-500" />
                            <h3 class="font-semibold text-red-700">Cancelacion</h3>
                        </div>
                    </template>
                    <p class="text-sm text-red-600">{{ order.cancellation_reason }}</p>
                </AppCard>
            </div>
        </div>

        <!-- Cancel modal -->
        <AppModal :show="showCancel" title="Cancelar Orden" @close="showCancel = false">
            <div class="space-y-4">
                <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                    <p class="text-sm text-red-800">
                        Vas a cancelar la orden <strong>#{{ order.order_number }}</strong>. El cliente sera notificado.
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Razon de la cancelacion *</label>
                    <textarea v-model="cancelForm.cancellation_reason" rows="3"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
                        placeholder="Ej: Cliente solicito cancelacion, producto no disponible..." />
                    <p v-if="cancelForm.errors.cancellation_reason" class="text-red-500 text-xs mt-1">{{ cancelForm.errors.cancellation_reason }}</p>
                </div>
            </div>
            <template #footer>
                <AppButton variant="secondary" size="sm" @click="showCancel = false">Volver</AppButton>
                <AppButton variant="danger" size="sm" @click="submitCancel" :loading="cancelForm.processing"
                           :disabled="!cancelForm.cancellation_reason.trim()">
                    Cancelar Orden
                </AppButton>
            </template>
        </AppModal>

        <!-- Address edit modal -->
        <AppModal :show="editingAddress" title="Editar Direccion" @close="editingAddress = false">
            <textarea v-model="addressValue" rows="3"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="Direccion..." />
            <template #footer>
                <AppButton variant="secondary" size="sm" @click="editingAddress = false">Cancelar</AppButton>
                <AppButton variant="primary" size="sm" @click="saveAddress">Guardar</AppButton>
            </template>
        </AppModal>
    </div>
</template>
