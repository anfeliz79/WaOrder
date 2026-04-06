<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Circle,
    Store,
    MessageCircle,
    Building2,
    BookOpen,
    CreditCard,
    Clock,
    ChevronRight,
    Rocket,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    checklist: Object,
});

const emit = defineEmits(['dismissed']);

const items = computed(() => props.checklist?.items ?? []);
const progress = computed(() => props.checklist?.progress ?? { completed: 0, total: 0, percentage: 0 });

const allComplete = computed(() => progress.value.completed === progress.value.total);

const iconMap = {
    restaurant_info: Store,
    whatsapp: MessageCircle,
    branch: Building2,
    menu: BookOpen,
    payment: CreditCard,
    business_hours: Clock,
};

const dismiss = () => {
    router.post('/dashboard/dismiss-onboarding', {}, {
        preserveScroll: true,
        onSuccess: () => emit('dismissed'),
    });
};
</script>

<template>
    <div class="bg-surface rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <!-- Gradient top border -->
        <div class="h-1 bg-gradient-to-r from-[#0052FF] to-[#00D1FF]"></div>

        <!-- Header -->
        <div class="px-6 pt-5 pb-4">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-50 to-cyan-50 flex items-center justify-center">
                        <Rocket class="w-5 h-5 text-[#0052FF]" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Primeros pasos</h3>
                        <p class="text-sm text-gray-500">
                            {{ progress.completed }} de {{ progress.total }} completados
                        </p>
                    </div>
                </div>
                <button
                    @click="dismiss"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                    title="Ocultar guia"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Progress bar -->
            <div class="mt-4 h-2 bg-gray-100 rounded-full overflow-hidden">
                <div
                    class="h-full bg-gradient-to-r from-[#0052FF] to-[#00D1FF] rounded-full transition-all duration-500 ease-out"
                    :style="{ width: progress.percentage + '%' }"
                ></div>
            </div>
        </div>

        <!-- Checklist items -->
        <div class="px-6 pb-2">
            <div class="divide-y divide-gray-100">
                <div
                    v-for="item in items"
                    :key="item.key"
                    class="py-3 flex items-center gap-3 group"
                >
                    <!-- Status icon -->
                    <div class="shrink-0">
                        <CheckCircle2
                            v-if="item.completed"
                            class="w-5 h-5 text-emerald-500"
                        />
                        <Circle
                            v-else
                            class="w-5 h-5 text-gray-300"
                        />
                    </div>

                    <!-- Item icon -->
                    <div
                        class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                        :class="item.completed
                            ? 'bg-emerald-50 text-emerald-500'
                            : 'bg-gray-50 text-gray-400 group-hover:bg-blue-50 group-hover:text-[#0052FF]'"
                    >
                        <component :is="iconMap[item.key]" class="w-4 h-4" />
                    </div>

                    <!-- Text -->
                    <div class="flex-1 min-w-0">
                        <p
                            class="text-sm"
                            :class="item.completed
                                ? 'text-gray-400 line-through'
                                : 'text-gray-900 font-medium'"
                        >
                            {{ item.title }}
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ item.description }}</p>
                    </div>

                    <!-- Action link -->
                    <Link
                        v-if="!item.completed"
                        :href="item.link"
                        class="shrink-0 flex items-center gap-1 text-sm font-medium text-[#0052FF] hover:text-blue-700 transition-colors opacity-0 group-hover:opacity-100"
                    >
                        Configurar
                        <ChevronRight class="w-3.5 h-3.5" />
                    </Link>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-3 bg-gray-50/50 border-t border-gray-100">
            <div class="flex items-center justify-between">
                <p v-if="allComplete" class="text-sm text-emerald-600 font-medium">
                    Todo listo — tu restaurante esta configurado
                </p>
                <p v-else class="text-xs text-gray-400">
                    Completa estos pasos para activar tu bot de WhatsApp
                </p>
                <button
                    @click="dismiss"
                    class="text-xs text-gray-400 hover:text-gray-600 font-medium transition-colors"
                >
                    Ocultar guia
                </button>
            </div>
        </div>
    </div>
</template>
