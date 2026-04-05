<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Link, Head, router, usePage } from '@inertiajs/vue3'
import {
    LayoutDashboard, Store, LogOut, Menu, X,
    ChevronRight, Shield, CreditCard, Settings, Server, Receipt,
    Building2, ArrowUpDown
} from 'lucide-vue-next'

defineProps({
    title: { type: String, default: 'SuperAdmin' }
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const flash = computed(() => page.props.flash)
const alertCount            = computed(() => page.props.alert_count ?? 0)
const pendingTransfersCount = computed(() => page.props.pending_transfers_count ?? 0)
const sidebarOpen = ref(false)
const showFlash = ref(false)

// Navigation items
const navItems = [
    { name: 'Dashboard',       href: '/superadmin',                              icon: LayoutDashboard },
    { name: 'Restaurantes',    href: '/superadmin/tenants',                      icon: Store },
    { name: 'Planes',          href: '/superadmin/plans',                        icon: CreditCard },
    { name: 'Suscripciones',   href: '/superadmin/subscriptions',                icon: Receipt },
    { name: 'Transferencias',  href: '/superadmin/transfer-verifications',        icon: ArrowUpDown, badge: 'pendingTransfers' },
    { name: 'Cuentas Bancarias', href: '/superadmin/bank-accounts',              icon: Building2 },
    { name: 'Sistema',         href: '/superadmin/system',                       icon: Server },
    { name: 'Configuracion',   href: '/superadmin/settings',                     icon: Settings },
]

const isActive = (href) => {
    const url = page.url
    if (href === '/superadmin') return url === '/superadmin'
    return url.startsWith(href)
}

const logout = () => {
    router.post('/logout')
}

// Flash message handling
watch(flash, (val) => {
    if (val?.success || val?.error) {
        showFlash.value = true
        setTimeout(() => { showFlash.value = false }, 5000)
    }
}, { immediate: true })
</script>

<template>
    <Head :title="title + ' — WaOrder SuperAdmin'" />

    <div class="min-h-screen bg-gray-100">
        <!-- Mobile sidebar overlay -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 lg:hidden" @click="sidebarOpen = false">
            <div class="fixed inset-0 bg-black/50"></div>
        </div>

        <!-- Sidebar -->
        <aside :class="[
            'fixed inset-y-0 left-0 z-50 w-64 bg-[#00174D] text-white transform transition-transform duration-200 lg:translate-x-0',
            sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        ]">
            <!-- Logo -->
            <div class="flex items-center gap-3 px-6 py-5 border-b border-white/10">
                <img src="/images/logo-white.png" alt="WaOrder" class="h-7" />
                <div class="text-xs text-[#00D1FF] font-medium">Super Admin</div>
            </div>

            <!-- Navigation -->
            <nav class="mt-4 px-3 space-y-1">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                        isActive(item.href)
                            ? 'bg-[#0052FF] text-white'
                            : 'text-blue-200/70 hover:bg-[#002370] hover:text-white'
                    ]"
                    @click="sidebarOpen = false"
                >
                    <component :is="item.icon" class="w-5 h-5 shrink-0" />
                    {{ item.name }}
                    <span
                        v-if="item.href === '/superadmin' && alertCount > 0"
                        class="ml-auto bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center leading-none"
                    >{{ alertCount }}</span>
                    <span
                        v-if="item.badge === 'pendingTransfers' && pendingTransfersCount > 0"
                        class="ml-auto bg-[#00D1FF] text-[#00174D] text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center leading-none"
                    >{{ pendingTransfersCount }}</span>
                </Link>
            </nav>

            <!-- User section -->
            <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-white/10">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 bg-[#0052FF] rounded-full flex items-center justify-center text-sm font-bold">
                        {{ user?.name?.charAt(0) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium truncate">{{ user?.name }}</div>
                        <div class="text-xs text-[#00D1FF]">Super Admin</div>
                    </div>
                </div>
                <button
                    @click="logout"
                    class="flex items-center gap-2 w-full px-3 py-2 text-sm text-blue-300/60 hover:text-white hover:bg-[#002370] rounded-lg transition-colors"
                >
                    <LogOut class="w-4 h-4" />
                    Cerrar Sesion
                </button>
            </div>
        </aside>

        <!-- Main content -->
        <div class="lg:ml-64">
            <!-- Top bar (mobile) -->
            <div class="sticky top-0 z-30 bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-3 lg:hidden">
                <button @click="sidebarOpen = !sidebarOpen" class="p-1.5 rounded-lg hover:bg-gray-100">
                    <Menu v-if="!sidebarOpen" class="w-5 h-5" />
                    <X v-else class="w-5 h-5" />
                </button>
                <span class="font-semibold text-gray-800">{{ title }}</span>
            </div>

            <!-- Flash messages -->
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="transform -translate-y-2 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="transform translate-y-0 opacity-100"
                leave-to-class="transform -translate-y-2 opacity-0"
            >
                <div v-if="showFlash && flash?.success" class="mx-4 mt-4 lg:mx-8">
                    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
                        {{ flash.success }}
                    </div>
                </div>
                <div v-else-if="showFlash && flash?.error" class="mx-4 mt-4 lg:mx-8">
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
                        {{ flash.error }}
                    </div>
                </div>
            </Transition>

            <!-- Page content -->
            <main class="p-4 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
