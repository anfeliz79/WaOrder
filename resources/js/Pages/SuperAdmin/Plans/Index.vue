<script setup>
import { Link, Head, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import {
    Plus, Pencil, Trash2, ToggleLeft, ToggleRight,
    Users, Store, UtensilsCrossed, Truck, ShoppingBag
} from 'lucide-vue-next'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    plans: Array,
})

const toggleActive = (plan) => {
    router.post(`/superadmin/plans/${plan.id}/toggle-active`, {}, { preserveScroll: true })
}

const deletePlan = (plan) => {
    if (plan.subscriptions_count > 0) {
        alert('No se puede eliminar un plan con suscripciones activas.')
        return
    }
    if (window.confirm(`¿Eliminar el plan "${plan.name}"? Esta accion no se puede deshacer.`)) {
        router.delete(`/superadmin/plans/${plan.id}`, { preserveScroll: true })
    }
}

const formatPrice = (price, currency = 'DOP') => {
    if (!price || parseFloat(price) === 0) return 'Gratis'
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency }).format(price)
}
</script>

<template>
    <Head title="Planes" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Planes</h1>
                <p class="text-sm text-gray-500 mt-1">Configura los planes de suscripcion de la plataforma.</p>
            </div>
            <Link href="/superadmin/plans/create"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition-colors">
                <Plus class="w-4 h-4" />
                Nuevo Plan
            </Link>
        </div>

        <!-- Plans Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="plan in plans" :key="plan.id"
                :class="[
                    'bg-white rounded-xl border shadow-sm overflow-hidden',
                    plan.is_active ? 'border-gray-200' : 'border-red-200 opacity-75'
                ]">
                <!-- Plan Header -->
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-lg font-bold text-gray-900">{{ plan.name }}</h3>
                        <span :class="[
                            'px-2 py-0.5 text-xs font-medium rounded-full',
                            plan.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                        ]">
                            {{ plan.is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                    <div class="flex items-baseline gap-1">
                        <span class="text-3xl font-bold text-gray-900">{{ formatPrice(plan.price_monthly) }}</span>
                        <span v-if="parseFloat(plan.price_monthly) > 0" class="text-sm text-gray-500">/mes</span>
                    </div>
                    <p v-if="plan.price_annual" class="text-sm text-gray-500 mt-1">
                        {{ formatPrice(plan.price_annual) }}/anual
                    </p>
                    <p v-if="plan.trial_days > 0" class="text-sm text-amber-600 font-medium mt-2">
                        {{ plan.trial_days }} dias de prueba
                    </p>
                </div>

                <!-- Limits -->
                <div class="p-6 space-y-3">
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Limites</h4>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="flex items-center gap-2">
                            <Store class="w-4 h-4 text-gray-400" />
                            <span>{{ plan.max_branches }} sucursales</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <UtensilsCrossed class="w-4 h-4 text-gray-400" />
                            <span>{{ plan.max_menu_items }} items</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Truck class="w-4 h-4 text-gray-400" />
                            <span>{{ plan.max_drivers }} drivers</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <ShoppingBag class="w-4 h-4 text-gray-400" />
                            <span>{{ plan.max_orders_per_month }}/mes</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Users class="w-4 h-4 text-gray-400" />
                            <span>{{ plan.max_users }} usuarios</span>
                        </div>
                    </div>

                    <!-- Features -->
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider pt-2">Funciones</h4>
                    <div class="flex flex-wrap gap-1.5">
                        <span v-if="plan.whatsapp_bot_enabled" class="px-2 py-0.5 bg-green-50 text-green-700 text-xs rounded-full">WhatsApp</span>
                        <span v-if="plan.ai_enabled" class="px-2 py-0.5 bg-purple-50 text-purple-700 text-xs rounded-full">IA</span>
                        <span v-if="plan.external_menu_enabled" class="px-2 py-0.5 bg-blue-50 text-blue-700 text-xs rounded-full">Menu externo</span>
                        <span v-if="plan.custom_domain" class="px-2 py-0.5 bg-indigo-50 text-indigo-700 text-xs rounded-full">Dominio</span>
                    </div>

                    <!-- Add-ons -->
                    <div v-if="plan.support_addon_available || plan.delivery_app_addon_available">
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider pt-2">Add-ons</h4>
                        <div class="space-y-1 mt-1">
                            <p v-if="plan.support_addon_available" class="text-sm text-gray-600">
                                Soporte: +{{ formatPrice(plan.support_addon_price) }}/mes
                            </p>
                            <p v-if="plan.delivery_app_addon_available" class="text-sm text-gray-600">
                                App Delivery: +{{ formatPrice(plan.delivery_app_addon_price) }}/mes
                            </p>
                        </div>
                    </div>

                    <!-- Subscribers -->
                    <p class="text-xs text-gray-400 pt-2">
                        {{ plan.subscriptions_count }} suscriptores
                    </p>
                </div>

                <!-- Actions -->
                <div class="px-6 py-3 bg-gray-50 flex items-center justify-between">
                    <button @click="toggleActive(plan)" class="text-gray-500 hover:text-gray-700 transition-colors" :title="plan.is_active ? 'Desactivar' : 'Activar'">
                        <component :is="plan.is_active ? ToggleRight : ToggleLeft" class="w-6 h-6" />
                    </button>
                    <div class="flex items-center gap-2">
                        <Link :href="`/superadmin/plans/${plan.id}/edit`"
                            class="p-2 text-gray-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors">
                            <Pencil class="w-4 h-4" />
                        </Link>
                        <button @click="deletePlan(plan)"
                            :disabled="plan.subscriptions_count > 0"
                            :class="[
                                'p-2 rounded-lg transition-colors',
                                plan.subscriptions_count > 0
                                    ? 'text-gray-300 cursor-not-allowed'
                                    : 'text-gray-500 hover:text-red-600 hover:bg-red-50'
                            ]">
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!plans.length" class="text-center py-16">
            <p class="text-gray-500">No hay planes creados.</p>
            <Link href="/superadmin/plans/create" class="text-amber-600 hover:text-amber-700 font-medium mt-2 inline-block">
                Crear primer plan
            </Link>
        </div>
    </div>
</template>
