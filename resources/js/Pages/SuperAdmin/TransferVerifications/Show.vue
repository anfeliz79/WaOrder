<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import {
    ArrowLeft, Clock, CheckCircle, XCircle, AlertTriangle,
    Building2, FileText, ImageIcon, Download, User
} from 'lucide-vue-next'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const props = defineProps({
    verification: Object,
})

const v = computed(() => props.verification)

const approveForm = useForm({ notes: '' })
const rejectForm  = useForm({ notes: '' })

const showApprovePanel = ref(false)
const showRejectPanel  = ref(false)

const approve = () => {
    approveForm.post(`/superadmin/transfer-verifications/${v.value.id}/approve`, {
        onSuccess: () => { showApprovePanel.value = false },
    })
}

const reject = () => {
    rejectForm.post(`/superadmin/transfer-verifications/${v.value.id}/reject`, {
        onSuccess: () => { showRejectPanel.value = false },
    })
}

const statusConfig = {
    pending:  { label: 'Pendiente', class: 'bg-amber-100 text-amber-800',  icon: Clock },
    approved: { label: 'Aprobado',  class: 'bg-green-100 text-green-800',  icon: CheckCircle },
    rejected: { label: 'Rechazado', class: 'bg-red-100 text-red-800',      icon: XCircle },
    expired:  { label: 'Expirado',  class: 'bg-gray-100 text-gray-600',    icon: AlertTriangle },
}

const badge = computed(() => statusConfig[v.value.status] ?? statusConfig.expired)

const formatDate = (iso) => {
    if (!iso) return '—'
    return new Date(iso).toLocaleString('es-DO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const formatCurrency = (amount) =>
    parseFloat(amount).toLocaleString('es-DO', { style: 'currency', currency: 'DOP', maximumFractionDigits: 2 })
</script>

<template>
    <SuperAdminLayout :title="`Transferencia #${v.id}`">
        <Head :title="`Transferencia #${v.id}`" />

        <!-- Back -->
        <Link href="/superadmin/transfer-verifications"
              class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 mb-6 transition">
            <ArrowLeft class="w-4 h-4" />
            Volver a transferencias
        </Link>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- LEFT: Evidence + Status -->
            <div class="lg:col-span-2 space-y-5">

                <!-- Status card -->
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-gray-900">Verificación #{{ v.id }}</h2>
                        <span :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium', badge.class]">
                            <component :is="badge.icon" class="w-4 h-4" />
                            {{ badge.label }}
                        </span>
                    </div>

                    <!-- Deadline warning -->
                    <div v-if="v.status === 'pending'"
                         :class="['flex items-center gap-2 text-sm rounded-lg p-3 mb-4', v.is_over_deadline ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700']">
                        <Clock class="w-4 h-4 shrink-0" />
                        <span v-if="v.is_over_deadline">El plazo de 12 horas ha vencido sin acción.</span>
                        <span v-else>Plazo de respuesta: <strong>{{ v.hours_left }}h restantes</strong> (vence {{ formatDate(v.deadline_at) }})</span>
                    </div>

                    <!-- Details grid -->
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-400 text-xs mb-0.5">Monto</p>
                            <p class="font-bold text-gray-900 text-lg">{{ formatCurrency(v.amount) }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs mb-0.5">Plan</p>
                            <p class="font-medium text-gray-800">{{ v.subscription?.plan?.name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs mb-0.5">Referencia</p>
                            <p class="font-medium text-gray-800">{{ v.reference_number ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs mb-0.5">Enviado el</p>
                            <p class="font-medium text-gray-800">{{ formatDate(v.created_at) }}</p>
                        </div>
                    </div>

                    <!-- Admin notes (if already processed) -->
                    <div v-if="v.admin_notes && !v.is_pending"
                         :class="['mt-4 p-3 rounded-lg text-sm', v.status === 'approved' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800']">
                        <p class="font-semibold mb-0.5">Nota del admin</p>
                        <p>{{ v.admin_notes }}</p>
                    </div>
                </div>

                <!-- Evidence viewer -->
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <FileText class="w-4 h-4 text-gray-500" />
                        <h3 class="font-semibold text-gray-800">Comprobante adjunto</h3>
                    </div>

                    <!-- Image preview -->
                    <div v-if="v.is_image && v.evidence_url"
                         class="rounded-xl overflow-hidden border border-gray-200 bg-gray-50">
                        <img :src="v.evidence_url" :alt="v.evidence_name"
                             class="w-full max-h-[480px] object-contain" />
                    </div>

                    <!-- PDF / non-image download -->
                    <div v-else class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <FileText class="w-5 h-5 text-[#0052FF]" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-800 truncate">{{ v.evidence_name ?? 'Comprobante' }}</p>
                            <p class="text-xs text-gray-400">Documento adjunto</p>
                        </div>
                        <a v-if="v.evidence_url" :href="v.evidence_url" target="_blank"
                           class="flex items-center gap-1.5 px-3 py-2 bg-[#0052FF] text-white text-sm rounded-lg hover:bg-[#0047DB] transition">
                            <Download class="w-3.5 h-3.5" />
                            Descargar
                        </a>
                    </div>

                    <a v-if="v.evidence_url" :href="v.evidence_url" target="_blank"
                       class="inline-flex items-center gap-1.5 mt-3 text-sm text-[#0052FF] hover:underline">
                        <Download class="w-3.5 h-3.5" />
                        Abrir en nueva pestaña
                    </a>
                </div>
            </div>

            <!-- RIGHT: Tenant info + Actions -->
            <div class="space-y-5">

                <!-- Tenant card -->
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                        <User class="w-4 h-4 text-gray-400" />
                        Restaurante
                    </h3>
                    <p class="font-bold text-gray-900">{{ v.tenant?.name }}</p>
                    <p class="text-sm text-gray-400">{{ v.tenant?.slug }}</p>
                    <Link :href="`/superadmin/tenants/${v.tenant?.id}/edit`"
                          class="inline-block mt-2 text-xs text-[#0052FF] hover:underline">
                        Ver en SuperAdmin →
                    </Link>
                </div>

                <!-- Bank account -->
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                        <Building2 class="w-4 h-4 text-gray-400" />
                        Cuenta destino
                    </h3>
                    <div class="space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Banco</span>
                            <span class="font-medium">{{ v.bank_account?.bank_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Titular</span>
                            <span class="font-medium">{{ v.bank_account?.account_holder_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Cuenta</span>
                            <span class="font-mono text-gray-800">{{ v.bank_account?.account_number }}</span>
                        </div>
                    </div>
                </div>

                <!-- Verified by (if processed) -->
                <div v-if="v.verified_by && v.verified_at" class="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 class="font-semibold text-gray-800 mb-3">Procesado por</h3>
                    <p class="text-sm text-gray-800 font-medium">{{ v.verified_by?.name }}</p>
                    <p class="text-xs text-gray-400">{{ formatDate(v.verified_at) }}</p>
                </div>

                <!-- Action panel (pending only) -->
                <div v-if="v.status === 'pending'" class="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 class="font-semibold text-gray-800 mb-4">Acción</h3>

                    <!-- Approve -->
                    <div v-if="!showRejectPanel">
                        <button v-if="!showApprovePanel"
                                @click="showApprovePanel = true"
                                class="w-full flex items-center justify-center gap-2 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition text-sm mb-3">
                            <CheckCircle class="w-4 h-4" />
                            Aprobar transferencia
                        </button>
                        <div v-else class="space-y-3">
                            <textarea v-model="approveForm.notes" rows="3" placeholder="Nota opcional para el restaurante…"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400 resize-none" />
                            <div class="flex gap-2">
                                <button @click="showApprovePanel = false"
                                        class="flex-1 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                                    Cancelar
                                </button>
                                <button @click="approve" :disabled="approveForm.processing"
                                        class="flex-1 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition disabled:opacity-50">
                                    {{ approveForm.processing ? 'Aprobando…' : 'Confirmar' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Reject -->
                    <div v-if="!showApprovePanel">
                        <button v-if="!showRejectPanel"
                                @click="showRejectPanel = true"
                                class="w-full flex items-center justify-center gap-2 py-2.5 border border-red-200 text-red-600 hover:bg-red-50 font-medium rounded-lg transition text-sm">
                            <XCircle class="w-4 h-4" />
                            Rechazar transferencia
                        </button>
                        <div v-else class="space-y-3">
                            <textarea v-model="rejectForm.notes" rows="3" placeholder="Motivo del rechazo (se mostrará al restaurante)…"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 resize-none" />
                            <div class="flex gap-2">
                                <button @click="showRejectPanel = false"
                                        class="flex-1 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                                    Cancelar
                                </button>
                                <button @click="reject" :disabled="rejectForm.processing"
                                        class="flex-1 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition disabled:opacity-50">
                                    {{ rejectForm.processing ? 'Rechazando…' : 'Confirmar rechazo' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
