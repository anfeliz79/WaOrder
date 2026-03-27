<script setup>
import { InboxIcon } from 'lucide-vue-next';
import AppButton from './AppButton.vue';

defineProps({
    title: { type: String, default: 'No hay datos' },
    description: String,
    icon: Object,
    actionLabel: String,
    actionHref: String,
});

defineEmits(['action']);
</script>

<template>
    <div class="flex flex-col items-center justify-center py-12 px-6 text-center">
        <div class="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center mb-4">
            <component :is="icon || InboxIcon" class="w-6 h-6 text-gray-400" />
        </div>
        <h3 class="text-sm font-semibold text-gray-900">{{ title }}</h3>
        <p v-if="description" class="mt-1 text-sm text-gray-500 max-w-sm">{{ description }}</p>
        <div v-if="actionLabel" class="mt-4">
            <AppButton
                v-if="actionHref"
                :href="actionHref"
                size="sm"
            >
                {{ actionLabel }}
            </AppButton>
            <AppButton
                v-else
                size="sm"
                @click="$emit('action')"
            >
                {{ actionLabel }}
            </AppButton>
        </div>
    </div>
</template>
