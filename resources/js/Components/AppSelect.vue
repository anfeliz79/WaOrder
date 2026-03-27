<script setup>
defineProps({
    modelValue: [String, Number],
    label: String,
    error: String,
    options: Array, // [{ value, label }]
    placeholder: String,
    required: Boolean,
    disabled: Boolean,
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div>
        <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
            {{ label }} <span v-if="required" class="text-red-400">*</span>
        </label>
        <select
            :value="modelValue"
            @change="$emit('update:modelValue', $event.target.value)"
            :required="required"
            :disabled="disabled"
            v-bind="$attrs"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 text-gray-900 transition-colors duration-150 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 disabled:bg-gray-50 disabled:text-gray-500"
            :class="{ 'border-red-300 focus:ring-red-500/20 focus:border-red-500': error }"
        >
            <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
            <slot>
                <option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </slot>
        </select>
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>

<script>
export default { inheritAttrs: false };
</script>
