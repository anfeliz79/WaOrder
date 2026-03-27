<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Search, Users } from 'lucide-vue-next';
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
let searchTimeout = null;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/customers', { search: value }, { preserveState: true, replace: true });
    }, 300);
});
</script>

<template>
    <div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3">
                <AppBadge variant="primary" size="md">{{ customers?.total ?? 0 }} clientes</AppBadge>
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
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="customer in customers?.data" :key="customer.id"
                        class="hover:bg-gray-50/50 transition-colors cursor-pointer hover:border-l-3 hover:border-l-primary-400"
                        @click="router.visit('/customers/' + customer.id)">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                     :class="getAvatarColor(customer.name)">
                                    {{ getInitials(customer.name) }}
                                </div>
                                <span class="font-medium text-gray-900 text-sm">{{ customer.name || 'Sin nombre' }}</span>
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
                 class="bg-surface rounded-xl border border-gray-200 shadow-sm p-4 cursor-pointer hover:shadow-md hover:border-l-3 hover:border-l-primary-400 transition-all duration-200"
                 @click="router.visit('/customers/' + customer.id)">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold"
                         :class="getAvatarColor(customer.name)">
                        {{ getInitials(customer.name) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-gray-900 text-sm">{{ customer.name || 'Sin nombre' }}</p>
                        <p class="text-xs text-gray-400">{{ customer.phone }}</p>
                    </div>
                    <AppBadge variant="primary" size="xs">{{ customer.orders_count ?? 0 }} ordenes</AppBadge>
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
        <AppPagination :data="customers" routePath="/customers" :routeParams="{ search: filters?.search }" />
    </div>
</template>
