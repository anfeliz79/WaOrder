<script setup>
import { Switch } from '@headlessui/vue';

const props = defineProps({
    modelValue: Boolean,
    label: String,
    description: String,
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex items-center justify-between gap-3">
        <div v-if="label">
            <p class="text-sm font-medium text-gray-900">{{ label }}</p>
            <p v-if="description" class="text-xs text-gray-500">{{ description }}</p>
        </div>
        <Switch
            :modelValue="modelValue"
            @update:modelValue="emit('update:modelValue', $event)"
            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
            :class="modelValue ? 'bg-primary-600' : 'bg-gray-200'"
        >
            <span
                class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform duration-200"
                :class="modelValue ? 'translate-x-6' : 'translate-x-1'"
            />
        </Switch>
    </div>
</template>
