<script setup>
defineProps({
    variant: { type: String, default: 'gray' },
    size: { type: String, default: 'sm' },
    dot: Boolean,
});

const variants = {
    blue: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
    amber: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20',
    emerald: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
    violet: 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-600/20',
    gray: 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20',
    red: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
    primary: 'bg-primary-50 text-primary-700 ring-1 ring-inset ring-primary-600/20',
};

const dotColors = {
    blue: 'bg-blue-500',
    amber: 'bg-amber-500',
    emerald: 'bg-emerald-500',
    violet: 'bg-violet-500',
    gray: 'bg-gray-500',
    red: 'bg-red-500',
    primary: 'bg-primary-500',
};

const sizes = {
    xs: 'text-[10px] px-1.5 py-0.5',
    sm: 'text-xs px-2 py-0.5',
    md: 'text-sm px-2.5 py-1',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1 font-medium rounded-full whitespace-nowrap"
        :class="[variants[variant], sizes[size]]"
    >
        <span v-if="dot" class="w-1.5 h-1.5 rounded-full" :class="dotColors[variant]" />
        <slot />
    </span>
</template>
