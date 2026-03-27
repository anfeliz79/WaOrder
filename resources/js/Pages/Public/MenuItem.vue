<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    token: { type: String, required: true },
});

const loading = ref(true);
const submitting = ref(false);
const error = ref(null);
const success = ref(false);
const tenant = ref(null);
const item = ref(null);
const quantity = ref(1);
const selectedVariants = ref({});
const selectedOptionals = ref({});

const apiBase = '/api/public/menu';

onMounted(async () => {
    try {
        const res = await fetch(`${apiBase}/${props.token}`);
        const data = await res.json();

        if (!res.ok) {
            error.value = data.error || 'Error cargando el producto.';
            return;
        }

        tenant.value = data.tenant;
        item.value = data.item;

        // Pre-select first variant option for each required group
        (item.value.modifiers?.variant_groups || []).forEach(group => {
            if (group.options?.length) {
                selectedVariants.value[group.name] = group.options[0];
            }
        });
    } catch (e) {
        error.value = 'No se pudo conectar al servidor.';
    } finally {
        loading.value = false;
    }
});

const variantGroups = computed(() => item.value?.modifiers?.variant_groups || []);
const optionalGroups = computed(() => item.value?.modifiers?.optional_groups || []);

const basePrice = computed(() => {
    const variants = Object.values(selectedVariants.value);
    if (variants.length > 0) {
        return variants.reduce((sum, v) => sum + (v.price || 0), 0);
    }
    return item.value?.price || 0;
});

const optionalsTotal = computed(() => {
    let total = 0;
    Object.values(selectedOptionals.value).forEach(groupSelections => {
        groupSelections.forEach(opt => {
            total += opt.price || 0;
        });
    });
    return total;
});

const unitPrice = computed(() => basePrice.value + optionalsTotal.value);
const totalPrice = computed(() => unitPrice.value * quantity.value);

function formatPrice(price) {
    return 'RD$' + new Intl.NumberFormat('es-DO', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(price);
}

function selectVariant(groupName, option) {
    selectedVariants.value[groupName] = option;
}

function toggleOptional(groupName, option, max) {
    if (!selectedOptionals.value[groupName]) {
        selectedOptionals.value[groupName] = [];
    }

    const current = selectedOptionals.value[groupName];
    const idx = current.findIndex(o => o.name === option.name);

    if (idx >= 0) {
        current.splice(idx, 1);
    } else {
        if (current.length >= max) return;
        current.push(option);
    }
}

function isOptionalSelected(groupName, optionName) {
    return (selectedOptionals.value[groupName] || []).some(o => o.name === optionName);
}

function decreaseQty() {
    if (quantity.value > 1) quantity.value--;
}

function increaseQty() {
    if (quantity.value < 20) quantity.value++;
}

async function addToCart() {
    submitting.value = true;
    error.value = null;

    const variants = Object.entries(selectedVariants.value).map(([groupName, opt]) => ({
        group_name: groupName,
        option_name: opt.name,
        price: opt.price,
    }));

    const optionals = [];
    Object.entries(selectedOptionals.value).forEach(([groupName, opts]) => {
        opts.forEach(opt => {
            optionals.push({
                name: opt.name,
                price: opt.price,
                group: groupName,
            });
        });
    });

    try {
        const res = await fetch(`${apiBase}/${props.token}/add`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                item_id: item.value.id,
                quantity: quantity.value,
                variants,
                optionals,
            }),
        });

        const data = await res.json();

        if (!res.ok) {
            error.value = data.error || 'Error al agregar al carrito.';
            return;
        }

        success.value = true;
    } catch (e) {
        error.value = 'No se pudo conectar al servidor.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Loading state -->
        <div v-if="loading" class="flex items-center justify-center min-h-screen">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600"></div>
        </div>

        <!-- Error state -->
        <div v-else-if="error && !item" class="flex items-center justify-center min-h-screen p-6">
            <div class="text-center">
                <div class="text-5xl mb-4">😕</div>
                <p class="text-gray-600 text-lg">{{ error }}</p>
                <p class="text-gray-400 text-sm mt-2">Vuelve a tu conversacion de WhatsApp para continuar.</p>
            </div>
        </div>

        <!-- Success state -->
        <div v-else-if="success" class="flex items-center justify-center min-h-screen p-6">
            <div class="text-center max-w-sm">
                <div class="text-6xl mb-4">✅</div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">Agregado al carrito</h2>
                <p class="text-gray-600 mb-6">Tu producto ha sido agregado. Revisa tu WhatsApp para continuar con el pedido.</p>
                <a href="https://wa.me/" class="inline-flex items-center gap-2 bg-green-500 text-white font-semibold px-6 py-3 rounded-xl hover:bg-green-600 transition-colors">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Volver a WhatsApp
                </a>
            </div>
        </div>

        <!-- Item detail -->
        <div v-else-if="item" class="pb-32">
            <!-- Header -->
            <div class="bg-white border-b border-gray-100 px-4 py-3 sticky top-0 z-10">
                <p class="text-sm font-medium text-primary-600 text-center">{{ tenant?.name }}</p>
            </div>

            <!-- Item image -->
            <div v-if="item.image_url" class="w-full aspect-[16/10] bg-gray-200 overflow-hidden">
                <img :src="item.image_url" :alt="item.name" class="w-full h-full object-cover" loading="eager">
            </div>

            <!-- Item info -->
            <div class="px-4 pt-4 pb-2">
                <h1 class="text-2xl font-bold text-gray-900">{{ item.name }}</h1>
                <p v-if="item.description" class="text-gray-500 mt-1">{{ item.description }}</p>
                <p class="text-lg font-semibold text-primary-600 mt-2">{{ formatPrice(basePrice) }}</p>
            </div>

            <!-- Variant groups -->
            <div v-for="group in variantGroups" :key="group.id || group.name" class="px-4 py-4 border-t border-gray-100">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900">{{ group.name }}</h3>
                    <span class="text-xs font-medium bg-red-100 text-red-600 px-2 py-0.5 rounded-full">Requerido</span>
                </div>
                <div class="space-y-2">
                    <label
                        v-for="option in group.options"
                        :key="option.id || option.name"
                        class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all"
                        :class="selectedVariants[group.name]?.name === option.name
                            ? 'border-primary-500 bg-primary-50'
                            : 'border-gray-200 hover:border-gray-300'"
                        @click="selectVariant(group.name, option)"
                    >
                        <div
                            class="w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0"
                            :class="selectedVariants[group.name]?.name === option.name
                                ? 'border-primary-500'
                                : 'border-gray-300'"
                        >
                            <div
                                v-if="selectedVariants[group.name]?.name === option.name"
                                class="w-2.5 h-2.5 rounded-full bg-primary-500"
                            ></div>
                        </div>
                        <span class="flex-1 text-gray-800">{{ option.name }}</span>
                        <span class="text-sm font-medium text-gray-600">{{ formatPrice(option.price) }}</span>
                    </label>
                </div>
            </div>

            <!-- Optional groups -->
            <div v-for="group in optionalGroups" :key="group.id || group.name" class="px-4 py-4 border-t border-gray-100">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900">{{ group.name }}</h3>
                    <span class="text-xs text-gray-400">
                        {{ group.min > 0 ? `Min ${group.min}` : 'Opcional' }}
                        {{ group.max ? ` - Max ${group.max}` : '' }}
                    </span>
                </div>
                <div class="space-y-2">
                    <label
                        v-for="option in group.options"
                        :key="option.id || option.name"
                        class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all"
                        :class="isOptionalSelected(group.name, option.name)
                            ? 'border-primary-500 bg-primary-50'
                            : 'border-gray-200 hover:border-gray-300'"
                        @click="toggleOptional(group.name, option, group.max || group.options.length)"
                    >
                        <div
                            class="w-5 h-5 rounded-md border-2 flex items-center justify-center flex-shrink-0"
                            :class="isOptionalSelected(group.name, option.name)
                                ? 'border-primary-500 bg-primary-500'
                                : 'border-gray-300'"
                        >
                            <svg v-if="isOptionalSelected(group.name, option.name)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <span class="flex-1 text-gray-800">{{ option.name }}</span>
                        <span class="text-sm font-medium" :class="option.price > 0 ? 'text-gray-600' : 'text-green-600'">
                            {{ option.price > 0 ? '+' + formatPrice(option.price) : 'Gratis' }}
                        </span>
                    </label>
                </div>
            </div>

            <!-- Quantity -->
            <div class="px-4 py-4 border-t border-gray-100">
                <h3 class="font-semibold text-gray-900 mb-3">Cantidad</h3>
                <div class="flex items-center gap-4">
                    <button
                        @click="decreaseQty"
                        class="w-10 h-10 rounded-full border-2 border-gray-200 flex items-center justify-center text-gray-600 hover:border-gray-300 transition-colors"
                        :disabled="quantity <= 1"
                        :class="{ 'opacity-40': quantity <= 1 }"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                    </button>
                    <span class="text-xl font-bold text-gray-900 w-8 text-center">{{ quantity }}</span>
                    <button
                        @click="increaseQty"
                        class="w-10 h-10 rounded-full border-2 border-gray-200 flex items-center justify-center text-gray-600 hover:border-gray-300 transition-colors"
                        :disabled="quantity >= 20"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    </button>
                </div>
            </div>

            <!-- Error -->
            <div v-if="error" class="mx-4 mt-2 p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">
                {{ error }}
            </div>

            <!-- Fixed bottom bar -->
            <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-4 z-20">
                <button
                    @click="addToCart"
                    :disabled="submitting"
                    class="w-full py-4 rounded-xl font-bold text-white text-lg transition-all"
                    :class="submitting
                        ? 'bg-gray-400 cursor-not-allowed'
                        : 'bg-primary-600 hover:bg-primary-700 active:scale-[0.98]'"
                >
                    <span v-if="submitting" class="flex items-center justify-center gap-2">
                        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
                        Agregando...
                    </span>
                    <span v-else>
                        Agregar al pedido - {{ formatPrice(totalPrice) }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
