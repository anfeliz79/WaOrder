<script setup>
import { ref, watch } from 'vue'
import { Link, Head, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import {
    Search, Plus, Pencil, Trash2, Store,
    ToggleLeft, ToggleRight,
} from 'lucide-vue-next'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    tenants: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || '')

let searchTimeout = null
watch(search, (val) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get('/superadmin/tenants', { search: val, status: status.value }, { preserveState: true, replace: true })
    }, 300)
})

const changeStatus = (val) => {
    status.value = val
    router.get('/superadmin/tenants', { search: search.value, status: val }, { preserveState: true, replace: true })
}

const toggleActive = (tenant) => {
    router.post(`/superadmin/tenants/${tenant.id}/toggle-active`, {}, { preserveScroll: true })
}

const deleteTenant = (tenant) => {
    if (window.confirm(`¿Estás seguro de eliminar el restaurante "${tenant.name}"? Esta acción no se puede deshacer.`)) {
        router.delete(`/superadmin/tenants/${tenant.id}`, { preserveScroll: true })
    }
}

const planBadgeClass = (plan) => {
    const map = {
        free: 'bg-gray-100 text-gray-700',
        starter: 'bg-blue-100 text-blue-700',
        pro: 'bg-amber-100 text-amber-700',
    }
    return map[plan] || 'bg-gray-100 text-gray-700'
}

const planLabel = (plan) => {
    const map = { free: 'Free', starter: 'Starter', pro: 'Pro' }
    return map[plan] || plan
}
</script>

<template>
    <Head title="Restaurantes" />

    <div>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Restaurantes</h1>
            <Link
                href="/superadmin/tenants/create"
                class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition-colors"
            >
                <Plus class="w-4 h-4" />
                Nuevo Restaurante
            </Link>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3 mb-6">
            <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Buscar por nombre o slug..."
                    class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                />
            </div>
            <select
                :value="status"
                @change="changeStatus($event.target.value)"
                class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
            >
                <option value="">Todos</option>
                <option value="active">Activos</option>
                <option value="inactive">Inactivos</option>
            </select>
        </div>

        <!-- Table -->
        <div v-if="tenants.data.length" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Plan</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Sucursales</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Usuarios</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Pedidos</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="tenant in tenants.data" :key="tenant.id" class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 bg-amber-100 rounded-lg flex items-center justify-center shrink-0">
                                        <Store class="w-4 h-4 text-amber-600" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ tenant.name }}</p>
                                        <p class="text-xs text-gray-400">{{ tenant.slug }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="planBadgeClass(tenant.subscription_plan)">
                                    {{ planLabel(tenant.subscription_plan) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center text-sm text-gray-600">
                                {{ tenant.branches_count }}
                            </td>
                            <td class="px-6 py-4 text-center text-sm text-gray-600">
                                {{ tenant.users_count }}
                            </td>
                            <td class="px-6 py-4 text-center text-sm text-gray-600">
                                {{ tenant.orders_count }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button
                                    @click="toggleActive(tenant)"
                                    class="inline-flex items-center gap-1.5 text-sm font-medium transition-colors"
                                    :class="tenant.is_active ? 'text-green-600 hover:text-green-700' : 'text-gray-400 hover:text-gray-500'"
                                    :title="tenant.is_active ? 'Desactivar' : 'Activar'"
                                >
                                    <ToggleRight v-if="tenant.is_active" class="w-5 h-5" />
                                    <ToggleLeft v-else class="w-5 h-5" />
                                    <span class="text-xs">{{ tenant.is_active ? 'Activo' : 'Inactivo' }}</span>
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="`/superadmin/tenants/${tenant.id}/edit`"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors"
                                        title="Editar"
                                    >
                                        <Pencil class="w-4 h-4" />
                                    </Link>
                                    <button
                                        @click="deleteTenant(tenant)"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                        title="Eliminar"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="tenants.links && tenants.links.length > 3" class="px-6 py-3 border-t border-gray-200 flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Mostrando {{ tenants.meta?.from || tenants.from }} a {{ tenants.meta?.to || tenants.to }} de {{ tenants.meta?.total || tenants.total }} resultados
                </p>
                <nav class="flex items-center gap-1">
                    <template v-for="(link, i) in tenants.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="px-3 py-1.5 text-sm rounded-lg transition-colors"
                            :class="link.active
                                ? 'bg-amber-600 text-white font-medium'
                                : 'text-gray-600 hover:bg-gray-100'"
                            v-html="link.label"
                            preserve-state
                        />
                        <span
                            v-else
                            class="px-3 py-1.5 text-sm text-gray-300"
                            v-html="link.label"
                        />
                    </template>
                </nav>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
            <Store class="w-12 h-12 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">No hay restaurantes registrados</h3>
            <p class="text-sm text-gray-500 mb-6">Crea el primer restaurante para comenzar.</p>
            <Link
                href="/superadmin/tenants/create"
                class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition-colors"
            >
                <Plus class="w-4 h-4" />
                Nuevo Restaurante
            </Link>
        </div>
    </div>
</template>
