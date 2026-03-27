<script setup>
import { useToast } from '@/Composables/useToast';
import { CheckCircle, XCircle, AlertTriangle, Info, X } from 'lucide-vue-next';
import { TransitionGroup } from 'vue';

const { toasts, removeToast } = useToast();

const icons = {
    success: CheckCircle,
    error: XCircle,
    warning: AlertTriangle,
    info: Info,
};

const styles = {
    success: 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
    error: 'bg-red-50 text-red-800 ring-red-600/20',
    warning: 'bg-amber-50 text-amber-800 ring-amber-600/20',
    info: 'bg-blue-50 text-blue-800 ring-blue-600/20',
};

const iconStyles = {
    success: 'text-emerald-500',
    error: 'text-red-500',
    warning: 'text-amber-500',
    info: 'text-blue-500',
};
</script>

<template>
    <div class="fixed top-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none">
        <TransitionGroup
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 translate-x-8"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 translate-x-8"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg ring-1 min-w-[280px] max-w-sm"
                :class="styles[toast.type]"
            >
                <component :is="icons[toast.type]" class="w-5 h-5 shrink-0" :class="iconStyles[toast.type]" />
                <p class="text-sm font-medium flex-1">{{ toast.message }}</p>
                <button @click="removeToast(toast.id)" class="shrink-0 opacity-60 hover:opacity-100 transition-opacity">
                    <X class="w-4 h-4" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
