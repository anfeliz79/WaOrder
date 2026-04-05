<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { Building2, Plus, Edit2, Trash2, CheckCircle, XCircle, X } from 'lucide-vue-next'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const props = defineProps({
    accounts: Array,
})

// ── Modal state ──────────────────────────────────────────────────────────────
const showModal  = ref(false)
const editTarget = ref(null) // null = create, object = edit

const emptyForm = () => ({
    bank_name:           '',
    account_holder_name: '',
    account_number:      '',
    account_type:        'savings',
    currency:            'DOP',
    instructions:        '',
    is_active:           true,
})

const form = useForm(emptyForm())

const openCreate = () => {
    editTarget.value = null
    form.reset()
    Object.assign(form, emptyForm())
    showModal.value = true
}

const openEdit = (account) => {
    editTarget.value = account
    form.bank_name           = account.bank_name
    form.account_holder_name = account.account_holder_name
    form.account_number      = account.account_number
    form.account_type        = account.account_type
    form.currency            = account.currency
    form.instructions        = account.instructions ?? ''
    form.is_active           = account.is_active
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
    editTarget.value = null
    form.reset()
}

const submit = () => {
    if (editTarget.value) {
        form.put(`/superadmin/bank-accounts/${editTarget.value.id}`, {
            onSuccess: closeModal,
        })
    } else {
        form.post('/superadmin/bank-accounts', {
            onSuccess: closeModal,
        })
    }
}

const deleteAccount = (account) => {
    if (!confirm(`¿Eliminar la cuenta de ${account.bank_name}?`)) return
    router.delete(`/superadmin/bank-accounts/${account.id}`)
}

const accountTypeLabel = (type) => type === 'savings' ? 'Ahorro' : 'Corriente'
</script>

<template>
    <SuperAdminLayout title="Cuentas Bancarias">
        <Head title="Cuentas Bancarias" />

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                    <Building2 class="w-5 h-5 text-[#0052FF]" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Cuentas Bancarias</h1>
                    <p class="text-sm text-gray-500">Para recibir pagos por transferencia</p>
                </div>
            </div>
            <button @click="openCreate"
                    class="flex items-center gap-2 px-4 py-2 bg-[#0052FF] hover:bg-[#0047DB] text-white text-sm font-semibold rounded-lg transition">
                <Plus class="w-4 h-4" />
                Nueva Cuenta
            </button>
        </div>

        <!-- Empty state -->
        <div v-if="!accounts.length" class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center">
            <Building2 class="w-10 h-10 text-gray-300 mx-auto mb-3" />
            <p class="text-gray-500 font-medium mb-1">Sin cuentas configuradas</p>
            <p class="text-sm text-gray-400 mb-4">Agrega una cuenta bancaria para que los clientes puedan transferir.</p>
            <button @click="openCreate"
                    class="px-4 py-2 bg-[#0052FF] text-white text-sm font-semibold rounded-lg hover:bg-[#0047DB] transition">
                Agregar primera cuenta
            </button>
        </div>

        <!-- Accounts grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <div v-for="account in accounts" :key="account.id"
                 :class="['bg-white rounded-xl border p-5 transition', account.is_active ? 'border-gray-200' : 'border-gray-100 opacity-60']">

                <!-- Bank header -->
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                            <Building2 class="w-4 h-4 text-[#0052FF]" />
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900 text-sm leading-tight">{{ account.bank_name }}</p>
                            <p class="text-xs text-gray-400">{{ accountTypeLabel(account.account_type) }} · {{ account.currency }}</p>
                        </div>
                    </div>
                    <span :class="['flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full', account.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                        <CheckCircle v-if="account.is_active" class="w-3 h-3" />
                        <XCircle v-else class="w-3 h-3" />
                        {{ account.is_active ? 'Activa' : 'Inactiva' }}
                    </span>
                </div>

                <!-- Details -->
                <div class="space-y-1.5 text-sm mb-4">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Titular</span>
                        <span class="font-medium text-gray-800">{{ account.account_holder_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Número</span>
                        <span class="font-mono text-gray-800">{{ account.account_number }}</span>
                    </div>
                </div>

                <div v-if="account.instructions" class="text-xs text-gray-500 bg-gray-50 rounded-lg p-2 mb-4 line-clamp-2">
                    {{ account.instructions }}
                </div>

                <!-- Actions -->
                <div class="flex gap-2 pt-2 border-t border-gray-100">
                    <button @click="openEdit(account)"
                            class="flex-1 flex items-center justify-center gap-1.5 py-1.5 text-sm text-gray-600 hover:text-[#0052FF] hover:bg-blue-50 rounded-lg transition">
                        <Edit2 class="w-3.5 h-3.5" />
                        Editar
                    </button>
                    <button @click="deleteAccount(account)"
                            class="flex-1 flex items-center justify-center gap-1.5 py-1.5 text-sm text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                        <Trash2 class="w-3.5 h-3.5" />
                        Eliminar
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeModal" />
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">

                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-lg font-bold text-gray-900">
                            {{ editTarget ? 'Editar Cuenta' : 'Nueva Cuenta Bancaria' }}
                        </h3>
                        <button @click="closeModal" class="p-1.5 hover:bg-gray-100 rounded-lg transition">
                            <X class="w-5 h-5 text-gray-500" />
                        </button>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Banco</label>
                            <input v-model="form.bank_name" type="text" placeholder="ej. Banco Popular Dominicano"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#3385ff]" />
                            <p v-if="form.errors.bank_name" class="text-xs text-red-500 mt-1">{{ form.errors.bank_name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Titular de la cuenta</label>
                            <input v-model="form.account_holder_name" type="text" placeholder="Nombre del titular"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#3385ff]" />
                            <p v-if="form.errors.account_holder_name" class="text-xs text-red-500 mt-1">{{ form.errors.account_holder_name }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Número de cuenta</label>
                                <input v-model="form.account_number" type="text" placeholder="0000000000"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#3385ff]" />
                                <p v-if="form.errors.account_number" class="text-xs text-red-500 mt-1">{{ form.errors.account_number }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                                <select v-model="form.account_type"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#3385ff]">
                                    <option value="savings">Ahorro</option>
                                    <option value="checking">Corriente</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                                <select v-model="form.currency"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#3385ff]">
                                    <option value="DOP">DOP (RD$)</option>
                                    <option value="USD">USD ($)</option>
                                </select>
                            </div>
                            <div class="flex items-end pb-0.5">
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input v-model="form.is_active" type="checkbox"
                                           class="w-4 h-4 rounded text-[#0052FF] focus:ring-[#3385ff]" />
                                    Cuenta activa
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instrucciones adicionales <span class="text-gray-400 font-normal">(opcional)</span></label>
                            <textarea v-model="form.instructions" rows="2"
                                      placeholder="ej. Incluir el nombre del restaurante en la descripción..."
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#3385ff] resize-none" />
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="closeModal"
                                    class="flex-1 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition text-sm">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing"
                                    class="flex-1 py-2.5 bg-[#0052FF] hover:bg-[#0047DB] text-white font-semibold rounded-lg transition text-sm disabled:opacity-50">
                                {{ form.processing ? 'Guardando…' : (editTarget ? 'Guardar cambios' : 'Crear cuenta') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </SuperAdminLayout>
</template>
