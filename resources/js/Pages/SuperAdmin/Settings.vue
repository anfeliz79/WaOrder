<script setup>
import { Link, Head, useForm, usePage } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { CreditCard, MessageCircle, Shield, Brain } from 'lucide-vue-next'

defineOptions({ layout: SuperAdminLayout })

const props = defineProps({
    settings: Object,
})

const form = useForm({
    cardnet_environment: props.settings.cardnet_environment || 'testing',
    cardnet_public_key: '',
    cardnet_private_key: '',
    whatsapp_contact: props.settings.whatsapp_contact || '',
    ai_provider: props.settings.ai_provider || 'groq',
    ai_api_key: '',
})

const submit = () => {
    form.post('/superadmin/settings')
}
</script>

<template>
    <Head title="Configuracion" />

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Configuracion de Plataforma</h1>
            <p class="text-sm text-gray-500 mt-1">Credenciales de pago, IA y configuracion global.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Cardnet Platform Credentials -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <div class="flex items-center gap-3 mb-4">
                    <CreditCard class="w-5 h-5 text-[#0052FF]" />
                    <h2 class="text-lg font-semibold text-gray-900">Cardnet — Pagos Recurrentes</h2>
                </div>
                <p class="text-sm text-gray-500 mb-4">
                    Credenciales de la plataforma para cobrar suscripciones a los tenants via Cardnet.
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ambiente</label>
                        <select v-model="form.cardnet_environment"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]">
                            <option value="testing">Testing (Lab)</option>
                            <option value="production">Produccion</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Public Key</label>
                        <input v-model="form.cardnet_public_key" type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]"
                            :placeholder="settings.cardnet_has_keys ? settings.cardnet_public_key : 'No configurada'" />
                        <p class="mt-1 text-xs text-gray-400">Dejar vacio para mantener la clave actual.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Private Key</label>
                        <input v-model="form.cardnet_private_key" type="password"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]"
                            :placeholder="settings.cardnet_has_keys ? settings.cardnet_private_key : 'No configurada'" />
                        <p class="mt-1 text-xs text-gray-400">Dejar vacio para mantener la clave actual.</p>
                    </div>

                    <div :class="[
                        'flex items-center gap-2 p-3 rounded-lg text-sm',
                        settings.cardnet_has_keys ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700'
                    ]">
                        <Shield class="w-4 h-4" />
                        <span v-if="settings.cardnet_has_keys">Credenciales configuradas ({{ settings.cardnet_environment }})</span>
                        <span v-else>Credenciales no configuradas — los cobros de suscripcion no funcionaran.</span>
                    </div>
                </div>
            </div>

            <!-- AI / NLP -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <div class="flex items-center gap-3 mb-4">
                    <Brain class="w-5 h-5 text-purple-600" />
                    <h2 class="text-lg font-semibold text-gray-900">Inteligencia Artificial</h2>
                </div>
                <p class="text-sm text-gray-500 mb-4">
                    La IA mejora el reconocimiento de texto libre en el chatbot. Los tenants con el flag <strong>ai_enabled</strong> en su plan tendran acceso.
                    La clave API es compartida por todos los tenants.
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Proveedor</label>
                        <select v-model="form.ai_provider"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]">
                            <option value="groq">Groq (gratuito, recomendado)</option>
                            <option value="openai">OpenAI</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                        <input v-model="form.ai_api_key" type="password"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]"
                            :placeholder="settings.ai_has_key ? settings.ai_api_key : 'No configurada'" />
                        <p class="mt-1 text-xs text-gray-400">Dejar vacio para mantener la clave actual.</p>
                    </div>

                    <div :class="[
                        'flex items-center gap-2 p-3 rounded-lg text-sm',
                        settings.ai_has_key ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700'
                    ]">
                        <Shield class="w-4 h-4" />
                        <span v-if="settings.ai_has_key">IA configurada — proveedor: {{ settings.ai_provider }}</span>
                        <span v-else>Sin API key — el chatbot usara solo regex + fuzzy matching.</span>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Contact -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <div class="flex items-center gap-3 mb-4">
                    <MessageCircle class="w-5 h-5 text-green-600" />
                    <h2 class="text-lg font-semibold text-gray-900">WhatsApp de Contacto</h2>
                </div>
                <p class="text-sm text-gray-500 mb-4">
                    Numero que aparece en la landing page para asistencia y soporte.
                </p>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Numero WhatsApp</label>
                    <input v-model="form.whatsapp_contact" type="text"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3385ff] focus:border-[#0052FF]"
                        placeholder="18091234567 (sin + ni espacios)" />
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end">
                <button type="submit" :disabled="form.processing"
                    class="px-6 py-2.5 text-sm font-medium text-white bg-[#0052FF] rounded-lg hover:bg-[#0047DB] disabled:opacity-50 transition-colors">
                    {{ form.processing ? 'Guardando...' : 'Guardar Configuracion' }}
                </button>
            </div>
        </form>
    </div>
</template>
