<script setup>
import { ref, onMounted, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import {
    CreditCard, CheckCircle, AlertCircle, Loader2, ShieldCheck,
    ArrowLeft, Building2, Upload, X, FileText, ImageIcon
} from 'lucide-vue-next'
import axios from 'axios'

const props = defineProps({
    plan: Object,
    publicKey: String,
    checkoutScriptBase: String,
    bankAccounts: { type: Array, default: () => [] },
})

// ── Payment method ────────────────────────────────────────────────────────────
const paymentMethod = ref(props.bankAccounts.length ? 'transfer' : 'card')

// ── Card (Cardnet) state ──────────────────────────────────────────────────────
const cardState    = ref('idle') // idle | loading_script | ready | processing | success | error
const cardError    = ref('')
const scriptLoaded = ref(false)

// ── Bank transfer form ────────────────────────────────────────────────────────
const selectedBank    = ref(props.bankAccounts[0]?.id ?? null)
const referenceNumber = ref('')
const evidenceFile    = ref(null)
const evidencePreview = ref(null)
const transferError   = ref('')
const transferLoading = ref(false)

const transferForm = useForm({
    bank_account_id:  null,
    reference_number: '',
    evidence:         null,
})

// ── Helpers ───────────────────────────────────────────────────────────────────
const planPrice = computed(() => {
    const price    = parseFloat(props.plan?.price_monthly || 0)
    const currency = props.plan?.currency || 'DOP'
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency, maximumFractionDigits: 2 }).format(price)
})

const selectedBankAccount = computed(() => props.bankAccounts.find(b => b.id === selectedBank.value))

// ── Cardnet logic ─────────────────────────────────────────────────────────────
onMounted(() => {
    if (paymentMethod.value === 'card' && props.publicKey) {
        loadCheckoutScript()
    }
})

const switchToCard = () => {
    paymentMethod.value = 'card'
    if (!scriptLoaded.value && props.publicKey) {
        loadCheckoutScript()
    }
}

const loadCheckoutScript = () => {
    if (cardState.value !== 'idle') return
    cardState.value = 'loading_script'
    const script    = document.createElement('script')
    script.src      = `${props.checkoutScriptBase}/Scripts/PWCheckout.js?key=${props.publicKey}`
    script.async    = true
    script.onload   = initCheckout
    script.onerror  = () => {
        cardState.value = 'error'
        cardError.value = 'No se pudo cargar el módulo de pago. Intenta recargar la página.'
    }
    document.head.appendChild(script)
}

const initCheckout = () => {
    if (!window.PWCheckout) {
        cardState.value = 'error'
        cardError.value = 'Error al inicializar el módulo de pago.'
        return
    }
    window.PWCheckout.SetProperties({
        name: 'WaOrder',
        button_label: `Pagar ${planPrice.value}/mes`,
        currency: props.plan?.currency || 'DOP',
        amount: String(parseFloat(props.plan?.price_monthly || 0).toFixed(2)),
        form_id: 'cardnet-form',
        lang: 'ESP',
        description: `Suscripción ${props.plan?.name}`,
    })
    window.PWCheckout.AddActionButton('cardnet-pay-btn')
    window.PWCheckout.Bind('tokenCreated', handleTokenCreated)
    window.PWCheckout.Bind('tokenError', handleTokenError)
    scriptLoaded.value = true
    cardState.value    = 'ready'
}

const handleTokenCreated = async (tokenObj) => {
    cardState.value = 'processing'
    cardError.value = ''
    try {
        const response = await axios.post('/register/payment/tokenize', {
            token_id:     tokenObj.TokenId,
            brand:        tokenObj.Brand,
            last4:        tokenObj.Last4,
            expiry_month: tokenObj.CardExpMonth,
            expiry_year:  tokenObj.CardExpYear,
        })
        if (response.data.success) {
            cardState.value = 'success'
            setTimeout(() => router.visit(response.data.redirect || '/setup'), 1500)
        }
    } catch (err) {
        cardState.value = 'error'
        cardError.value = err.response?.data?.message || 'Error al procesar el pago. Intenta de nuevo.'
    }
}

const handleTokenError = (err) => {
    cardState.value = 'error'
    cardError.value = 'No se pudo procesar la tarjeta. Verifica los datos e intenta de nuevo.'
    console.error('PWCheckout tokenError:', err)
}

const retryCard = () => {
    cardState.value = 'ready'
    cardError.value = ''
}

// ── Bank transfer logic ───────────────────────────────────────────────────────
const onFileChange = (e) => {
    const file = e.target.files[0]
    if (!file) return
    evidenceFile.value  = file
    transferError.value = ''

    const isImage = file.type.startsWith('image/')
    if (isImage) {
        const reader    = new FileReader()
        reader.onload   = (ev) => { evidencePreview.value = ev.target.result }
        reader.readAsDataURL(file)
    } else {
        evidencePreview.value = null
    }
}

const removeFile = () => {
    evidenceFile.value    = null
    evidencePreview.value = null
}

const submitTransfer = () => {
    transferError.value = ''

    if (!selectedBank.value) {
        transferError.value = 'Selecciona una cuenta bancaria.'
        return
    }
    if (!evidenceFile.value) {
        transferError.value = 'Debes adjuntar el comprobante de pago.'
        return
    }

    transferForm.bank_account_id  = selectedBank.value
    transferForm.reference_number = referenceNumber.value
    transferForm.evidence         = evidenceFile.value

    transferForm.post('/register/bank-transfer', {
        forceFormData: true,
        onError: (errors) => {
            transferError.value = Object.values(errors)[0] ?? 'Error al enviar el comprobante.'
        },
    })
}
</script>

<template>
    <Head title="Activar Suscripción — WaOrder" />

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-blue-50 flex items-center justify-center py-12 px-4">
        <div class="w-full max-w-md">

            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-2.5">
                    <img src="/images/logo.png" alt="WaOrder" class="h-10" />
                </div>
            </div>

            <!-- Card success state -->
            <div v-if="cardState === 'success'" class="bg-white rounded-2xl shadow-xl border border-gray-100 p-8 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <CheckCircle class="w-8 h-8 text-green-600" />
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">¡Pago exitoso!</h2>
                <p class="text-gray-500 text-sm">Redirigiendo a la configuración inicial…</p>
            </div>

            <!-- Main card -->
            <div v-else class="bg-white rounded-2xl shadow-xl border border-gray-100 p-8">

                <!-- Header -->
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                        <CreditCard class="w-5 h-5 text-[#0052FF]" />
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Activar Suscripción</h2>
                        <p class="text-sm text-gray-500">Elige tu método de pago</p>
                    </div>
                </div>

                <!-- Plan summary -->
                <div class="bg-blue-50 rounded-xl p-4 mb-5">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm font-semibold text-[#002F94]">Plan {{ plan?.name }}</p>
                            <p class="text-xs text-[#0052FF] mt-0.5">Facturación mensual</p>
                        </div>
                        <p class="text-xl font-bold text-[#0047DB]">{{ planPrice }}<span class="text-sm font-normal">/mes</span></p>
                    </div>
                </div>

                <!-- Payment method tabs -->
                <div class="flex rounded-xl border border-gray-200 p-1 mb-6 gap-1">
                    <button
                        @click="switchToCard"
                        :class="['flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-sm font-medium transition',
                                 paymentMethod === 'card' ? 'bg-[#0052FF] text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50']"
                    >
                        <CreditCard class="w-4 h-4" />
                        Tarjeta
                    </button>
                    <button
                        v-if="bankAccounts.length > 0"
                        @click="paymentMethod = 'transfer'"
                        :class="['flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-sm font-medium transition',
                                 paymentMethod === 'transfer' ? 'bg-[#0052FF] text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50']"
                    >
                        <Building2 class="w-4 h-4" />
                        Transferencia
                    </button>
                </div>

                <!-- ── CARD PAYMENT ── -->
                <template v-if="paymentMethod === 'card'">
                    <div v-if="cardState === 'error' && cardError" class="mb-4 p-3 bg-red-50 border border-red-100 rounded-xl flex items-start gap-2">
                        <AlertCircle class="w-4 h-4 text-red-500 mt-0.5 shrink-0" />
                        <p class="text-sm text-red-700">{{ cardError }}</p>
                    </div>

                    <form id="cardnet-form">
                        <input type="hidden" name="PWToken" id="PWToken" />
                    </form>

                    <div v-if="cardState === 'loading_script'" class="flex items-center justify-center py-6 gap-2 text-gray-500">
                        <Loader2 class="w-5 h-5 animate-spin" />
                        <span class="text-sm">Cargando módulo de pago…</span>
                    </div>
                    <div v-else-if="cardState === 'processing'" class="flex items-center justify-center py-6 gap-2 text-[#0052FF]">
                        <Loader2 class="w-5 h-5 animate-spin" />
                        <span class="text-sm font-medium">Procesando pago…</span>
                    </div>
                    <div v-else-if="cardState === 'ready'">
                        <button id="cardnet-pay-btn" type="button"
                                class="w-full py-3 px-6 bg-[#0052FF] text-white font-semibold rounded-xl hover:bg-[#0047DB] transition-all text-sm shadow-lg shadow-blue-200 flex items-center justify-center gap-2">
                            <CreditCard class="w-4 h-4" />
                            Ingresar datos de tarjeta
                        </button>
                    </div>
                    <div v-else-if="cardState === 'error'">
                        <button @click="retryCard" type="button"
                                class="w-full py-3 px-6 bg-[#0052FF] text-white font-semibold rounded-xl hover:bg-[#0047DB] transition-all text-sm">
                            Intentar de nuevo
                        </button>
                    </div>
                    <div v-else-if="cardState === 'idle'" class="flex items-center justify-center py-6 gap-2 text-gray-500">
                        <Loader2 class="w-5 h-5 animate-spin" />
                        <span class="text-sm">Inicializando…</span>
                    </div>

                    <div class="mt-4 flex items-center justify-center gap-1.5 text-gray-400">
                        <ShieldCheck class="w-4 h-4" />
                        <p class="text-xs">Pago seguro procesado por Cardnet</p>
                    </div>
                </template>

                <!-- ── BANK TRANSFER ── -->
                <template v-else-if="paymentMethod === 'transfer'">

                    <!-- Bank account selector -->
                    <div v-if="bankAccounts.length > 1" class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cuenta destino</label>
                        <div class="space-y-2">
                            <label v-for="bank in bankAccounts" :key="bank.id"
                                   :class="['flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition',
                                            selectedBank === bank.id ? 'border-[#3385ff] bg-blue-50' : 'border-gray-200 hover:border-gray-300']">
                                <input type="radio" :value="bank.id" v-model="selectedBank" class="text-[#0052FF]" />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900">{{ bank.bank_name }}</p>
                                    <p class="text-xs text-gray-500">{{ bank.account_holder_name }} · {{ bank.account_type === 'savings' ? 'Ahorro' : 'Corriente' }}</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Single bank account details -->
                    <div v-if="selectedBankAccount" class="bg-gray-50 rounded-xl border border-gray-200 p-4 mb-4 text-sm">
                        <p class="font-semibold text-gray-800 mb-2 flex items-center gap-1.5">
                            <Building2 class="w-4 h-4 text-gray-400" />
                            {{ selectedBankAccount.bank_name }}
                        </p>
                        <div class="space-y-1.5">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Titular</span>
                                <span class="font-medium">{{ selectedBankAccount.account_holder_name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Cuenta ({{ selectedBankAccount.account_type === 'savings' ? 'Ahorro' : 'Corriente' }})</span>
                                <span class="font-mono font-medium">{{ selectedBankAccount.account_number }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Monto a transferir</span>
                                <span class="font-bold text-[#0047DB]">{{ planPrice }}</span>
                            </div>
                        </div>
                        <p v-if="selectedBankAccount.instructions" class="mt-3 text-xs text-amber-700 bg-amber-50 rounded-lg p-2">
                            💡 {{ selectedBankAccount.instructions }}
                        </p>
                    </div>

                    <!-- Reference number -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Número de referencia <span class="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <input v-model="referenceNumber" type="text" placeholder="ej. 00012345678"
                               class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#3385ff]" />
                    </div>

                    <!-- Evidence upload -->
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Comprobante de pago</label>

                        <!-- Uploaded file preview -->
                        <div v-if="evidenceFile" class="relative">
                            <div v-if="evidencePreview" class="rounded-xl overflow-hidden border border-gray-200 mb-2">
                                <img :src="evidencePreview" alt="Comprobante" class="w-full max-h-48 object-contain bg-gray-50" />
                            </div>
                            <div v-else class="flex items-center gap-3 p-3 bg-gray-50 border border-gray-200 rounded-xl mb-2">
                                <FileText class="w-5 h-5 text-gray-400 shrink-0" />
                                <span class="text-sm text-gray-700 truncate">{{ evidenceFile.name }}</span>
                            </div>
                            <button @click="removeFile" type="button"
                                    class="absolute top-2 right-2 w-6 h-6 bg-gray-800/70 hover:bg-gray-900 text-white rounded-full flex items-center justify-center transition">
                                <X class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <!-- Drop zone -->
                        <label v-else
                               class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-300 hover:border-[#3385ff] rounded-xl p-6 cursor-pointer transition bg-gray-50 hover:bg-blue-50">
                            <Upload class="w-6 h-6 text-gray-400" />
                            <p class="text-sm text-gray-600 font-medium">Subir comprobante</p>
                            <p class="text-xs text-gray-400">JPG, PNG, WEBP o PDF · máx. 5 MB</p>
                            <input type="file" class="hidden" accept=".jpg,.jpeg,.png,.webp,.pdf" @change="onFileChange" />
                        </label>
                    </div>

                    <!-- Error -->
                    <div v-if="transferError" class="mb-4 p-3 bg-red-50 border border-red-100 rounded-xl flex items-start gap-2">
                        <AlertCircle class="w-4 h-4 text-red-500 mt-0.5 shrink-0" />
                        <p class="text-sm text-red-700">{{ transferError }}</p>
                    </div>

                    <!-- Submit -->
                    <button @click="submitTransfer" :disabled="transferForm.processing"
                            class="w-full py-3 px-6 bg-[#0052FF] text-white font-semibold rounded-xl hover:bg-[#0047DB] transition-all text-sm shadow-lg shadow-blue-200 flex items-center justify-center gap-2 disabled:opacity-50">
                        <Loader2 v-if="transferForm.processing" class="w-4 h-4 animate-spin" />
                        <Upload v-else class="w-4 h-4" />
                        {{ transferForm.processing ? 'Enviando comprobante…' : 'Enviar comprobante' }}
                    </button>

                    <div class="mt-4 flex items-center justify-center gap-1.5 text-gray-400">
                        <ShieldCheck class="w-4 h-4" />
                        <p class="text-xs">Tu comprobante se verificará en hasta 12 horas</p>
                    </div>
                </template>

            </div>

            <!-- Back link -->
            <div v-if="cardState !== 'success'" class="text-center mt-4">
                <a href="/register" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft class="w-3.5 h-3.5" />
                    Volver al registro
                </a>
            </div>
        </div>
    </div>
</template>
