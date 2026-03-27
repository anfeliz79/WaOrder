<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Building2, Plus, Pencil, MapPin, Phone, Ruler } from 'lucide-vue-next';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import AppCard from '@/Components/AppCard.vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppSwitch from '@/Components/AppSwitch.vue';
import AppModal from '@/Components/AppModal.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import LocationPicker from '@/Components/LocationPicker.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    branches: Array,
});

const showAdd = ref(false);
const editingBranch = ref(null);

const form = useForm({
    name: '',
    address: '',
    latitude: '',
    longitude: '',
    phone: '',
    max_delivery_distance_km: 10,
});

const editForm = useForm({
    name: '',
    address: '',
    latitude: '',
    longitude: '',
    phone: '',
    max_delivery_distance_km: 10,
});

const submitBranch = () => {
    form.post('/branches', {
        preserveScroll: true,
        onSuccess: () => {
            showAdd.value = false;
            form.reset();
        },
    });
};

const startEdit = (branch) => {
    editingBranch.value = branch;
    editForm.name = branch.name;
    editForm.address = branch.address;
    editForm.latitude = branch.latitude ? Number(branch.latitude) : '';
    editForm.longitude = branch.longitude ? Number(branch.longitude) : '';
    editForm.phone = branch.phone || '';
    editForm.max_delivery_distance_km = branch.max_delivery_distance_km;
};

const updateBranch = () => {
    editForm.put(`/branches/${editingBranch.value.id}`, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            editingBranch.value = null;
            editForm.reset();
        },
    });
};

const toggleActive = (branch) => {
    useForm({ is_active: !branch.is_active }).put(`/branches/${branch.id}`, {
        preserveScroll: true,
        preserveState: false,
    });
};
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <p class="text-sm text-gray-500">Administra las sucursales de tu negocio</p>
            </div>
            <AppButton @click="showAdd = true" size="sm">
                <Plus class="w-4 h-4 mr-1" /> Nueva sucursal
            </AppButton>
        </div>

        <!-- Empty state -->
        <AppEmptyState
            v-if="!branches.length"
            title="Sin sucursales"
            description="Crea tu primera sucursal para comenzar a recibir pedidos."
            :icon="Building2"
        />

        <!-- Branch cards -->
        <div v-else class="grid gap-4 md:grid-cols-2">
            <AppCard v-for="branch in branches" :key="branch.id" class="relative">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-semibold text-gray-900 truncate">{{ branch.name }}</h3>
                            <AppBadge :variant="branch.is_active ? 'success' : 'danger'" size="sm">
                                {{ branch.is_active ? 'Activa' : 'Inactiva' }}
                            </AppBadge>
                        </div>

                        <div class="mt-2 space-y-1.5">
                            <p class="text-sm text-gray-500 flex items-center gap-1.5">
                                <MapPin class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                {{ branch.address }}
                            </p>
                            <p v-if="branch.phone" class="text-sm text-gray-500 flex items-center gap-1.5">
                                <Phone class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                {{ branch.phone }}
                            </p>
                            <p class="text-sm text-gray-500 flex items-center gap-1.5">
                                <Ruler class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                Radio: {{ branch.max_delivery_distance_km }} km
                            </p>
                        </div>

                        <div class="flex items-center gap-4 mt-3 text-xs text-gray-400">
                            <span>{{ branch.orders_count ?? 0 }} ordenes</span>
                            <span>{{ branch.drivers_count ?? 0 }} mensajeros</span>
                            <span v-if="branch.latitude">
                                {{ Number(branch.latitude).toFixed(4) }}, {{ Number(branch.longitude).toFixed(4) }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 ml-3 shrink-0">
                        <button @click="startEdit(branch)"
                                class="p-2 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors">
                            <Pencil class="w-4 h-4" />
                        </button>
                        <AppSwitch :modelValue="branch.is_active" @update:modelValue="toggleActive(branch)" />
                    </div>
                </div>
            </AppCard>
        </div>

        <!-- Add modal -->
        <AppModal :show="showAdd" @close="showAdd = false" title="Nueva sucursal" maxWidth="lg">
            <form @submit.prevent="submitBranch" class="space-y-4">
                <AppInput v-model="form.name" label="Nombre" :error="form.errors.name" required placeholder="Sucursal Centro" />
                <AppInput v-model="form.address" label="Direccion" :error="form.errors.address" required placeholder="Calle Principal #123" />

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ubicacion</label>
                    <LocationPicker
                        v-model:latitude="form.latitude"
                        v-model:longitude="form.longitude"
                        @update:address="(addr) => { if (!form.address) form.address = addr; }"
                    />
                    <p v-if="form.errors.latitude" class="mt-1 text-sm text-red-600">{{ form.errors.latitude }}</p>
                    <p v-if="form.errors.longitude" class="mt-1 text-sm text-red-600">{{ form.errors.longitude }}</p>
                </div>

                <AppInput v-model="form.phone" label="Telefono" :error="form.errors.phone" placeholder="+1 809 000 0000" />
                <AppInput v-model="form.max_delivery_distance_km" label="Distancia maxima de delivery (km)" :error="form.errors.max_delivery_distance_km" type="number" step="0.5" min="0.5" required />

                <div class="flex justify-end gap-2 pt-2">
                    <AppButton type="button" variant="ghost" @click="showAdd = false">Cancelar</AppButton>
                    <AppButton type="submit" :loading="form.processing">Crear sucursal</AppButton>
                </div>
            </form>
        </AppModal>

        <!-- Edit modal -->
        <AppModal :show="!!editingBranch" @close="editingBranch = null" title="Editar sucursal" maxWidth="lg">
            <form @submit.prevent="updateBranch" class="space-y-4">
                <AppInput v-model="editForm.name" label="Nombre" :error="editForm.errors.name" required />
                <AppInput v-model="editForm.address" label="Direccion" :error="editForm.errors.address" required />

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ubicacion</label>
                    <LocationPicker
                        v-model:latitude="editForm.latitude"
                        v-model:longitude="editForm.longitude"
                        @update:address="(addr) => { if (!editForm.address) editForm.address = addr; }"
                    />
                    <p v-if="editForm.errors.latitude" class="mt-1 text-sm text-red-600">{{ editForm.errors.latitude }}</p>
                    <p v-if="editForm.errors.longitude" class="mt-1 text-sm text-red-600">{{ editForm.errors.longitude }}</p>
                </div>

                <AppInput v-model="editForm.phone" label="Telefono" :error="editForm.errors.phone" />
                <AppInput v-model="editForm.max_delivery_distance_km" label="Distancia maxima de delivery (km)" :error="editForm.errors.max_delivery_distance_km" type="number" step="0.5" min="0.5" required />

                <div class="flex justify-end gap-2 pt-2">
                    <AppButton type="button" variant="ghost" @click="editingBranch = null">Cancelar</AppButton>
                    <AppButton type="submit" :loading="editForm.processing">Guardar cambios</AppButton>
                </div>
            </form>
        </AppModal>
    </div>
</template>
