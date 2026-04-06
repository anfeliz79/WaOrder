<script setup>
import { ref, reactive } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import {
    Wallet, CreditCard, Building2, CheckCircle, XCircle,
    ChevronDown, ChevronUp, Save, Loader2
} from 'lucide-vue-next'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const props = defineProps({
    methods: Array,
})

// Icon map — resolve component from icon name string
const iconMap = { CreditCard, Building2, Wallet }

const resolveIcon = (name) => iconMap[name] ?? Wallet

// Expandable config sections
const expandedSlug = ref(null)

const toggleExpand = (slug) => {
    expandedSlug.value = expandedSlug.value === slug ? null : slug
}

// Toggle active
const togglingSlug = ref(null)

const toggleActive = (method) => {
    togglingSlug.value = method.slug
    router.post(`/superadmin/payment-methods/${method.id}/toggle`, {}, {
        preserveScroll: true,
        onFinish: () => { togglingSlug.value = null },
    })
}

// PayPal config form
const paypalForm = useForm({
    client_id: '',
    client_secret: '',
    mode: 'sandbox',
    webhook_id: '',
})

const openPaypalConfig = (method) => {
    const config = method.config ?? {}
    paypalForm.client_id     = config.client_id ?? ''
    paypalForm.client_secret = config.client_secret ?? ''
    paypalForm.mode          = config.mode ?? 'sandbox'
    paypalForm.webhook_id    = config.webhook_id ?? ''
    expandedSlug.value       = 'paypal'
}

const savePaypalConfig = (method) => {
    paypalForm.put(`/superadmin/payment-methods/${method.id}/config`, {
        preserveScroll: true,
        onSuccess: () => { expandedSlug.value = null },
    })
}
</script>

<template>
    <SuperAdminLayout title="Metodos de Pago">
        <Head title="Metodos de Pago" />

        <!-- Header -->
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                <Wallet class="w-5 h-5 text-[#0052FF]" />
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900">Metodos de Pago</h1>
                <p class="text-sm text-gray-500">Controla que metodos de pago estan disponibles en el registro</p>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!methods.length" class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
            <Wallet class="w-10 h-10 text-gray-300 mx-auto mb-3" />
            <p class="text-gray-500 font-medium mb-1">Sin metodos de pago configurados</p>
            <p class="text-sm text-gray-400">Ejecuta el seeder para crear los metodos iniciales.</p>
        </div>

        <!-- Methods list -->
        <div v-else class="space-y-4">
            <div v-for="method in methods" :key="method.id"
                 class="bg-white rounded-xl border border-gray-200 overflow-hidden transition">

                <!-- Method card -->
                <div class="flex items-center gap-4 p-5">
                    <!-- Icon -->
                    <div :class="[
                        'w-11 h-11 rounded-xl flex items-center justify-center shrink-0',
                        method.is_active ? 'bg-blue-100' : 'bg-gray-100'
                    ]">
                        <component :is="resolveIcon(method.icon)"
                                   :class="['w-5 h-5', method.is_active ? 'text-[#0052FF]' : 'text-gray-400']" />
                    </div>

                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-gray-900 text-sm">{{ method.name }}</h3>
                            <span :class="[
                                'flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full',
                                method.is_active
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-gray-100 text-gray-500'
                            ]">
                                <CheckCircle v-if="method.is_active" class="w-3 h-3" />
                                <XCircle v-else class="w-3 h-3" />
                                {{ method.is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 mt-0.5">{{ method.description }}</p>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-3 shrink-0">
                        <!-- Config button (only for paypal) -->
                        <button v-if="method.slug === 'paypal'"
                                @click="openPaypalConfig(method)"
                                class="text-sm text-gray-500 hover:text-[#0052FF] transition flex items-center gap-1">
                            <component :is="expandedSlug === 'paypal' ? ChevronUp : ChevronDown" class="w-4 h-4" />
                            Configurar
                        </button>

                        <!-- Toggle switch -->
                        <button @click="toggleActive(method)"
                                :disabled="togglingSlug === method.slug"
                                :class="[
                                    'relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-[#0052FF] focus:ring-offset-2',
                                    method.is_active ? 'bg-[#0052FF]' : 'bg-gray-300',
                                    togglingSlug === method.slug ? 'opacity-50 cursor-wait' : 'cursor-pointer'
                                ]">
                            <span :class="[
                                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-200 shadow-sm',
                                method.is_active ? 'translate-x-6' : 'translate-x-1'
                            ]" />
                        </button>
                    </div>
                </div>

                <!-- PayPal config panel -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-if="method.slug === 'paypal' && expandedSlug === 'paypal'"
                         class="border-t border-gray-100 bg-gray-50 p-5">
                        <form @submit.prevent="savePaypalConfig(method)" class="space-y-4 max-w-lg">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Client ID</label>
                                <input v-model="paypalForm.client_id" type="text"
                                       placeholder="PayPal Client ID"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#3385ff]" />
                                <p v-if="paypalForm.errors.client_id" class="text-xs text-red-500 mt-1">{{ paypalForm.errors.client_id }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Client Secret</label>
                                <input v-model="paypalForm.client_secret" type="password"
                                       placeholder="PayPal Client Secret"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#3385ff]" />
                                <p v-if="paypalForm.errors.client_secret" class="text-xs text-red-500 mt-1">{{ paypalForm.errors.client_secret }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Modo</label>
                                    <select v-model="paypalForm.mode"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#3385ff]">
                                        <option value="sandbox">Sandbox</option>
                                        <option value="live">Live</option>
                                    </select>
                                    <p v-if="paypalForm.errors.mode" class="text-xs text-red-500 mt-1">{{ paypalForm.errors.mode }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Webhook ID <span class="text-gray-400 font-normal">(opcional)</span></label>
                                    <input v-model="paypalForm.webhook_id" type="text"
                                           placeholder="Webhook ID"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#3385ff]" />
                                </div>
                            </div>

                            <div class="flex gap-3 pt-2">
                                <button type="button" @click="expandedSlug = null"
                                        class="px-4 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition text-sm">
                                    Cancelar
                                </button>
                                <button type="submit" :disabled="paypalForm.processing"
                                        class="flex items-center gap-2 px-4 py-2 bg-[#0052FF] hover:bg-[#0047DB] text-white font-semibold rounded-lg transition text-sm disabled:opacity-50">
                                    <Loader2 v-if="paypalForm.processing" class="w-4 h-4 animate-spin" />
                                    <Save v-else class="w-4 h-4" />
                                    {{ paypalForm.processing ? 'Guardando...' : 'Guardar configuracion' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </div>
    </SuperAdminLayout>
</template>
