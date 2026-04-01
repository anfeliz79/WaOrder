<script setup>
import { ref } from 'vue'
import { Link, Head, useForm, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import {
    Save, ArrowLeft, Trash2, Users, MapPin,
    ShoppingBag, DollarSign, Calendar, LogIn,
} from 'lucide-vue-next'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    tenant: Object,
    users: Array,
    branches: Array,
    orderStats: Object,
})

const form = useForm({
    name: props.tenant.name,
    slug: props.tenant.slug,
    timezone: props.tenant.timezone,
    currency: props.tenant.currency,
    subscription_plan: props.tenant.subscription_plan,
    is_active: props.tenant.is_active,
    whatsapp_phone_number_id: props.tenant.whatsapp_phone_number_id || '',
    whatsapp_business_account_id: props.tenant.whatsapp_business_account_id || '',
    whatsapp_access_token: '',
})

const activeTab = ref('general')

const tabs = [
    { key: 'general', label: 'Informacion General' },
    { key: 'stats', label: 'Estadisticas' },
    { key: 'users', label: 'Usuarios' },
    { key: 'branches', label: 'Sucursales' },
]

const submit = () => {
    form.put(`/superadmin/tenants/${props.tenant.id}`)
}

const deleteTenant = () => {
    if (window.confirm(`¿Estás seguro de eliminar "${props.tenant.name}"? Se eliminarán todos los datos asociados. Esta acción no se puede deshacer.`)) {
        router.delete(`/superadmin/tenants/${props.tenant.id}`)
    }
}

const impersonateTenant = () => {
    router.post(`/superadmin/tenants/${props.tenant.id}/impersonate`)
}

const timezones = [
    { value: 'America/Santo_Domingo', label: 'America/Santo_Domingo (AST)' },
    { value: 'America/New_York', label: 'America/New_York (EST)' },
    { value: 'America/Chicago', label: 'America/Chicago (CST)' },
    { value: 'America/Los_Angeles', label: 'America/Los_Angeles (PST)' },
    { value: 'America/Bogota', label: 'America/Bogota (COT)' },
    { value: 'America/Mexico_City', label: 'America/Mexico_City (CST)' },
    { value: 'Europe/Madrid', label: 'Europe/Madrid (CET)' },
]

const currencies = [
    { value: 'DOP', label: 'DOP — Peso Dominicano' },
    { value: 'USD', label: 'USD — Dolar Estadounidense' },
    { value: 'EUR', label: 'EUR — Euro' },
    { value: 'MXN', label: 'MXN — Peso Mexicano' },
    { value: 'COP', label: 'COP — Peso Colombiano' },
]

const plans = [
    { value: 'free', label: 'Free' },
    { value: 'starter', label: 'Starter' },
    { value: 'pro', label: 'Pro' },
]

const planBadgeClass = (plan) => {
    const map = {
        free: 'bg-gray-100 text-gray-700',
        starter: 'bg-blue-100 text-blue-700',
        pro: 'bg-amber-100 text-amber-700',
    }
    return map[plan] || 'bg-gray-100 text-gray-700'
}

const roleBadgeClass = (role) => {
    return role === 'admin' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'
}

const formatCurrency = (amount) => {
    const num = Number(amount) || 0
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: props.tenant.currency || 'DOP',
        minimumFractionDigits: 0,
    }).format(num)
}

const formatDate = (date) => {
    if (!date) return '—'
    return new Date(date).toLocaleDateString('es-DO', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}
</script>

<template>
    <Head :title="`Editar — ${tenant.name}`" />

    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
            <Link href="/superadmin/tenants" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                <ArrowLeft class="w-5 h-5" />
            </Link>
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900">{{ tenant.name }}</h1>
                <p class="text-sm text-gray-500">Creado {{ formatDate(tenant.created_at) }}</p>
            </div>
            <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="planBadgeClass(tenant.subscription_plan)"
            >
                {{ tenant.subscription_plan }}
            </span>
            <button
                v-if="tenant.is_active"
                @click="impersonateTenant"
                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors"
                title="Entrar como admin de este restaurante"
            >
                <LogIn class="w-4 h-4" />
                Entrar como
            </button>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex gap-6">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="activeTab = tab.key"
                    class="pb-3 text-sm font-medium border-b-2 transition-colors"
                    :class="activeTab === tab.key
                        ? 'border-amber-600 text-amber-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700'"
                >
                    {{ tab.label }}
                </button>
            </nav>
        </div>

        <!-- Tab: Informacion General -->
        <div v-show="activeTab === 'general'">
            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Datos del Restaurante</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Nombre -->
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                            />
                            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                        </div>

                        <!-- Slug -->
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Slug (URL) *</label>
                            <input
                                v-model="form.slug"
                                type="text"
                                required
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                            />
                            <p v-if="form.errors.slug" class="text-red-500 text-xs mt-1">{{ form.errors.slug }}</p>
                        </div>

                        <!-- Timezone -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Zona Horaria</label>
                            <select
                                v-model="form.timezone"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                            >
                                <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
                            </select>
                            <p v-if="form.errors.timezone" class="text-red-500 text-xs mt-1">{{ form.errors.timezone }}</p>
                        </div>

                        <!-- Currency -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                            <select
                                v-model="form.currency"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                            >
                                <option v-for="c in currencies" :key="c.value" :value="c.value">{{ c.label }}</option>
                            </select>
                            <p v-if="form.errors.currency" class="text-red-500 text-xs mt-1">{{ form.errors.currency }}</p>
                        </div>

                        <!-- Plan -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                            <select
                                v-model="form.subscription_plan"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                            >
                                <option v-for="p in plans" :key="p.value" :value="p.value">{{ p.label }}</option>
                            </select>
                            <p v-if="form.errors.subscription_plan" class="text-red-500 text-xs mt-1">{{ form.errors.subscription_plan }}</p>
                        </div>

                        <!-- Activo -->
                        <div class="flex items-center gap-3 pt-6">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input v-model="form.is_active" type="checkbox" class="sr-only peer" />
                                <div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-amber-500/20 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-600"></div>
                            </label>
                            <span class="text-sm font-medium text-gray-700">Activo</span>
                        </div>
                    </div>
                </div>

                <!-- WhatsApp Configuration -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Configuracion WhatsApp</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number ID</label>
                            <input
                                v-model="form.whatsapp_phone_number_id"
                                type="text"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                                placeholder="Ej: 123456789012345"
                            />
                            <p v-if="form.errors.whatsapp_phone_number_id" class="text-red-500 text-xs mt-1">{{ form.errors.whatsapp_phone_number_id }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Business Account ID</label>
                            <input
                                v-model="form.whatsapp_business_account_id"
                                type="text"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                                placeholder="Ej: 123456789012345"
                            />
                            <p v-if="form.errors.whatsapp_business_account_id" class="text-red-500 text-xs mt-1">{{ form.errors.whatsapp_business_account_id }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Access Token</label>
                            <input
                                v-model="form.whatsapp_access_token"
                                type="password"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none"
                                placeholder="••••••• (encriptado) — dejar vacio para no cambiar"
                            />
                            <p class="text-xs text-gray-400 mt-1">Dejar vacio para mantener el token actual.</p>
                            <p v-if="form.errors.whatsapp_access_token" class="text-red-500 text-xs mt-1">{{ form.errors.whatsapp_access_token }}</p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between">
                    <button
                        type="button"
                        @click="deleteTenant"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 transition-colors"
                    >
                        <Trash2 class="w-4 h-4" />
                        Eliminar Restaurante
                    </button>
                    <div class="flex items-center gap-3">
                        <Link
                            href="/superadmin/tenants"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Volver
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 disabled:opacity-50 transition-colors"
                        >
                            <Save class="w-4 h-4" />
                            Guardar Cambios
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tab: Estadisticas -->
        <div v-show="activeTab === 'stats'">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <ShoppingBag class="w-5 h-5 text-blue-600" />
                        </div>
                        <p class="text-sm font-medium text-gray-500">Pedidos Totales</p>
                    </div>
                    <p class="text-2xl font-bold text-gray-900">{{ orderStats?.total || 0 }}</p>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                            <Calendar class="w-5 h-5 text-amber-600" />
                        </div>
                        <p class="text-sm font-medium text-gray-500">Pedidos Hoy</p>
                    </div>
                    <p class="text-2xl font-bold text-gray-900">{{ orderStats?.today || 0 }}</p>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <ShoppingBag class="w-5 h-5 text-green-600" />
                        </div>
                        <p class="text-sm font-medium text-gray-500">Pedidos Este Mes</p>
                    </div>
                    <p class="text-2xl font-bold text-gray-900">{{ orderStats?.this_month || 0 }}</p>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                            <DollarSign class="w-5 h-5 text-emerald-600" />
                        </div>
                        <p class="text-sm font-medium text-gray-500">Ingresos Este Mes</p>
                    </div>
                    <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(orderStats?.revenue_this_month || 0) }}</p>
                </div>
            </div>

            <!-- Additional info -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4">
                    <div class="w-10 h-10 bg-violet-100 rounded-lg flex items-center justify-center">
                        <Users class="w-5 h-5 text-violet-600" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Usuarios</p>
                        <p class="text-xl font-bold text-gray-900">{{ tenant.users_count || 0 }}</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4">
                    <div class="w-10 h-10 bg-rose-100 rounded-lg flex items-center justify-center">
                        <MapPin class="w-5 h-5 text-rose-600" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Sucursales</p>
                        <p class="text-xl font-bold text-gray-900">{{ tenant.branches_count || 0 }}</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex items-center gap-4">
                    <div class="w-10 h-10 bg-cyan-100 rounded-lg flex items-center justify-center">
                        <Users class="w-5 h-5 text-cyan-600" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Clientes</p>
                        <p class="text-xl font-bold text-gray-900">{{ tenant.customers_count || 0 }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Usuarios -->
        <div v-show="activeTab === 'users'">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <table v-if="users.length" class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Rol</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Ultimo Login</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="u in users" :key="u.id" class="hover:bg-gray-50/50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ u.name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ u.email }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="roleBadgeClass(u.role)">
                                    {{ u.role === 'admin' ? 'Admin' : 'Gestor' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="u.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                >
                                    {{ u.is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ formatDate(u.last_login_at) }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="p-12 text-center">
                    <Users class="w-10 h-10 text-gray-300 mx-auto mb-3" />
                    <p class="text-sm text-gray-500">No hay usuarios registrados</p>
                </div>
            </div>
        </div>

        <!-- Tab: Sucursales -->
        <div v-show="activeTab === 'branches'">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <table v-if="branches.length" class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Direccion</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="b in branches" :key="b.id" class="hover:bg-gray-50/50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ b.name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ b.address || '—' }}</td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="b.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                >
                                    {{ b.is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="p-12 text-center">
                    <MapPin class="w-10 h-10 text-gray-300 mx-auto mb-3" />
                    <p class="text-sm text-gray-500">No hay sucursales registradas</p>
                </div>
            </div>
        </div>
    </div>
</template>
