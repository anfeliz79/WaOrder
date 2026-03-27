<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Truck, Bike, Car, Plus, UserX, Pencil, QrCode, Smartphone, RefreshCw } from 'lucide-vue-next';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppCard from '@/Components/AppCard.vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppSwitch from '@/Components/AppSwitch.vue';
import AppModal from '@/Components/AppModal.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import QrcodeVue from 'qrcode.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    drivers: Array,
});

const showAdd = ref(false);
const editingDriver = ref(null);
const deleteTarget = ref(null);

// QR linking
const qrDriver = ref(null);
const qrData = ref('');
const qrExpiresAt = ref(null);
const qrLoading = ref(false);
const qrCountdown = ref('');
let countdownInterval = null;

const form = useForm({
    name: '',
    phone: '',
    vehicle_type: 'moto',
    vehicle_plate: '',
});

const editForm = useForm({
    name: '',
    phone: '',
    vehicle_type: 'moto',
    vehicle_plate: '',
});

const submitDriver = () => {
    form.post('/drivers', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showAdd.value = false;
        },
    });
};

const openEdit = (driver) => {
    editingDriver.value = driver.id;
    editForm.name = driver.name;
    editForm.phone = driver.phone;
    editForm.vehicle_type = driver.vehicle_type || 'moto';
    editForm.vehicle_plate = driver.vehicle_plate || '';
};

const submitEdit = () => {
    editForm.put(`/drivers/${editingDriver.value}`, {
        preserveScroll: true,
        onSuccess: () => { editingDriver.value = null; },
    });
};

const toggleAvailability = (driverId) => {
    router.patch(`/drivers/${driverId}/availability`, {}, { preserveScroll: true });
};

const confirmDelete = () => {
    if (!deleteTarget.value) return;
    router.delete(`/drivers/${deleteTarget.value}`, {
        preserveScroll: true,
        onFinish: () => { deleteTarget.value = null; },
    });
};

const generateQr = async (driver) => {
    qrDriver.value = driver;
    qrLoading.value = true;
    qrData.value = '';

    try {
        const response = await fetch(`/drivers/${driver.id}/qr-token`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json();
        qrData.value = data.qr_data;
        qrExpiresAt.value = new Date(data.expires_at);
        startCountdown();
    } catch (e) {
        console.error('Error generating QR:', e);
    } finally {
        qrLoading.value = false;
    }
};

const startCountdown = () => {
    if (countdownInterval) clearInterval(countdownInterval);
    countdownInterval = setInterval(() => {
        if (!qrExpiresAt.value) return;
        const diff = Math.max(0, Math.floor((qrExpiresAt.value - Date.now()) / 1000));
        if (diff <= 0) {
            qrCountdown.value = 'Expirado';
            qrData.value = '';
            clearInterval(countdownInterval);
            return;
        }
        const min = Math.floor(diff / 60);
        const sec = diff % 60;
        qrCountdown.value = `${min}:${sec.toString().padStart(2, '0')}`;
    }, 1000);
};

const closeQr = () => {
    qrDriver.value = null;
    qrData.value = '';
    qrExpiresAt.value = null;
    qrCountdown.value = '';
    if (countdownInterval) clearInterval(countdownInterval);
};

const vehicleIcons = { moto: Truck, carro: Car, bicicleta: Bike };
const vehicleLabels = { moto: 'Moto', carro: 'Carro', bicicleta: 'Bicicleta' };
const vehicleOptions = [
    { value: 'moto', label: 'Moto' },
    { value: 'carro', label: 'Carro' },
    { value: 'bicicleta', label: 'Bicicleta' },
];
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <AppBadge v-if="drivers?.length" variant="gray" size="md">{{ drivers.length }} mensajeros</AppBadge>
            <AppButton @click="showAdd = !showAdd" size="sm" :variant="showAdd ? 'ghost' : 'primary'">
                <template v-if="showAdd">Cancelar</template>
                <template v-else>
                    <Plus class="w-4 h-4" />
                    Agregar mensajero
                </template>
            </AppButton>
        </div>

        <!-- Add form -->
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            leave-active-class="transition-all duration-150 ease-in"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <AppCard v-if="showAdd" class="mb-6" title="Nuevo Mensajero">
                <form @submit.prevent="submitDriver" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <AppInput v-model="form.name" label="Nombre" required placeholder="Nombre completo" :error="form.errors.name" />
                    <AppInput v-model="form.phone" label="WhatsApp" required placeholder="+18091234567" hint="Numero con codigo de pais" :error="form.errors.phone" />
                    <AppSelect v-model="form.vehicle_type" label="Vehiculo" :options="vehicleOptions" />
                    <AppInput v-model="form.vehicle_plate" label="Placa" placeholder="Opcional" />
                    <div class="sm:col-span-2">
                        <AppButton type="submit" :loading="form.processing">Registrar mensajero</AppButton>
                    </div>
                </form>
            </AppCard>
        </Transition>

        <!-- Driver cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="driver in drivers" :key="driver.id"
                 class="bg-surface rounded-xl border border-gray-200 shadow-sm p-5 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center">
                            <component :is="vehicleIcons[driver.vehicle_type] || Truck" class="w-5 h-5 text-gray-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 text-sm">{{ driver.name }}</h3>
                            <p class="text-xs text-gray-400">{{ driver.phone }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <button @click="generateQr(driver)" class="text-gray-300 hover:text-primary-500 transition-colors p-1" title="Vincular App">
                            <QrCode class="w-4 h-4" />
                        </button>
                        <button @click="openEdit(driver)" class="text-gray-300 hover:text-primary-500 transition-colors p-1">
                            <Pencil class="w-4 h-4" />
                        </button>
                        <button @click="deleteTarget = driver.id" class="text-gray-300 hover:text-red-500 transition-colors p-1">
                            <UserX class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <AppBadge variant="gray" size="xs">
                            {{ vehicleLabels[driver.vehicle_type] || '-' }}
                        </AppBadge>
                        <span v-if="driver.vehicle_plate" class="text-xs text-gray-400">{{ driver.vehicle_plate }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <AppBadge v-if="driver.app_linked" variant="success" size="xs">
                            <Smartphone class="w-3 h-3 inline -mt-0.5" /> App
                        </AppBadge>
                        <AppBadge variant="primary" size="xs">
                            {{ driver.completed_deliveries ?? 0 }} entregas
                        </AppBadge>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100">
                    <AppSwitch
                        :modelValue="driver.is_available"
                        @update:modelValue="toggleAvailability(driver.id)"
                        :label="driver.is_available ? 'Disponible' : 'No disponible'"
                    />
                </div>
            </div>
        </div>

        <AppEmptyState
            v-if="!drivers?.length"
            :icon="Truck"
            title="Sin mensajeros"
            description="Agrega mensajeros para asignarles entregas"
            actionLabel="+ Agregar mensajero"
            @action="showAdd = true"
        />

        <!-- Edit driver modal -->
        <AppModal :show="!!editingDriver" title="Editar mensajero" @close="editingDriver = null">
            <form @submit.prevent="submitEdit" class="space-y-4">
                <AppInput v-model="editForm.name" label="Nombre" required placeholder="Nombre completo" :error="editForm.errors.name" />
                <AppInput v-model="editForm.phone" label="WhatsApp" required placeholder="18091234567" hint="Numero con codigo de pais" :error="editForm.errors.phone" />
                <AppSelect v-model="editForm.vehicle_type" label="Vehiculo" :options="vehicleOptions" />
                <AppInput v-model="editForm.vehicle_plate" label="Placa" placeholder="Opcional" />
            </form>
            <template #footer>
                <AppButton variant="ghost" @click="editingDriver = null">Cancelar</AppButton>
                <AppButton variant="primary" @click="submitEdit" :loading="editForm.processing">Guardar</AppButton>
            </template>
        </AppModal>

        <!-- Delete confirmation modal -->
        <AppModal :show="!!deleteTarget" title="Desactivar mensajero" @close="deleteTarget = null">
            <p class="text-sm text-gray-600">Estas seguro de que deseas desactivar este mensajero? No podra recibir nuevas entregas.</p>
            <template #footer>
                <AppButton variant="ghost" @click="deleteTarget = null">Cancelar</AppButton>
                <AppButton variant="danger" @click="confirmDelete">Desactivar</AppButton>
            </template>
        </AppModal>

        <!-- QR Code modal -->
        <AppModal :show="!!qrDriver" title="Vincular App de Delivery" @close="closeQr">
            <div class="text-center">
                <p class="text-sm text-gray-600 mb-4">
                    Escanea este QR desde la app de delivery para vincularla a <strong>{{ qrDriver?.name }}</strong>
                </p>

                <div v-if="qrLoading" class="flex items-center justify-center py-12">
                    <RefreshCw class="w-8 h-8 text-gray-400 animate-spin" />
                </div>

                <div v-else-if="qrData" class="inline-block bg-white p-4 rounded-xl border-2 border-gray-100">
                    <QrcodeVue :value="qrData" :size="220" level="M" />
                </div>

                <div v-else class="py-12 text-gray-400 text-sm">
                    QR expirado. Genera uno nuevo.
                </div>

                <p v-if="qrCountdown && qrCountdown !== 'Expirado'" class="text-xs text-gray-400 mt-3">
                    Expira en {{ qrCountdown }}
                </p>
                <p v-else-if="qrCountdown === 'Expirado'" class="text-xs text-red-500 mt-3">
                    QR expirado
                </p>
            </div>
            <template #footer>
                <AppButton variant="ghost" @click="closeQr">Cerrar</AppButton>
                <AppButton variant="primary" @click="generateQr(qrDriver)" :loading="qrLoading">
                    <RefreshCw class="w-4 h-4" /> Regenerar
                </AppButton>
            </template>
        </AppModal>
    </div>
</template>
