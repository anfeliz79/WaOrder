<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Search, Users, ShieldBan, ShieldCheck, X } from 'lucide-vue-next';
import AppCard from '@/Components/AppCard.vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppPagination from '@/Components/AppPagination.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import { formatCurrency, formatRelativeTime, formatDate, getInitials, getAvatarColor } from '@/Utils/formatters';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    customers: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const showBlocked = ref(props.filters?.blocked || false);
let searchTimeout = null;

const applyFilters = () => {
    const params = {};
    if (search.value) params.search = search.value;
    if (showBlocked.value) params.blocked = '1';
    router.get('/customers', params, { preserveState: true, replace: true });
};

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch(showBlocked, () => {
    applyFilters();
});

// Block modal
const blockModal = ref(false);
const blockTarget = ref(null);
const blockForm = useForm({ blocked_reason: '' });

const openBlockModal = (customer, event) => {
    event.stopPropagation();
    blockTarget.value = customer;
    blockForm.blocked_reason = '';
    blockModal.value = true;
};

const confirmBlock = () => {
    blockForm.post(`/customers/${blockTarget.value.id}/toggle-block`, {
        preserveScroll: true,
        onSuccess: () => {
            blockModal.value = false;
            blockTarget.value = null;
        },
    });
};

const unblockCustomer = (customer, event) => {
    event.stopPropagation();
    router.post(`/customers/${customer.id}/toggle-block`, {}, { preserveScroll: true });
};
</script>

<template>
    <div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3">
                <AppBadge variant="primary" size="md">{{ customers?.total ?? 0 }} clientes</AppBadge>
                <label class="inline-flex items-center gap-2 cursor-pointer text-sm text-gray-600 select-none">
                    <input type="checkbox" v-model="showBlocked"
                           class="rounded border-gray-300 text-red-600 focus:ring-red-500/20" />
                    <ShieldBan class="w-3.5 h-3.5 text-red-500" />
                    Bloqueados
                </label>
            </div>
            <!-- Search -->
            <div class="relative w-full sm:w-72">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input v-model="search" type="text" placeholder="Buscar por nombre o telefono..."
                       class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-colors" />
            </div>
        </div>

        <!-- Desktop table -->
        <AppCard noPadding class="hidden md:block">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="bg-gray-50/80">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Telefono</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Ordenes</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Total gastado</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Ultimo pedido</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider w-20">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="customer in customers?.data" :key="customer.id"
                        class="hover:bg-gray-50/50 transition-colors cursor-pointer hover:border-l-3"
                        :class="customer.is_blocked ? 'bg-red-50/30 hover:border-l-red-400' : 'hover:border-l-primary-400'"
                        @click="router.visit('/customers/' + customer.id)">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                     :class="customer.is_blocked ? 'bg-red-100 text-red-700' : getAvatarColor(customer.name)">
                                    {{ getInitials(customer.name) }}
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-sm" :class="customer.is_blocked ? 'text-red-700' : 'text-gray-900'">
                                        {{ customer.name || 'Sin nombre' }}
                                    </span>
                                    <AppBadge v-if="customer.is_blocked" variant="red" size="xs">
                                        Bloqueado
                                    </AppBadge>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ customer.phone }}</td>
                        <td class="px-6 py-4 text-center">
                            <AppBadge variant="primary" size="xs">
                                {{ customer.orders_count ?? customer.total_orders ?? 0 }}
                            </AppBadge>
                        </td>
                        <td class="px-6 py-4 text-right font-semibold text-sm text-gray-900">
                            {{ formatCurrency(customer.total_spent || 0) }}
                        </td>
                        <td class="px-6 py-4 text-right text-sm text-gray-500" :title="formatDate(customer.last_order_at)">
                            {{ customer.last_order_at ? formatRelativeTime(customer.last_order_at) : 'Nunca' }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button v-if="customer.is_blocked"
                                    @click="unblockCustomer(customer, $event)"
                                    class="inline-flex items-center gap-1 text-xs text-emerald-600 hover:text-emerald-700 font-medium transition-colors"
                                    title="Desbloquear cliente">
                                <ShieldCheck class="w-4 h-4" />
                            </button>
                            <button v-else
                                    @click="openBlockModal(customer, $event)"
                                    class="inline-flex items-center gap-1 text-xs text-gray-400 hover:text-red-600 font-medium transition-colors"
                                    title="Bloquear cliente">
                                <ShieldBan class="w-4 h-4" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <AppEmptyState
                v-if="!customers?.data?.length"
                :icon="Users"
                title="Sin clientes"
                description="Los clientes apareceran aqui cuando envien mensajes a tu WhatsApp"
            />
        </AppCard>

        <!-- Mobile cards -->
        <div class="md:hidden space-y-3">
            <div v-for="customer in customers?.data" :key="customer.id"
                 class="bg-surface rounded-xl border shadow-sm p-4 cursor-pointer hover:shadow-md hover:border-l-3 transition-all duration-200"
                 :class="customer.is_blocked ? 'border-red-200 bg-red-50/30 hover:border-l-red-400' : 'border-gray-200 hover:border-l-primary-400'"
                 @click="router.visit('/customers/' + customer.id)">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold"
                         :class="customer.is_blocked ? 'bg-red-100 text-red-700' : getAvatarColor(customer.name)">
                        {{ getInitials(customer.name) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="font-medium text-sm" :class="customer.is_blocked ? 'text-red-700' : 'text-gray-900'">
                                {{ customer.name || 'Sin nombre' }}
                            </p>
                            <AppBadge v-if="customer.is_blocked" variant="red" size="xs">Bloqueado</AppBadge>
                        </div>
                        <p class="text-xs text-gray-400">{{ customer.phone }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <AppBadge variant="primary" size="xs">{{ customer.orders_count ?? 0 }} ordenes</AppBadge>
                        <button v-if="customer.is_blocked"
                                @click="unblockCustomer(customer, $event)"
                                class="text-emerald-600 hover:text-emerald-700 transition-colors"
                                title="Desbloquear">
                            <ShieldCheck class="w-4 h-4" />
                        </button>
                        <button v-else
                                @click="openBlockModal(customer, $event)"
                                class="text-gray-400 hover:text-red-600 transition-colors"
                                title="Bloquear">
                            <ShieldBan class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Total: <strong class="text-gray-900">{{ formatCurrency(customer.total_spent || 0) }}</strong></span>
                    <span class="text-gray-400 text-xs">{{ customer.last_order_at ? formatRelativeTime(customer.last_order_at) : 'Sin pedidos' }}</span>
                </div>
            </div>

            <AppEmptyState
                v-if="!customers?.data?.length"
                :icon="Users"
                title="Sin clientes"
                description="Los clientes apareceran aqui cuando envien mensajes a tu WhatsApp"
            />
        </div>

        <!-- Pagination -->
        <AppPagination :data="customers" routePath="/customers" :routeParams="{ search: filters?.search, blocked: filters?.blocked }" />

        <!-- Block Modal -->
        <Teleport to="body">
            <div v-if="blockModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/40" @click="blockModal = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                    <button @click="blockModal = false" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 transition-colors">
                        <X class="w-5 h-5" />
                    </button>

                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                            <ShieldBan class="w-5 h-5 text-red-600" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Bloquear cliente</h3>
                            <p class="text-sm text-gray-500">{{ blockTarget?.name || blockTarget?.phone }}</p>
                        </div>
                    </div>

                    <p class="text-sm text-gray-600 mb-4">
                        El cliente no podra realizar pedidos por WhatsApp y recibira un mensaje de cuenta suspendida.
                    </p>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Razon del bloqueo (opcional)</label>
                        <input v-model="blockForm.blocked_reason" type="text"
                               placeholder="Ej: Pedidos falsos, comportamiento inapropiado..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-colors" />
                    </div>

                    <div class="flex items-center gap-3 justify-end">
                        <button @click="blockModal = false"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                            Cancelar
                        </button>
                        <button @click="confirmBlock"
                                :disabled="blockForm.processing"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors disabled:opacity-50">
                            Bloquear cliente
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
