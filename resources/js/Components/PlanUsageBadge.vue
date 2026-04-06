<script setup>
import { computed } from 'vue';

const props = defineProps({
    used: { type: Number, required: true },
    limit: { type: Number, default: null },
    unlimited: { type: Boolean, default: false },
    label: { type: String, default: '' },
});

const percentage = computed(() => {
    if (props.unlimited || !props.limit) return 0;
    return Math.min(100, Math.round((props.used / props.limit) * 100));
});

const variant = computed(() => {
    if (props.unlimited || !props.limit) return 'gray';
    if (percentage.value >= 90) return 'red';
    if (percentage.value >= 70) return 'amber';
    return 'emerald';
});

const atLimit = computed(() => {
    if (props.unlimited || !props.limit) return false;
    return props.used >= props.limit;
});

const variantClasses = {
    emerald: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
    amber: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20',
    red: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
    gray: 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20',
};

const dotClasses = {
    emerald: 'bg-emerald-500',
    amber: 'bg-amber-500',
    red: 'bg-red-500',
    gray: 'bg-gray-400',
};

defineExpose({ atLimit });
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 text-xs font-medium rounded-full px-2.5 py-1 whitespace-nowrap"
        :class="variantClasses[variant]"
        :title="label && !unlimited && limit ? `${label}: ${used} de ${limit} usados (${percentage}%)` : undefined"
    >
        <span class="w-1.5 h-1.5 rounded-full" :class="dotClasses[variant]" />
        <span v-if="unlimited || !limit">{{ used }}</span>
        <span v-else>{{ used }}/{{ limit }}</span>
    </span>
</template>
