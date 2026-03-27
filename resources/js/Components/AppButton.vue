<script setup>
import { Link } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';

const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    loading: Boolean,
    disabled: Boolean,
    href: String,
    method: String,
    as: String,
});

const variants = {
    primary: 'bg-gradient-to-r from-primary-600 to-primary-500 text-white hover:from-primary-700 hover:to-primary-600 hover:shadow-md hover:shadow-primary-500/20 focus-visible:ring-primary-500 shadow-sm',
    secondary: 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 focus-visible:ring-primary-500',
    danger: 'bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-500 shadow-sm',
    ghost: 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus-visible:ring-gray-500',
    success: 'bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:ring-emerald-500 shadow-sm',
};

const sizes = {
    xs: 'text-xs px-2 py-1 gap-1',
    sm: 'text-sm px-3 py-1.5 gap-1.5',
    md: 'text-sm px-4 py-2 gap-2',
    lg: 'text-base px-5 py-2.5 gap-2',
};
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        :href="href"
        :method="method"
        :as="as"
        :disabled="disabled || loading"
        class="inline-flex items-center justify-center font-medium rounded-lg transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 active:scale-[0.98] disabled:opacity-50 disabled:pointer-events-none"
        :class="[variants[variant], sizes[size]]"
    >
        <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
        <slot />
    </component>
</template>
