<script setup>
import { ref, watch } from 'vue'
import { Link, Head, useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { Save, ArrowLeft } from 'lucide-vue-next'

defineOptions({ layout: SuperAdminLayout })

const slugManuallyEdited = ref(false)

const form = useForm({
    name: '',
    slug: '',
    timezone: 'America/Santo_Domingo',
    currency: 'DOP',
    subscription_plan: 'free',
    is_active: true,
    admin_name: '',
    admin_email: '',
    admin_password: '',
    branch_name: '',
    branch_address: '',
})

watch(() => form.name, (val) => {
    if (!slugManuallyEdited.value) {
        form.slug = val.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '')
    }
})

const onSlugInput = () => {
    slugManuallyEdited.value = true
}

const submit = () => {
    form.post('/superadmin/tenants')
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
</script>

<template>
    <Head title="Nuevo Restaurante" />

    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
            <Link href="/superadmin/tenants" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                <ArrowLeft class="w-5 h-5" />
            </Link>
            <h1 class="text-2xl font-bold text-gray-900">Nuevo Restaurante</h1>
        </div>

        <form @submit.prevent="submit" class="space-y-8">
            <!-- Datos del Restaurante -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Datos del Restaurante</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Nombre -->
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Restaurante *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
                            placeholder="Ej: Pizzeria Don Mario"
                        />
                        <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                    </div>

                    <!-- Slug -->
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Slug (URL) *</label>
                        <input
                            v-model="form.slug"
                            @input="onSlugInput"
                            type="text"
                            required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
                            placeholder="pizzeria-don-mario"
                        />
                        <p class="text-xs text-gray-400 mt-1">Se usa en la URL. Solo letras, numeros y guiones.</p>
                        <p v-if="form.errors.slug" class="text-red-500 text-xs mt-1">{{ form.errors.slug }}</p>
                    </div>

                    <!-- Timezone -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Zona Horaria</label>
                        <select
                            v-model="form.timezone"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
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
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
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
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
                        >
                            <option v-for="p in plans" :key="p.value" :value="p.value">{{ p.label }}</option>
                        </select>
                        <p v-if="form.errors.subscription_plan" class="text-red-500 text-xs mt-1">{{ form.errors.subscription_plan }}</p>
                    </div>

                    <!-- Activo -->
                    <div class="flex items-center gap-3 pt-6">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input v-model="form.is_active" type="checkbox" class="sr-only peer" />
                            <div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-[#0052FF]/20 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#0052FF]"></div>
                        </label>
                        <span class="text-sm font-medium text-gray-700">Activo</span>
                    </div>
                </div>
            </div>

            <!-- Administrador del Restaurante -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Administrador del Restaurante</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Admin name -->
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Admin *</label>
                        <input
                            v-model="form.admin_name"
                            type="text"
                            required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
                            placeholder="Juan Perez"
                        />
                        <p v-if="form.errors.admin_name" class="text-red-500 text-xs mt-1">{{ form.errors.admin_name }}</p>
                    </div>

                    <!-- Admin email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email del Admin *</label>
                        <input
                            v-model="form.admin_email"
                            type="email"
                            required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
                            placeholder="admin@restaurante.com"
                        />
                        <p v-if="form.errors.admin_email" class="text-red-500 text-xs mt-1">{{ form.errors.admin_email }}</p>
                    </div>

                    <!-- Admin password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contrasena *</label>
                        <input
                            v-model="form.admin_password"
                            type="password"
                            required
                            minlength="8"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
                            placeholder="Minimo 8 caracteres"
                        />
                        <p v-if="form.errors.admin_password" class="text-red-500 text-xs mt-1">{{ form.errors.admin_password }}</p>
                    </div>
                </div>
            </div>

            <!-- Sucursal Inicial -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-1">Sucursal Inicial</h2>
                <p class="text-sm text-gray-500 mb-4">Opcional. Puedes agregar sucursales despues.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Branch name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de la Sucursal</label>
                        <input
                            v-model="form.branch_name"
                            type="text"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
                            placeholder="Ej: Sucursal Centro"
                        />
                        <p v-if="form.errors.branch_name" class="text-red-500 text-xs mt-1">{{ form.errors.branch_name }}</p>
                    </div>

                    <!-- Branch address -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Direccion</label>
                        <input
                            v-model="form.branch_address"
                            type="text"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#0052FF]/20 focus:border-[#0052FF] outline-none"
                            placeholder="Calle Principal #123"
                        />
                        <p v-if="form.errors.branch_address" class="text-red-500 text-xs mt-1">{{ form.errors.branch_address }}</p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3">
                <Link
                    href="/superadmin/tenants"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                >
                    Cancelar
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-[#0052FF] text-white text-sm font-medium rounded-lg hover:bg-[#0047DB] disabled:opacity-50 transition-colors"
                >
                    <Save class="w-4 h-4" />
                    Crear Restaurante
                </button>
            </div>
        </form>
    </div>
</template>
