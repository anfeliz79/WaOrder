<script setup>
import { ref } from 'vue'
import { Link, Head, router, useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import {
    Bell, Plus, Edit2, Trash2, Eye, EyeOff, X,
    AlertTriangle, Info, CheckCircle, AlertOctagon, Globe
} from 'lucide-vue-next'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    notices: Object,
    tenants: Array,
    filters: Object,
})

// ── Filters ──
const typeFilter = ref(props.filters?.type || '')
const tenantFilter = ref(props.filters?.tenant_id || '')
const activeOnly = ref(!!props.filters?.active_only)

const applyFilters = () => {
    router.get('/superadmin/notices', {
        type: typeFilter.value || undefined,
        tenant_id: tenantFilter.value || undefined,
        active_only: activeOnly.value || undefined,
    }, { preserveState: true, replace: true })
}

// ── Modal ──
const showModal = ref(false)
const editTarget = ref(null)

const emptyForm = () => ({
    tenant_id: '',
    title: '',
    message: '',
    type: 'info',
    is_active: true,
    dismissible: true,
    starts_at: '',
    expires_at: '',
})

const form = useForm(emptyForm())

const openCreate = () => {
    editTarget.value = null
    form.reset()
    Object.assign(form, emptyForm())
    showModal.value = true
}

const openEdit = (notice) => {
    editTarget.value = notice
    form.tenant_id = notice.tenant_id || ''
    form.title = notice.title
    form.message = notice.message
    form.type = notice.type
    form.is_active = notice.is_active
    form.dismissible = notice.dismissible
    form.starts_at = notice.starts_at ? notice.starts_at.substring(0, 16) : ''
    form.expires_at = notice.expires_at ? notice.expires_at.substring(0, 16) : ''
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
    editTarget.value = null
    form.reset()
}

const submitForm = () => {
    const data = {
        ...form.data(),
        tenant_id: form.tenant_id || null,
        starts_at: form.starts_at || null,
        expires_at: form.expires_at || null,
    }

    if (editTarget.value) {
        router.put(`/superadmin/notices/${editTarget.value.id}`, data, {
            preserveScroll: true,
            onSuccess: closeModal,
        })
    } else {
        router.post('/superadmin/notices', data, {
            preserveScroll: true,
            onSuccess: closeModal,
        })
    }
}

const toggleActive = (notice) => {
    router.post(`/superadmin/notices/${notice.id}/toggle-active`, {}, { preserveScroll: true })
}

const destroy = (notice) => {
    if (window.confirm(`¿Eliminar el aviso "${notice.title}"?`)) {
        router.delete(`/superadmin/notices/${notice.id}`, { preserveScroll: true })
    }
}

// ── Helpers ──
const typeConfig = {
    info:    { label: 'Información', class: 'bg-blue-100 text-blue-700',   icon: Info },
    warning: { label: 'Advertencia', class: 'bg-amber-100 text-amber-700', icon: AlertTriangle },
    danger:  { label: 'Crítico',     class: 'bg-red-100 text-red-700',     icon: AlertOctagon },
    success: { label: 'Éxito',       class: 'bg-green-100 text-green-700', icon: CheckCircle },
}

const formatDate = (date) => {
    if (!date) return '—'
    return new Intl.DateTimeFormat('es-DO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date))
}

const isExpired = (notice) => {
    return notice.expires_at && new Date(notice.expires_at) < new Date()
}
</script>

<template>
    <Head title="Avisos" />

    <div>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Avisos a tenants</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Envía notificaciones y avisos a restaurantes específicos o a todos
                </p>
            </div>
            <button
                @click="openCreate"
                class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-[#0052FF] hover:bg-[#0041CC] transition-colors shadow-sm"
            >
                <Plus class="w-4 h-4" />
                Nuevo aviso
            </button>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 mb-6">
            <select
                v-model="typeFilter"
                @change="applyFilters"
                class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
            >
                <option value="">Todos los tipos</option>
                <option value="info">Información</option>
                <option value="warning">Advertencia</option>
                <option value="danger">Crítico</option>
                <option value="success">Éxito</option>
            </select>

            <select
                v-model="tenantFilter"
                @change="applyFilters"
                class="border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
            >
                <option value="">Todos los tenants</option>
                <option value="global">Globales (todos)</option>
                <option v-for="t in tenants" :key="t.id" :value="t.id">
                    {{ t.name }}
                </option>
            </select>

            <label class="inline-flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input
                    type="checkbox"
                    v-model="activeOnly"
                    @change="applyFilters"
                    class="rounded border-gray-300 text-[#0052FF] focus:ring-[#0052FF]/30"
                />
                Solo activos
            </label>
        </div>

        <!-- Table -->
        <div v-if="notices.data.length" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Aviso</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Destinatario</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Programación</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="notice in notices.data"
                            :key="notice.id"
                            class="hover:bg-gray-50/50 transition-colors"
                            :class="{ 'opacity-50': !notice.is_active || isExpired(notice) }"
                        >
                            <!-- Title + message -->
                            <td class="px-6 py-4 max-w-xs">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ notice.title }}</p>
                                <p class="text-xs text-gray-400 truncate mt-0.5">{{ notice.message }}</p>
                            </td>

                            <!-- Type -->
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="typeConfig[notice.type]?.class"
                                >
                                    <component :is="typeConfig[notice.type]?.icon" class="w-3 h-3" />
                                    {{ typeConfig[notice.type]?.label }}
                                </span>
                            </td>

                            <!-- Target -->
                            <td class="px-6 py-4">
                                <div v-if="notice.tenant" class="text-sm text-gray-700">{{ notice.tenant.name }}</div>
                                <div v-else class="inline-flex items-center gap-1 text-sm text-[#0052FF] font-medium">
                                    <Globe class="w-3.5 h-3.5" />
                                    Todos
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4">
                                <span v-if="isExpired(notice)" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                    Expirado
                                </span>
                                <span v-else-if="notice.is_active" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                    Activo
                                </span>
                                <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                    Inactivo
                                </span>
                            </td>

                            <!-- Dates -->
                            <td class="px-6 py-4">
                                <div class="text-xs text-gray-500 space-y-0.5">
                                    <div v-if="notice.starts_at">Desde: {{ formatDate(notice.starts_at) }}</div>
                                    <div v-if="notice.expires_at">Hasta: {{ formatDate(notice.expires_at) }}</div>
                                    <div v-if="!notice.starts_at && !notice.expires_at" class="text-gray-400">Permanente</div>
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        @click="toggleActive(notice)"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                                        :title="notice.is_active ? 'Desactivar' : 'Activar'"
                                    >
                                        <Eye v-if="notice.is_active" class="w-4 h-4" />
                                        <EyeOff v-else class="w-4 h-4" />
                                    </button>
                                    <button
                                        @click="openEdit(notice)"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                        title="Editar"
                                    >
                                        <Edit2 class="w-4 h-4" />
                                    </button>
                                    <button
                                        @click="destroy(notice)"
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
            <div v-if="notices.links && notices.links.length > 3" class="px-6 py-3 border-t border-gray-200 flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Mostrando {{ notices.meta?.from || notices.from }} a {{ notices.meta?.to || notices.to }} de {{ notices.meta?.total || notices.total }}
                </p>
                <nav class="flex items-center gap-1">
                    <template v-for="(link, i) in notices.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="px-3 py-1.5 text-sm rounded-lg transition-colors"
                            :class="link.active
                                ? 'bg-[#0052FF] text-white font-medium'
                                : 'text-gray-600 hover:bg-gray-100'"
                            v-html="link.label"
                            preserve-state
                        />
                        <span v-else class="px-3 py-1.5 text-sm text-gray-300" v-html="link.label" />
                    </template>
                </nav>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
            <Bell class="w-12 h-12 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 mb-2">No hay avisos</h3>
            <p class="text-sm text-gray-500 mb-4">
                {{ filters?.type || filters?.tenant_id ? 'No se encontraron avisos con los filtros aplicados.' : 'Crea un aviso para notificar a tus restaurantes.' }}
            </p>
            <button
                @click="openCreate"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-[#0052FF] hover:bg-[#0041CC] transition-colors"
            >
                <Plus class="w-4 h-4" />
                Crear primer aviso
            </button>
        </div>

        <!-- Create/Edit Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/40" @click="closeModal" />
                    <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto z-10">
                        <!-- Header -->
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                            <h3 class="text-lg font-bold text-gray-900">
                                {{ editTarget ? 'Editar aviso' : 'Nuevo aviso' }}
                            </h3>
                            <button @click="closeModal" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                                <X class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- Form -->
                        <div class="p-6 space-y-4">
                            <!-- Destinatario -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Destinatario</label>
                                <select
                                    v-model="form.tenant_id"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
                                >
                                    <option value="">Todos los restaurantes (global)</option>
                                    <option v-for="t in tenants" :key="t.id" :value="t.id">
                                        {{ t.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Tipo -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                                <div class="grid grid-cols-4 gap-2">
                                    <button
                                        v-for="(conf, key) in typeConfig"
                                        :key="key"
                                        type="button"
                                        @click="form.type = key"
                                        class="flex flex-col items-center gap-1 px-3 py-2 rounded-lg border text-xs font-medium transition-colors"
                                        :class="form.type === key
                                            ? 'border-[#0052FF] bg-blue-50 text-[#0052FF]'
                                            : 'border-gray-200 text-gray-500 hover:border-gray-300'"
                                    >
                                        <component :is="conf.icon" class="w-4 h-4" />
                                        {{ conf.label }}
                                    </button>
                                </div>
                            </div>

                            <!-- Título -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Título</label>
                                <input
                                    v-model="form.title"
                                    type="text"
                                    placeholder="Ej: Mantenimiento programado"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
                                />
                            </div>

                            <!-- Mensaje -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje</label>
                                <textarea
                                    v-model="form.message"
                                    rows="3"
                                    placeholder="Descripción del aviso..."
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none resize-none"
                                />
                            </div>

                            <!-- Schedule -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Desde (opcional)</label>
                                    <input
                                        v-model="form.starts_at"
                                        type="datetime-local"
                                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Hasta (opcional)</label>
                                    <input
                                        v-model="form.expires_at"
                                        type="datetime-local"
                                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
                                    />
                                </div>
                            </div>

                            <!-- Toggles -->
                            <div class="flex items-center gap-6 pt-2">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.is_active"
                                        class="rounded border-gray-300 text-[#0052FF] focus:ring-[#0052FF]/30"
                                    />
                                    Activo
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.dismissible"
                                        class="rounded border-gray-300 text-[#0052FF] focus:ring-[#0052FF]/30"
                                    />
                                    Descartable
                                </label>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                            <button
                                @click="closeModal"
                                class="flex-1 px-4 py-2.5 text-sm font-medium rounded-lg text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 transition-colors"
                            >
                                Cancelar
                            </button>
                            <button
                                @click="submitForm"
                                :disabled="form.processing || !form.title || !form.message"
                                class="flex-1 px-4 py-2.5 text-sm font-medium rounded-lg text-white bg-[#0052FF] hover:bg-[#0041CC] disabled:opacity-50 transition-colors"
                            >
                                {{ editTarget ? 'Actualizar' : 'Crear aviso' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
