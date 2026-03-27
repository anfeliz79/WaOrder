<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { UserPlus, Pencil, UserX, Shield, ClipboardList } from 'lucide-vue-next';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppCard from '@/Components/AppCard.vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppModal from '@/Components/AppModal.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    users: Array,
    branches: Array,
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);

const showAdd = ref(false);
const editingUser = ref(null);
const deleteTarget = ref(null);

const roleLabels = {
    admin: 'Administrador',
    gestor: 'Gestor de Pedidos',
};

const roleOptions = [
    { value: 'admin', label: 'Administrador' },
    { value: 'gestor', label: 'Gestor de Pedidos' },
];

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: 'gestor',
    branch_ids: [],
});

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'gestor',
    branch_ids: [],
});

const submitUser = () => {
    form.post('/users', {
        preserveScroll: true,
        onSuccess: () => {
            showAdd.value = false;
            form.reset();
        },
    });
};

const startEdit = (user) => {
    editingUser.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.role = user.role;
    editForm.branch_ids = user.branches?.map(b => b.id) || [];
};

const updateUser = () => {
    editForm.put(`/users/${editingUser.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingUser.value = null;
            editForm.reset();
        },
    });
};

const confirmDelete = (user) => {
    deleteTarget.value = user;
};

const deactivateUser = () => {
    useForm({}).delete(`/users/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteTarget.value = null;
        },
    });
};

const toggleBranch = (formRef, branchId) => {
    const idx = formRef.branch_ids.indexOf(branchId);
    if (idx > -1) {
        formRef.branch_ids.splice(idx, 1);
    } else {
        formRef.branch_ids.push(branchId);
    }
};
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm text-gray-500">Administra los usuarios y sus permisos</p>
            <AppButton @click="showAdd = true" size="sm">
                <UserPlus class="w-4 h-4 mr-1" /> Nuevo usuario
            </AppButton>
        </div>

        <!-- Empty state -->
        <AppEmptyState
            v-if="!users.length"
            title="Sin usuarios"
            description="Crea tu primer usuario para comenzar."
            :icon="UserPlus"
        />

        <!-- Users table -->
        <AppCard v-else>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-3 px-4 font-medium text-gray-500">Nombre</th>
                            <th class="text-left py-3 px-4 font-medium text-gray-500">Email</th>
                            <th class="text-left py-3 px-4 font-medium text-gray-500">Rol</th>
                            <th class="text-left py-3 px-4 font-medium text-gray-500">Sucursales</th>
                            <th class="text-left py-3 px-4 font-medium text-gray-500">Estado</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id" class="border-b border-gray-50 hover:bg-gray-50/50">
                            <td class="py-3 px-4">
                                <div class="font-medium text-gray-900">{{ user.name }}</div>
                            </td>
                            <td class="py-3 px-4 text-gray-600">{{ user.email }}</td>
                            <td class="py-3 px-4">
                                <AppBadge :variant="user.role === 'admin' ? 'primary' : 'default'" size="sm">
                                    <component :is="user.role === 'admin' ? Shield : ClipboardList" class="w-3 h-3 mr-1" />
                                    {{ roleLabels[user.role] || user.role }}
                                </AppBadge>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="branch in user.branches"
                                        :key="branch.id"
                                        class="inline-block px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded-full"
                                    >
                                        {{ branch.name }}
                                    </span>
                                    <span v-if="!user.branches?.length" class="text-xs text-gray-400">Sin asignar</span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <AppBadge :variant="user.is_active ? 'success' : 'danger'" size="sm">
                                    {{ user.is_active ? 'Activo' : 'Inactivo' }}
                                </AppBadge>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click="startEdit(user)"
                                            class="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors">
                                        <Pencil class="w-4 h-4" />
                                    </button>
                                    <button v-if="user.id !== currentUserId"
                                            @click="confirmDelete(user)"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                        <UserX class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </AppCard>

        <!-- Add modal -->
        <AppModal :show="showAdd" @close="showAdd = false" title="Nuevo usuario">
            <form @submit.prevent="submitUser" class="space-y-4">
                <AppInput v-model="form.name" label="Nombre" :error="form.errors.name" required />
                <AppInput v-model="form.email" label="Email" type="email" :error="form.errors.email" required />
                <AppInput v-model="form.password" label="Contrasena" type="password" :error="form.errors.password" required />
                <AppSelect v-model="form.role" label="Rol" :options="roleOptions" :error="form.errors.role" />

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sucursales *</label>
                    <div class="space-y-2">
                        <label v-for="branch in branches" :key="branch.id"
                               class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                               :class="form.branch_ids.includes(branch.id) ? 'border-primary-300 bg-primary-50' : 'border-gray-200 hover:bg-gray-50'">
                            <input type="checkbox"
                                   :checked="form.branch_ids.includes(branch.id)"
                                   @change="toggleBranch(form, branch.id)"
                                   class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm text-gray-700">{{ branch.name }}</span>
                        </label>
                    </div>
                    <p v-if="form.errors.branch_ids" class="text-sm text-red-500 mt-1">{{ form.errors.branch_ids }}</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <AppButton type="button" variant="ghost" @click="showAdd = false">Cancelar</AppButton>
                    <AppButton type="submit" :loading="form.processing">Crear usuario</AppButton>
                </div>
            </form>
        </AppModal>

        <!-- Edit modal -->
        <AppModal :show="!!editingUser" @close="editingUser = null" title="Editar usuario">
            <form @submit.prevent="updateUser" class="space-y-4">
                <AppInput v-model="editForm.name" label="Nombre" :error="editForm.errors.name" required />
                <AppInput v-model="editForm.email" label="Email" type="email" :error="editForm.errors.email" required />
                <AppInput v-model="editForm.password" label="Nueva contrasena (dejar vacio para mantener)" type="password" :error="editForm.errors.password" />
                <AppSelect v-model="editForm.role" label="Rol" :options="roleOptions" :error="editForm.errors.role"
                           :disabled="editingUser?.id === currentUserId" />

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sucursales *</label>
                    <div class="space-y-2">
                        <label v-for="branch in branches" :key="branch.id"
                               class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                               :class="editForm.branch_ids.includes(branch.id) ? 'border-primary-300 bg-primary-50' : 'border-gray-200 hover:bg-gray-50'">
                            <input type="checkbox"
                                   :checked="editForm.branch_ids.includes(branch.id)"
                                   @change="toggleBranch(editForm, branch.id)"
                                   class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm text-gray-700">{{ branch.name }}</span>
                        </label>
                    </div>
                    <p v-if="editForm.errors.branch_ids" class="text-sm text-red-500 mt-1">{{ editForm.errors.branch_ids }}</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <AppButton type="button" variant="ghost" @click="editingUser = null">Cancelar</AppButton>
                    <AppButton type="submit" :loading="editForm.processing">Guardar cambios</AppButton>
                </div>
            </form>
        </AppModal>

        <!-- Delete confirmation -->
        <AppModal :show="!!deleteTarget" @close="deleteTarget = null" title="Desactivar usuario">
            <p class="text-gray-600">
                Estas seguro que deseas desactivar a <strong>{{ deleteTarget?.name }}</strong>?
                El usuario no podra acceder al sistema.
            </p>
            <div class="flex justify-end gap-2 mt-6">
                <AppButton variant="ghost" @click="deleteTarget = null">Cancelar</AppButton>
                <AppButton variant="danger" @click="deactivateUser">Desactivar</AppButton>
            </div>
        </AppModal>
    </div>
</template>
