<script setup>
import { Link, Head, useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { ArrowLeft } from 'lucide-vue-next'

defineOptions({ layout: SuperAdminLayout })

const form = useForm({
    name: '',
    description: '',
    price_monthly: 0,
    price_annual: null,
    trial_days: 0,
    currency: 'DOP',
    max_branches: 1,
    max_menu_items: 50,
    max_drivers: 3,
    max_orders_per_month: 100,
    max_users: 2,
    whatsapp_bot_enabled: true,
    ai_enabled: false,
    external_menu_enabled: false,
    custom_domain: false,
    support_addon_available: false,
    support_addon_price: 0,
    delivery_app_addon_available: false,
    delivery_app_addon_price: 0,
    is_active: true,
    sort_order: 0,
})

const submit = () => {
    form.post('/superadmin/plans')
}
</script>

<template>
    <Head title="Nuevo Plan" />

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6">
        <!-- Header -->
        <div class="flex items-center gap-3 mb-6">
            <Link href="/superadmin/plans" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
                <ArrowLeft class="w-5 h-5" />
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Nuevo Plan</h1>
                <p class="text-sm text-gray-500">Configura un nuevo plan de suscripcion.</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Basic Info -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Informacion Basica</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                        <input v-model="form.name" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]" placeholder="Ej: Starter" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descripcion</label>
                        <textarea v-model="form.description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]" placeholder="Breve descripcion del plan"></textarea>
                    </div>
                </div>
            </div>

            <!-- Pricing -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Precios</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Precio Mensual</label>
                        <input v-model.number="form.price_monthly" type="number" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]" />
                        <p v-if="form.errors.price_monthly" class="mt-1 text-sm text-red-600">{{ form.errors.price_monthly }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Precio Anual</label>
                        <input v-model.number="form.price_annual" type="number" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]" placeholder="Dejar vacio si no aplica" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dias de Prueba</label>
                        <input v-model.number="form.trial_days" type="number" min="0" max="365" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                        <select v-model="form.currency" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]">
                            <option value="DOP">DOP (Peso Dominicano)</option>
                            <option value="USD">USD (Dolar)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Orden de Aparicion</label>
                        <input v-model.number="form.sort_order" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]" />
                    </div>
                </div>
            </div>

            <!-- Limits -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Limites</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sucursales</label>
                        <input v-model.number="form.max_branches" type="number" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Items de Menu</label>
                        <input v-model.number="form.max_menu_items" type="number" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mensajeros</label>
                        <input v-model.number="form.max_drivers" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ordenes/Mes</label>
                        <input v-model.number="form.max_orders_per_month" type="number" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Usuarios</label>
                        <input v-model.number="form.max_users" type="number" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]" />
                    </div>
                </div>
            </div>

            <!-- Features -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Funcionalidades</h2>
                <div class="space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input v-model="form.whatsapp_bot_enabled" type="checkbox" class="w-4 h-4 text-[#0052FF] rounded border-gray-300 focus:ring-[#3385ff]" />
                        <span class="text-sm text-gray-700">Bot de WhatsApp</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input v-model="form.ai_enabled" type="checkbox" class="w-4 h-4 text-[#0052FF] rounded border-gray-300 focus:ring-[#3385ff]" />
                        <span class="text-sm text-gray-700">Inteligencia Artificial (NLP)</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input v-model="form.external_menu_enabled" type="checkbox" class="w-4 h-4 text-[#0052FF] rounded border-gray-300 focus:ring-[#3385ff]" />
                        <span class="text-sm text-gray-700">Menu Externo (API)</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input v-model="form.custom_domain" type="checkbox" class="w-4 h-4 text-[#0052FF] rounded border-gray-300 focus:ring-[#3385ff]" />
                        <span class="text-sm text-gray-700">Dominio Personalizado</span>
                    </label>
                </div>
            </div>

            <!-- Add-ons -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Add-ons</h2>
                <div class="space-y-4">
                    <div class="flex items-start gap-4">
                        <label class="flex items-center gap-3 cursor-pointer min-w-fit">
                            <input v-model="form.support_addon_available" type="checkbox" class="w-4 h-4 text-[#0052FF] rounded border-gray-300 focus:ring-[#3385ff]" />
                            <span class="text-sm text-gray-700">Soporte Premium</span>
                        </label>
                        <div v-if="form.support_addon_available" class="flex-1">
                            <input v-model.number="form.support_addon_price" type="number" step="0.01" min="0" placeholder="Precio mensual" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF] text-sm" />
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <label class="flex items-center gap-3 cursor-pointer min-w-fit">
                            <input v-model="form.delivery_app_addon_available" type="checkbox" class="w-4 h-4 text-[#0052FF] rounded border-gray-300 focus:ring-[#3385ff]" />
                            <span class="text-sm text-gray-700">App Movil Delivery</span>
                        </label>
                        <div v-if="form.delivery_app_addon_available" class="flex-1">
                            <input v-model.number="form.delivery_app_addon_price" type="number" step="0.01" min="0" placeholder="Precio mensual" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF] text-sm" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input v-model="form.is_active" type="checkbox" class="w-4 h-4 text-[#0052FF] rounded border-gray-300 focus:ring-[#3385ff]" />
                    <span class="text-sm font-medium text-gray-700">Plan activo (visible para nuevos registros)</span>
                </label>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end gap-3">
                <Link href="/superadmin/plans" class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancelar
                </Link>
                <button type="submit" :disabled="form.processing"
                    class="px-6 py-2.5 text-sm font-medium text-white bg-[#0052FF] rounded-lg hover:bg-[#0047DB] disabled:opacity-50 transition-colors">
                    {{ form.processing ? 'Creando...' : 'Crear Plan' }}
                </button>
            </div>
        </form>
    </div>
</template>
