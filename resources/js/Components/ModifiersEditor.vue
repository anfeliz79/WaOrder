<script setup>
import { Plus, Trash2, GripVertical } from 'lucide-vue-next';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';

const props = defineProps({
    modelValue: { type: Object, default: () => ({ variant_groups: [], optional_groups: [] }) },
});

const emit = defineEmits(['update:modelValue']);

function update(changes) {
    emit('update:modelValue', { ...props.modelValue, ...changes });
}

function generateId() {
    return Math.random().toString(36).substring(2, 9);
}

// Variant Groups
function addVariantGroup() {
    const groups = [...(props.modelValue.variant_groups || [])];
    groups.push({
        id: generateId(),
        name: '',
        required: true,
        options: [
            { id: generateId(), name: '', price: '' },
            { id: generateId(), name: '', price: '' },
        ],
    });
    update({ variant_groups: groups });
}

function removeVariantGroup(index) {
    const groups = [...(props.modelValue.variant_groups || [])];
    groups.splice(index, 1);
    update({ variant_groups: groups });
}

function updateVariantGroup(index, field, value) {
    const groups = JSON.parse(JSON.stringify(props.modelValue.variant_groups || []));
    groups[index][field] = value;
    update({ variant_groups: groups });
}

function addVariantOption(groupIndex) {
    const groups = JSON.parse(JSON.stringify(props.modelValue.variant_groups || []));
    groups[groupIndex].options.push({ id: generateId(), name: '', price: '' });
    update({ variant_groups: groups });
}

function removeVariantOption(groupIndex, optIndex) {
    const groups = JSON.parse(JSON.stringify(props.modelValue.variant_groups || []));
    if (groups[groupIndex].options.length <= 2) return;
    groups[groupIndex].options.splice(optIndex, 1);
    update({ variant_groups: groups });
}

function updateVariantOption(groupIndex, optIndex, field, value) {
    const groups = JSON.parse(JSON.stringify(props.modelValue.variant_groups || []));
    groups[groupIndex].options[optIndex][field] = value;
    update({ variant_groups: groups });
}

// Optional Groups
function addOptionalGroup() {
    const groups = [...(props.modelValue.optional_groups || [])];
    groups.push({
        id: generateId(),
        name: '',
        min: 0,
        max: 5,
        options: [
            { id: generateId(), name: '', price: '' },
        ],
    });
    update({ optional_groups: groups });
}

function removeOptionalGroup(index) {
    const groups = [...(props.modelValue.optional_groups || [])];
    groups.splice(index, 1);
    update({ optional_groups: groups });
}

function updateOptionalGroup(index, field, value) {
    const groups = JSON.parse(JSON.stringify(props.modelValue.optional_groups || []));
    groups[index][field] = value;
    update({ optional_groups: groups });
}

function addOptionalOption(groupIndex) {
    const groups = JSON.parse(JSON.stringify(props.modelValue.optional_groups || []));
    groups[groupIndex].options.push({ id: generateId(), name: '', price: '' });
    update({ optional_groups: groups });
}

function removeOptionalOption(groupIndex, optIndex) {
    const groups = JSON.parse(JSON.stringify(props.modelValue.optional_groups || []));
    if (groups[groupIndex].options.length <= 1) return;
    groups[groupIndex].options.splice(optIndex, 1);
    update({ optional_groups: groups });
}

function updateOptionalOption(groupIndex, optIndex, field, value) {
    const groups = JSON.parse(JSON.stringify(props.modelValue.optional_groups || []));
    groups[groupIndex].options[optIndex][field] = value;
    update({ optional_groups: groups });
}
</script>

<template>
    <div class="space-y-6">
        <!-- Variant Groups -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-gray-700">Variantes</h4>
                <button type="button" @click="addVariantGroup"
                        class="text-xs text-primary-600 hover:text-primary-700 font-medium flex items-center gap-1">
                    <Plus class="w-3.5 h-3.5" />
                    Agregar grupo
                </button>
            </div>
            <p v-if="!modelValue.variant_groups?.length" class="text-xs text-gray-400 italic">
                Sin variantes. Ej: Tamano (Pequena, Mediana, Grande)
            </p>

            <div v-for="(group, gi) in modelValue.variant_groups" :key="group.id"
                 class="border border-gray-200 rounded-lg p-4 mb-3 bg-gray-50/50">
                <div class="flex items-center gap-3 mb-3">
                    <input v-model="group.name" @input="updateVariantGroup(gi, 'name', $event.target.value)"
                           placeholder="Nombre del grupo (ej: Tamano)"
                           class="flex-1 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500" />
                    <button type="button" @click="removeVariantGroup(gi)"
                            class="text-red-400 hover:text-red-600 transition-colors">
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-2 ml-2">
                    <div v-for="(opt, oi) in group.options" :key="opt.id"
                         class="flex items-center gap-2">
                        <GripVertical class="w-3.5 h-3.5 text-gray-300 shrink-0" />
                        <input :value="opt.name" @input="updateVariantOption(gi, oi, 'name', $event.target.value)"
                               placeholder="Opcion (ej: Grande)"
                               class="flex-1 px-2.5 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500" />
                        <input :value="opt.price" @input="updateVariantOption(gi, oi, 'price', $event.target.value)"
                               type="number" step="0.01" min="0" placeholder="Precio"
                               class="w-24 px-2.5 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500" />
                        <button type="button" @click="removeVariantOption(gi, oi)"
                                :disabled="group.options.length <= 2"
                                class="text-gray-400 hover:text-red-500 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                    <button type="button" @click="addVariantOption(gi)"
                            class="text-xs text-gray-500 hover:text-primary-600 font-medium flex items-center gap-1 mt-1">
                        <Plus class="w-3 h-3" />
                        Agregar opcion
                    </button>
                </div>
            </div>
        </div>

        <!-- Optional Groups -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-gray-700">Opcionales / Extras</h4>
                <button type="button" @click="addOptionalGroup"
                        class="text-xs text-primary-600 hover:text-primary-700 font-medium flex items-center gap-1">
                    <Plus class="w-3.5 h-3.5" />
                    Agregar grupo
                </button>
            </div>
            <p v-if="!modelValue.optional_groups?.length" class="text-xs text-gray-400 italic">
                Sin opcionales. Ej: Extras (Extra queso, Sin cebolla)
            </p>

            <div v-for="(group, gi) in modelValue.optional_groups" :key="group.id"
                 class="border border-gray-200 rounded-lg p-4 mb-3 bg-gray-50/50">
                <div class="flex items-center gap-3 mb-3">
                    <input v-model="group.name" @input="updateOptionalGroup(gi, 'name', $event.target.value)"
                           placeholder="Nombre del grupo (ej: Extras)"
                           class="flex-1 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500" />
                    <div class="flex items-center gap-2 shrink-0">
                        <label class="text-xs text-gray-500">Min</label>
                        <input :value="group.min" @input="updateOptionalGroup(gi, 'min', parseInt($event.target.value) || 0)"
                               type="number" min="0" class="w-14 px-2 py-1.5 border border-gray-300 rounded-lg text-sm text-center" />
                        <label class="text-xs text-gray-500">Max</label>
                        <input :value="group.max" @input="updateOptionalGroup(gi, 'max', parseInt($event.target.value) || 0)"
                               type="number" min="0" class="w-14 px-2 py-1.5 border border-gray-300 rounded-lg text-sm text-center" />
                    </div>
                    <button type="button" @click="removeOptionalGroup(gi)"
                            class="text-red-400 hover:text-red-600 transition-colors">
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-2 ml-2">
                    <div v-for="(opt, oi) in group.options" :key="opt.id"
                         class="flex items-center gap-2">
                        <GripVertical class="w-3.5 h-3.5 text-gray-300 shrink-0" />
                        <input :value="opt.name" @input="updateOptionalOption(gi, oi, 'name', $event.target.value)"
                               placeholder="Opcion (ej: Extra queso)"
                               class="flex-1 px-2.5 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500" />
                        <input :value="opt.price" @input="updateOptionalOption(gi, oi, 'price', $event.target.value)"
                               type="number" step="0.01" min="0" placeholder="Precio (0=gratis)"
                               class="w-24 px-2.5 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500" />
                        <button type="button" @click="removeOptionalOption(gi, oi)"
                                :disabled="group.options.length <= 1"
                                class="text-gray-400 hover:text-red-500 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                    <button type="button" @click="addOptionalOption(gi)"
                            class="text-xs text-gray-500 hover:text-primary-600 font-medium flex items-center gap-1 mt-1">
                        <Plus class="w-3 h-3" />
                        Agregar opcion
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
