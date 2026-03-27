<script setup>
defineProps({
    modelValue: [String, Number],
    label: String,
    error: String,
    hint: String,
    type: { type: String, default: 'text' },
    required: Boolean,
    disabled: Boolean,
    readonly: Boolean,
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div>
        <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
            {{ label }} <span v-if="required" class="text-red-400">*</span>
        </label>
        <input
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
            :type="type"
            :required="required"
            :disabled="disabled"
            :readonly="readonly"
            v-bind="$attrs"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 text-gray-900 placeholder:text-gray-400 transition-colors duration-150 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 disabled:bg-gray-50 disabled:text-gray-500 read-only:bg-gray-50 read-only:text-gray-500"
            :class="{ 'border-red-300 focus:ring-red-500/20 focus:border-red-500': error }"
        />
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
        <p v-else-if="hint" class="mt-1 text-xs text-gray-400">{{ hint }}</p>
    </div>
</template>

<script>
export default { inheritAttrs: false };
</script>
