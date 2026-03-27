<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Dialog,
    DialogPanel,
    TransitionRoot,
    TransitionChild,
    Menu,
    MenuButton,
    MenuItems,
    MenuItem,
} from '@headlessui/vue';
import {
    LayoutDashboard,
    ClipboardList,
    BookOpen,
    Users,
    UsersRound,
    Truck,
    Star,
    Settings,
    Building2,
    Server,
    Menu as MenuIcon,
    X,
    LogOut,
    ChevronDown,
    ChevronsUpDown,
} from 'lucide-vue-next';
import AppToast from '@/Components/AppToast.vue';
import { getInitials, getAvatarColor } from '@/Utils/formatters';
import { useOrderNotification } from '@/Composables/useOrderNotification';

const page = usePage();
useOrderNotification();
const user = computed(() => page.props.auth?.user);
const branches = computed(() => page.props.auth?.branches || []);
const currentBranch = computed(() => page.props.auth?.current_branch);
const permissions = computed(() => page.props.auth?.permissions || []);
const sidebarOpen = ref(false);
const branchDropdownOpen = ref(false);

const hasPermission = (perm) => {
    return permissions.value.includes('*') || permissions.value.includes(perm);
};

const isAdmin = computed(() => user.value?.role === 'admin');

const allNavigation = [
    { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, permission: 'dashboard.view' },
    { name: 'Ordenes', href: '/orders', icon: ClipboardList, permission: 'orders.view' },
    { name: 'Menu', href: '/menu', icon: BookOpen, permission: 'menu.manage' },
    { name: 'Clientes', href: '/customers', icon: Users, permission: 'customers.view' },
    { name: 'Mensajeros', href: '/drivers', icon: Truck, permission: 'drivers.manage' },
    { name: 'Sucursales', href: '/branches', icon: Building2, permission: 'branches.manage' },
    { name: 'Usuarios', href: '/users', icon: UsersRound, permission: 'users.manage' },
    { name: 'Resenas', href: '/reviews', icon: Star, permission: 'reviews.view' },
    { name: 'Configuracion', href: '/settings', icon: Settings, permission: 'settings.manage' },
    { name: 'Sistema', href: '/system', icon: Server, permission: 'settings.manage' },
];

const navigation = computed(() => allNavigation.filter(item => hasPermission(item.permission)));

const switchBranch = (branchId) => {
    branchDropdownOpen.value = false;
    router.post('/switch-branch', { branch_id: branchId }, { preserveState: false });
};

const isActive = (href) => page.url.startsWith(href);

const pageTitle = computed(() => {
    const match = navigation.value.find(n => isActive(n.href));
    return match?.name || '';
});
</script>

<template>
    <div class="min-h-screen bg-background">
        <!-- Mobile sidebar -->
        <TransitionRoot :show="sidebarOpen" as="template">
            <Dialog @close="sidebarOpen = false" class="relative z-50 lg:hidden">
                <TransitionChild
                    enter="ease-out duration-200" enter-from="opacity-0" enter-to="opacity-100"
                    leave="ease-in duration-150" leave-from="opacity-100" leave-to="opacity-0"
                >
                    <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" />
                </TransitionChild>
                <TransitionChild
                    enter="ease-out duration-200" enter-from="-translate-x-full" enter-to="translate-x-0"
                    leave="ease-in duration-150" leave-from="translate-x-0" leave-to="-translate-x-full"
                >
                    <DialogPanel class="fixed inset-y-0 left-0 w-64 bg-sidebar flex flex-col">
                        <!-- Mobile sidebar header -->
                        <div class="flex items-center justify-between h-16 px-5">
                            <Link href="/dashboard" class="text-lg font-bold text-white flex items-center gap-2">
                                <span class="w-8 h-8 bg-gradient-to-br from-primary-500 to-violet-500 rounded-xl flex items-center justify-center text-sm font-bold text-white">W</span>
                                WaOrder
                            </Link>
                            <button @click="sidebarOpen = false" class="text-slate-400 hover:text-white transition-colors">
                                <X class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- Mobile branch switcher -->
                        <div v-if="branches.length > 1" class="px-3 mt-3">
                            <select
                                :value="currentBranch?.id"
                                @change="switchBranch(Number($event.target.value))"
                                class="w-full bg-white/5 text-indigo-200 text-sm rounded-lg border-0 px-3 py-2 focus:ring-1 focus:ring-primary-400"
                            >
                                <option v-for="branch in branches" :key="branch.id" :value="branch.id" class="bg-slate-800">
                                    {{ branch.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Mobile nav -->
                        <nav class="flex-1 px-3 mt-3 space-y-0.5">
                            <Link v-for="item in navigation" :key="item.name" :href="item.href"
                                  @click="sidebarOpen = false"
                                  class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors duration-150"
                                  :class="isActive(item.href)
                                      ? 'border-l-3 border-primary-400 bg-sidebar-active text-white'
                                      : 'text-indigo-200/70 hover:text-white hover:bg-sidebar-hover'">
                                <component :is="item.icon" class="w-5 h-5" :class="isActive(item.href) ? 'text-primary-300' : 'text-indigo-300/50'" />
                                {{ item.name }}
                            </Link>
                        </nav>
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </TransitionRoot>

        <!-- Desktop sidebar -->
        <div class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-50 lg:flex lg:w-64 lg:flex-col">
            <div class="flex flex-col flex-1 bg-sidebar">
                <!-- Logo -->
                <div class="flex items-center h-16 px-5">
                    <Link href="/dashboard" class="text-lg font-bold text-white flex items-center gap-2.5">
                        <span class="w-8 h-8 bg-gradient-to-br from-primary-500 to-violet-500 rounded-xl flex items-center justify-center text-sm font-bold text-white shadow-lg shadow-primary-600/30">W</span>
                        WaOrder
                    </Link>
                </div>

                <!-- Branch switcher -->
                <div v-if="branches.length > 0" class="px-3 mt-3">
                    <div class="relative">
                        <button
                            @click="branchDropdownOpen = !branchDropdownOpen"
                            class="w-full flex items-center gap-2 px-3 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-sm text-indigo-200 transition-colors"
                        >
                            <Building2 class="w-4 h-4 text-indigo-300/60 shrink-0" />
                            <span class="truncate flex-1 text-left">{{ currentBranch?.name || 'Sin sucursal' }}</span>
                            <ChevronsUpDown v-if="branches.length > 1" class="w-3.5 h-3.5 text-indigo-300/40 shrink-0" />
                        </button>
                        <div v-if="branchDropdownOpen && branches.length > 1"
                             class="absolute left-0 right-0 mt-1 bg-slate-800 rounded-lg shadow-xl border border-slate-700 py-1 z-50 max-h-48 overflow-y-auto">
                            <button
                                v-for="branch in branches"
                                :key="branch.id"
                                @click="switchBranch(branch.id)"
                                class="w-full text-left px-3 py-2 text-sm transition-colors"
                                :class="branch.id === currentBranch?.id
                                    ? 'text-primary-300 bg-white/5'
                                    : 'text-indigo-200/70 hover:text-white hover:bg-white/5'"
                            >
                                {{ branch.name }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 px-3 mt-3 space-y-0.5">
                    <Link v-for="item in navigation" :key="item.name" :href="item.href"
                          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors duration-150"
                          :class="isActive(item.href)
                              ? 'border-l-3 border-primary-400 bg-sidebar-active text-white'
                              : 'text-indigo-200/70 hover:text-white hover:bg-sidebar-hover'">
                        <component :is="item.icon" class="w-5 h-5" :class="isActive(item.href) ? 'text-primary-300' : 'text-indigo-300/50'" />
                        {{ item.name }}
                    </Link>
                </nav>

                <!-- User section at bottom -->
                <div class="p-3 border-t border-slate-700/50" v-if="user">
                    <div class="flex items-center gap-3 px-3 py-2.5">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                             :class="getAvatarColor(user.name)">
                            {{ getInitials(user.name) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-200 truncate">{{ user.name }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ user.email }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="lg:pl-64">
            <!-- Top bar -->
            <div class="sticky top-0 z-40">
            <div class="h-16 flex items-center justify-between px-4 sm:px-6 bg-white/80 backdrop-blur-md border-b border-gray-200/80">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" class="lg:hidden p-2 -ml-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                        <MenuIcon class="w-5 h-5" />
                    </button>
                    <h1 class="text-lg font-semibold text-gray-900">{{ pageTitle }}</h1>
                </div>

                <div class="flex items-center gap-3">
                    <!-- User menu -->
                    <Menu as="div" class="relative" v-if="user">
                        <MenuButton class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ring-2 ring-primary-500/30"
                                 :class="getAvatarColor(user.name)">
                                {{ getInitials(user.name) }}
                            </div>
                            <span class="hidden sm:block text-sm font-medium text-gray-700">{{ user.name }}</span>
                            <ChevronDown class="w-4 h-4 text-gray-400 hidden sm:block" />
                        </MenuButton>
                        <transition
                            enter-active-class="transition duration-100 ease-out"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition duration-75 ease-in"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95"
                        >
                            <MenuItems class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg ring-1 ring-black/5 py-1 focus:outline-none">
                                <MenuItem v-if="isAdmin" v-slot="{ active }">
                                    <Link href="/settings"
                                          class="flex items-center gap-2 px-4 py-2 text-sm transition-colors"
                                          :class="active ? 'bg-gray-50 text-gray-900' : 'text-gray-700'">
                                        <Settings class="w-4 h-4" />
                                        Configuracion
                                    </Link>
                                </MenuItem>
                                <MenuItem v-slot="{ active }">
                                    <Link href="/logout" method="post" as="button"
                                          class="flex items-center gap-2 w-full px-4 py-2 text-sm transition-colors"
                                          :class="active ? 'bg-gray-50 text-gray-900' : 'text-gray-700'">
                                        <LogOut class="w-4 h-4" />
                                        Cerrar sesion
                                    </Link>
                                </MenuItem>
                            </MenuItems>
                        </transition>
                    </Menu>
                </div>
            </div>
            <div class="h-px bg-gradient-to-r from-primary-500/20 via-primary-500/10 to-transparent"></div>
            </div>

            <!-- Page content with transition -->
            <main class="p-4 sm:p-6">
                <Transition name="page" mode="out-in">
                    <slot />
                </Transition>
            </main>
        </div>

        <!-- Toast notifications -->
        <AppToast />
    </div>
</template>
