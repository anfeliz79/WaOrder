<script setup>
import { router } from '@inertiajs/vue3';
import { MapPin, Phone } from 'lucide-vue-next';

const props = defineProps({
    branches: Array,
});

const selectBranch = (branchId) => {
    router.post('/select-branch', { branch_id: branchId });
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 via-white to-primary-50/30 px-4">
        <!-- Background pattern -->
        <div class="fixed inset-0 opacity-[0.015]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23000&quot; fill-opacity=&quot;1&quot;%3E%3Cpath d=&quot;M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')" />

        <div class="max-w-lg w-full relative">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-14 h-14 bg-gradient-to-br from-primary-500 to-violet-500 rounded-xl shadow-lg shadow-primary-600/30 mb-4">
                    <span class="text-2xl font-bold text-white">W</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Selecciona una sucursal</h1>
                <p class="text-gray-500 mt-1">Elige la sucursal con la que deseas trabajar</p>
            </div>

            <!-- Branch cards -->
            <div class="space-y-3">
                <button
                    v-for="branch in branches"
                    :key="branch.id"
                    @click="selectBranch(branch.id)"
                    class="w-full bg-white rounded-xl shadow-md shadow-gray-200/60 border border-gray-100 p-5 text-left hover:border-primary-300 hover:shadow-lg hover:shadow-primary-100/40 transition-all duration-200 group"
                >
                    <h3 class="text-lg font-semibold text-gray-900 group-hover:text-primary-600 transition-colors">
                        {{ branch.name }}
                    </h3>
                    <div class="mt-2 space-y-1">
                        <p v-if="branch.address" class="text-sm text-gray-500 flex items-center gap-1.5">
                            <MapPin class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                            {{ branch.address }}
                        </p>
                        <p v-if="branch.phone" class="text-sm text-gray-500 flex items-center gap-1.5">
                            <Phone class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                            {{ branch.phone }}
                        </p>
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>
