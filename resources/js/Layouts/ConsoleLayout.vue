<script setup>
import { computed, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { LogOut, MonitorDot, Building2, ChevronsUpDown } from 'lucide-vue-next';
import AppToast from '@/Components/AppToast.vue';
import { useToast } from '@/Composables/useToast';
import { useOrderNotification } from '@/Composables/useOrderNotification';
import { getInitials, getAvatarColor } from '@/Utils/formatters';

const page = usePage();
const toast = useToast();

watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, { deep: true, immediate: true });

useOrderNotification();

const user = computed(() => page.props.auth?.user);
const branches = computed(() => page.props.auth?.branches || []);
const currentBranch = computed(() => page.props.auth?.current_branch);

const switchBranch = (branchId) => {
    router.post('/switch-branch', { branch_id: branchId }, { preserveState: false });
};
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Compact header -->
        <header class="h-14 bg-gray-900 flex items-center justify-between px-4 shadow-lg">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-lg flex items-center justify-center">
                        <MonitorDot class="w-4.5 h-4.5 text-white" />
                    </div>
                    <span class="text-white font-semibold text-sm sm:text-base">Consola de Pedidos</span>
                </div>

                <!-- Branch name / switcher -->
                <div class="hidden sm:flex items-center">
                    <span class="text-gray-600 mx-2">/</span>
                    <div v-if="branches.length > 1" class="relative">
                        <select
                            :value="currentBranch?.id"
                            @change="switchBranch(Number($event.target.value))"
                            class="appearance-none bg-gray-800 text-gray-300 text-sm rounded-lg border border-gray-700 pl-3 pr-8 py-1.5 focus:ring-1 focus:ring-emerald-400 focus:border-emerald-400 cursor-pointer hover:bg-gray-700 transition-colors"
                        >
                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                                {{ branch.name }}
                            </option>
                        </select>
                        <ChevronsUpDown class="w-3.5 h-3.5 text-gray-500 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" />
                    </div>
                    <span v-else class="text-gray-400 text-sm">{{ currentBranch?.name }}</span>
                </div>
            </div>

            <!-- Right side: user + logout -->
            <div class="flex items-center gap-3">
                <!-- Branch switcher mobile -->
                <select
                    v-if="branches.length > 1"
                    :value="currentBranch?.id"
                    @change="switchBranch(Number($event.target.value))"
                    class="sm:hidden appearance-none bg-gray-800 text-gray-300 text-xs rounded-lg border border-gray-700 px-2 py-1.5 focus:ring-1 focus:ring-emerald-400 max-w-[120px]"
                >
                    <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                        {{ branch.name }}
                    </option>
                </select>

                <div class="flex items-center gap-2" v-if="user">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                         :class="getAvatarColor(user.name)">
                        {{ getInitials(user.name) }}
                    </div>
                    <span class="hidden sm:block text-sm text-gray-300">{{ user.name }}</span>
                </div>
                <Link href="/logout" method="post" as="button"
                      class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition-colors"
                      title="Cerrar sesion">
                    <LogOut class="w-4 h-4" />
                </Link>
            </div>
        </header>

        <!-- Page content -->
        <main class="p-3 sm:p-4">
            <slot />
        </main>

        <AppToast />
    </div>
</template>
