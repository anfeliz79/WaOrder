<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Clock, CheckCircle, XCircle, AlertTriangle, ArrowLeft, RefreshCw } from 'lucide-vue-next'

const props = defineProps({
    verification: Object, // null if no verification submitted yet
})

const now = ref(new Date())
let timer = null

onMounted(() => {
    timer = setInterval(() => { now.value = new Date() }, 30000)

    // Auto-redirect if approved
    if (props.verification?.status === 'approved') {
        setTimeout(() => router.visit('/setup'), 2500)
    }
})

onUnmounted(() => clearInterval(timer))

const statusConfig = computed(() => {
    const status = props.verification?.status
    return {
        pending: {
            icon: Clock,
            iconClass: 'text-amber-500',
            bgClass: 'bg-amber-50',
            badgeClass: 'bg-amber-100 text-amber-800',
            label: 'En revisión',
            title: 'Verificando tu transferencia',
            message: 'Nuestro equipo está revisando tu comprobante. Recibirás un correo electrónico cuando se complete la verificación.',
        },
        approved: {
            icon: CheckCircle,
            iconClass: 'text-green-500',
            bgClass: 'bg-green-50',
            badgeClass: 'bg-green-100 text-green-800',
            label: 'Aprobado',
            title: '¡Transferencia aprobada!',
            message: 'Tu pago fue verificado correctamente. Redirigiendo a la configuración de tu restaurante…',
        },
        rejected: {
            icon: XCircle,
            iconClass: 'text-red-500',
            bgClass: 'bg-red-50',
            badgeClass: 'bg-red-100 text-red-800',
            label: 'No aprobado',
            title: 'No pudimos verificar tu transferencia',
            message: 'El comprobante enviado no pudo ser validado. Puedes intentarlo de nuevo o pagar con tarjeta.',
        },
        expired: {
            icon: AlertTriangle,
            iconClass: 'text-gray-400',
            bgClass: 'bg-gray-50',
            badgeClass: 'bg-gray-100 text-gray-700',
            label: 'Expirado',
            title: 'Verificación expirada',
            message: 'El plazo de 12 horas para revisar tu comprobante venció sin respuesta. Por favor, intenta de nuevo.',
        },
    }[status] ?? {
        icon: Clock,
        iconClass: 'text-gray-400',
        bgClass: 'bg-gray-50',
        badgeClass: 'bg-gray-100 text-gray-600',
        label: 'Sin verificación',
        title: 'Sin comprobante enviado',
        message: 'No encontramos un comprobante pendiente. Vuelve a la página de pago para enviarlo.',
    }
})

const timeLeft = computed(() => {
    if (!props.verification?.deadline_at || props.verification.status !== 'pending') return null
    const deadline = new Date(props.verification.deadline_at)
    const diff = deadline - now.value
    if (diff <= 0) return '00:00'
    const hours   = Math.floor(diff / 3_600_000)
    const minutes = Math.floor((diff % 3_600_000) / 60_000)
    return `${String(hours).padStart(2, '0')}h ${String(minutes).padStart(2, '0')}m`
})
</script>

<template>
    <Head title="Verificación de Transferencia — WaOrder" />

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-blue-50 flex items-center justify-center py-12 px-4">
        <div class="w-full max-w-md">

            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-2.5">
                    <img src="/images/logo.png" alt="WaOrder" class="h-10" />
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-8">

                <!-- Icon -->
                <div :class="['w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-5', statusConfig.bgClass]">
                    <component :is="statusConfig.icon" :class="['w-8 h-8', statusConfig.iconClass]" />
                </div>

                <!-- Badge + title -->
                <div class="text-center mb-6">
                    <span :class="['inline-block text-xs font-semibold px-3 py-1 rounded-full mb-3', statusConfig.badgeClass]">
                        {{ statusConfig.label }}
                    </span>
                    <h2 class="text-xl font-bold text-gray-900">{{ statusConfig.title }}</h2>
                    <p class="text-sm text-gray-500 mt-2 leading-relaxed">{{ statusConfig.message }}</p>
                </div>

                <!-- Admin notes if rejected -->
                <div v-if="verification?.status === 'rejected' && verification.admin_notes"
                     class="mb-5 p-3 bg-red-50 border border-red-100 rounded-xl text-sm text-red-700">
                    <span class="font-medium">Motivo: </span>{{ verification.admin_notes }}
                </div>

                <!-- Transfer summary (pending/rejected/expired) -->
                <div v-if="verification && verification.status !== 'approved'"
                     class="bg-gray-50 rounded-xl border border-gray-200 p-4 mb-5 text-sm space-y-2">
                    <div v-if="verification.bank_account" class="flex justify-between">
                        <span class="text-gray-500">Banco</span>
                        <span class="font-medium text-gray-800">{{ verification.bank_account.bank_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Monto</span>
                        <span class="font-medium text-gray-800">{{ parseFloat(verification.amount).toLocaleString('es-DO', { style: 'currency', currency: 'DOP' }) }}</span>
                    </div>
                    <div v-if="verification.reference_number" class="flex justify-between">
                        <span class="text-gray-500">Referencia</span>
                        <span class="font-medium text-gray-800">{{ verification.reference_number }}</span>
                    </div>
                </div>

                <!-- Countdown (pending only) -->
                <div v-if="verification?.status === 'pending' && timeLeft"
                     class="flex items-center justify-center gap-2 text-sm text-amber-700 bg-amber-50 rounded-xl p-3 mb-5">
                    <Clock class="w-4 h-4" />
                    <span>Tiempo restante para revisión: <strong>{{ timeLeft }}</strong></span>
                </div>

                <!-- Actions -->
                <div class="space-y-3">
                    <!-- Approved → setup -->
                    <a v-if="verification?.status === 'approved'"
                       href="/setup"
                       class="flex items-center justify-center gap-2 w-full py-3 px-6 bg-green-600 text-white font-semibold rounded-xl hover:bg-green-700 transition text-sm">
                        <CheckCircle class="w-4 h-4" />
                        Ir a configurar mi restaurante
                    </a>

                    <!-- Rejected / Expired → try again -->
                    <a v-if="['rejected', 'expired'].includes(verification?.status)"
                       href="/register/payment"
                       class="flex items-center justify-center gap-2 w-full py-3 px-6 bg-[#0052FF] text-white font-semibold rounded-xl hover:bg-[#0047DB] transition text-sm">
                        <RefreshCw class="w-4 h-4" />
                        Intentar de nuevo
                    </a>

                    <!-- No verification → back to payment -->
                    <a v-if="!verification"
                       href="/register/payment"
                       class="flex items-center justify-center gap-2 w-full py-3 px-6 bg-[#0052FF] text-white font-semibold rounded-xl hover:bg-[#0047DB] transition text-sm">
                        <ArrowLeft class="w-4 h-4" />
                        Volver al pago
                    </a>
                </div>

                <!-- Info for pending -->
                <p v-if="verification?.status === 'pending'" class="mt-5 text-xs text-center text-gray-400">
                    Puedes cerrar esta página. Te notificaremos por correo cuando se procese tu comprobante.
                </p>
            </div>

            <div class="text-center mt-4">
                <a href="/register" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft class="w-3.5 h-3.5" />
                    Volver al registro
                </a>
            </div>
        </div>
    </div>
</template>
