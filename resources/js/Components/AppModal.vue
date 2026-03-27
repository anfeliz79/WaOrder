<script setup>
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    TransitionRoot,
    TransitionChild,
} from '@headlessui/vue';
import { X } from 'lucide-vue-next';

defineProps({
    show: Boolean,
    title: String,
    maxWidth: { type: String, default: 'md' },
});

const emit = defineEmits(['close']);

const widths = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-xl',
};
</script>

<template>
    <TransitionRoot :show="show" as="template">
        <Dialog @close="emit('close')" class="relative z-50">
            <TransitionChild
                enter="ease-out duration-200" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-150" leave-from="opacity-100" leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" />
            </TransitionChild>

            <div class="fixed inset-0 flex items-center justify-center p-4">
                <TransitionChild
                    enter="ease-out duration-200" enter-from="opacity-0 scale-95" enter-to="opacity-100 scale-100"
                    leave="ease-in duration-150" leave-from="opacity-100 scale-100" leave-to="opacity-0 scale-95"
                >
                    <DialogPanel class="w-full bg-white rounded-xl shadow-xl flex flex-col max-h-[90vh]" :class="widths[maxWidth]">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                            <DialogTitle class="text-lg font-semibold text-gray-900">{{ title }}</DialogTitle>
                            <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <X class="w-5 h-5" />
                            </button>
                        </div>
                        <div class="px-6 py-4 overflow-y-auto">
                            <slot />
                        </div>
                        <div v-if="$slots.footer" class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                            <slot name="footer" />
                        </div>
                    </DialogPanel>
                </TransitionChild>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
