<script setup>
defineProps({
    title: String,
    subtitle: String,
    noPadding: Boolean,
    hoverable: { type: Boolean, default: false },
});
</script>

<template>
    <div class="bg-surface rounded-xl border border-gray-200 shadow-sm" :class="hoverable ? 'card-hover cursor-pointer' : ''">
        <div v-if="title || $slots.header" class="px-6 py-4 border-b border-gray-100">
            <slot name="header">
                <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
                <p v-if="subtitle" class="text-sm text-gray-500 mt-0.5">{{ subtitle }}</p>
            </slot>
        </div>
        <div :class="noPadding ? '' : 'p-6'">
            <slot />
        </div>
        <div v-if="$slots.footer" class="px-6 py-4 border-t border-gray-100 bg-gray-50/50 rounded-b-xl">
            <slot name="footer" />
        </div>
    </div>
</template>
