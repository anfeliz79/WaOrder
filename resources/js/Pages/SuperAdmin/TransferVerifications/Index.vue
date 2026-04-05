<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ArrowUpDown, Clock, CheckCircle, XCircle, AlertTriangle, Eye } from 'lucide-vue-next'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const props = defineProps({
    verifications: Object, // paginated
    filters: Object,
})

const statusOptions = [
    { value: '',         label: 'Todos' },
    { value: 'pending',  label: 'Pendientes' },
    { value: 'approved', label: 'Aprobados' },
    { value: 'rejected', label: 'Rechazados' },
    { value: 'expired',  label: 'Expirados' },
]

const selectedStatus = ref(props.filters?.status ?? '')

const applyFilter = () => {
    router.get('/superadmin/transfer-verifications', { status: selectedStatus.value || undefined }, { preserveState: true })
}

const statusBadge = (status) => ({
    pending:  { label: 'Pendiente',  class: 'bg-amber-100 text-amber-800',  icon: Clock },
    approved: { label: 'Aprobado',   class: 'bg-green-100 text-green-800',  icon: CheckCircle },
    rejected: { label: 'Rechazado',  class: 'bg-red-100 text-red-800',      icon: XCircle },
    expired:  { label: 'Expirado',   class: 'bg-gray-100 text-gray-600',    icon: AlertTriangle },
})[status] ?? { label: status, class: 'bg-gray-100 text-gray-600', icon: Clock }

const formatDate = (iso) => {
    if (!iso) return '—'
    return new Date(iso).toLocaleString('es-DO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const formatCurrency = (amount) =>
    parseFloat(amount).toLocaleString('es-DO', { style: 'currency', currency: 'DOP', maximumFractionDigits: 2 })
</script>

<template>
    <SuperAdminLayout title="Transferencias">
        <Head title="Verificaciones de Transferencia" />

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                    <ArrowUpDown class="w-5 h-5 text-[#0052FF]" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Transferencias Bancarias</h1>
                    <p class="text-sm text-gray-500">Comprobantes de pago enviados por restaurantes</p>
                </div>
            </div>

            <!-- Filter -->
            <div class="flex items-center gap-2">
                <select v-model="selectedStatus" @change="applyFilter"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#3385ff] bg-white">
                    <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </div>
        </div>

        <!-- Empty -->
        <div v-if="!verifications.data.length" class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
            <ArrowUpDown class="w-10 h-10 text-gray-300 mx-auto mb-3" />
            <p class="text-gray-500 font-medium">Sin verificaciones</p>
            <p class="text-sm text-gray-400">No hay transferencias que coincidan con el filtro seleccionado.</p>
        </div>

        <!-- Table -->
        <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Restaurante</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Plan</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Banco</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-600">Monto</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Estado</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Enviado</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600">Plazo</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="v in verifications.data" :key="v.id"
                            :class="['hover:bg-gray-50 transition', v.status === 'pending' ? 'bg-amber-50/30' : '']">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ v.tenant?.name ?? '—' }}</p>
                                <p class="text-xs text-gray-400">{{ v.tenant?.slug }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ v.subscription?.plan?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                <p>{{ v.bank_account?.bank_name ?? '—' }}</p>
                                <p class="text-xs text-gray-400 font-mono">{{ v.bank_account?.account_number }}</p>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ formatCurrency(v.amount) }}</td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium', statusBadge(v.status).class]">
                                    <component :is="statusBadge(v.status).icon" class="w-3 h-3" />
                                    {{ statusBadge(v.status).label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-xs">{{ formatDate(v.created_at) }}</td>
                            <td class="px-4 py-3 text-gray-600 text-xs">{{ formatDate(v.deadline_at) }}</td>
                            <td class="px-4 py-3">
                                <Link :href="`/superadmin/transfer-verifications/${v.id}`"
                                      class="flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-[#0052FF] hover:bg-blue-50 rounded-lg transition">
                                    <Eye class="w-3.5 h-3.5" />
                                    Revisar
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="verifications.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-200">
                <p class="text-sm text-gray-500">
                    {{ verifications.from }}–{{ verifications.to }} de {{ verifications.total }}
                </p>
                <div class="flex gap-2">
                    <Link v-if="verifications.prev_page_url" :href="verifications.prev_page_url"
                          class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Anterior
                    </Link>
                    <Link v-if="verifications.next_page_url" :href="verifications.next_page_url"
                          class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Siguiente
                    </Link>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
