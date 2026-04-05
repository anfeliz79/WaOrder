<script setup>
import { ref, computed, onMounted, watch } from 'vue';

const props = defineProps({
    token: { type: String, required: true },
});

const loading = ref(true);
const error = ref(null);
const tenant = ref(null);
const customerPhone = ref('');
const categories = ref([]);
const theme = ref({ primary_color: '#0052FF', logo_url: null, show_restaurant_name: true });
const selectedCategory = ref(null);
const selectedItem = ref(null);
const submitting = ref(false);
const successMessage = ref(null);

// Item customization state
const quantity = ref(1);
const selectedVariants = ref({});
const selectedOptionals = ref({});

// Cart tracking
const cartCount = ref(0);
const cartTotal = ref(0);

const apiBase = '/api/public/menu';

onMounted(async () => {
    try {
        const res = await fetch(`${apiBase}/${props.token}/full`);
        const data = await res.json();

        if (!res.ok) {
            error.value = data.error || 'Error cargando el menu.';
            return;
        }

        tenant.value = data.tenant;
        customerPhone.value = data.customer_phone || '';
        categories.value = data.categories || [];
        if (data.theme) theme.value = data.theme;
    } catch (e) {
        error.value = 'No se pudo conectar al servidor.';
    } finally {
        loading.value = false;
    }
});

const availableItems = computed(() => {
    if (!selectedCategory.value) return [];
    return (selectedCategory.value.items || []).filter(i => i.is_available !== false);
});

const variantGroups = computed(() => selectedItem.value?.modifiers?.variant_groups || []);
const optionalGroups = computed(() => selectedItem.value?.modifiers?.optional_groups || []);

const basePrice = computed(() => {
    const variants = Object.values(selectedVariants.value);
    if (variants.length > 0) {
        return variants.reduce((sum, v) => sum + (v.price || 0), 0);
    }
    return selectedItem.value?.price || 0;
});

const optionalsTotal = computed(() => {
    let total = 0;
    Object.values(selectedOptionals.value).forEach(groupSelections => {
        groupSelections.forEach(opt => { total += opt.price || 0; });
    });
    return total;
});

const unitPrice = computed(() => basePrice.value + optionalsTotal.value);
const totalPrice = computed(() => unitPrice.value * quantity.value);

// --- Theme helpers ---
function hexToHsl(hex) {
    let r = parseInt(hex.slice(1, 3), 16) / 255;
    let g = parseInt(hex.slice(3, 5), 16) / 255;
    let b = parseInt(hex.slice(5, 7), 16) / 255;
    const max = Math.max(r, g, b), min = Math.min(r, g, b);
    let h, s, l = (max + min) / 2;
    if (max === min) { h = s = 0; }
    else {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
            case g: h = ((b - r) / d + 2) / 6; break;
            case b: h = ((r - g) / d + 4) / 6; break;
        }
    }
    return [Math.round(h * 360), Math.round(s * 100), Math.round(l * 100)];
}

const themeVars = computed(() => {
    const hex = theme.value.primary_color || '#0052FF';
    const [h, s] = hexToHsl(hex);
    return {
        '--color-primary-50':  `hsl(${h}, ${s}%, 97%)`,
        '--color-primary-100': `hsl(${h}, ${s}%, 93%)`,
        '--color-primary-200': `hsl(${h}, ${s}%, 86%)`,
        '--color-primary-300': `hsl(${h}, ${s}%, 75%)`,
        '--color-primary-400': `hsl(${h}, ${s}%, 63%)`,
        '--color-primary-500': `hsl(${h}, ${s}%, 53%)`,
        '--color-primary-600': hex,
        '--color-primary-700': `hsl(${h}, ${s}%, 36%)`,
        '--color-primary-800': `hsl(${h}, ${s}%, 28%)`,
        '--color-primary-900': `hsl(${h}, ${s}%, 20%)`,
    };
});
// --- End theme helpers ---

function formatPrice(price) {
    return 'RD$' + new Intl.NumberFormat('es-DO', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(price);
}

function itemPriceText(item) {
    const mods = item.modifiers || {};
    if (mods.variant_groups?.length) {
        const prices = [];
        mods.variant_groups.forEach(g => {
            (g.options || []).forEach(o => prices.push(o.price || 0));
        });
        if (prices.length) {
            const min = Math.min(...prices);
            const max = Math.max(...prices);
            return min === max ? formatPrice(min) : `Desde ${formatPrice(min)}`;
        }
    }
    return formatPrice(item.price);
}

function openCategory(cat) {
    selectedCategory.value = cat;
    selectedItem.value = null;
}

function openItem(item) {
    selectedItem.value = item;
    quantity.value = 1;
    selectedVariants.value = {};
    selectedOptionals.value = {};

    // Pre-select first variant option for each group
    (item.modifiers?.variant_groups || []).forEach(group => {
        if (group.options?.length) {
            selectedVariants.value[group.name] = group.options[0];
        }
    });
}

function goBack() {
    if (selectedItem.value) {
        selectedItem.value = null;
    } else if (selectedCategory.value) {
        selectedCategory.value = null;
    }
}

function returnToWhatsApp() {
    window.location.href = `https://wa.me/${tenant.value?.whatsapp_phone || ''}`;
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

function decreaseQty() { if (quantity.value > 1) quantity.value--; }
function increaseQty() { if (quantity.value < 20) quantity.value++; }

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
            optionals.push({ name: opt.name, price: opt.price, group: groupName });
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
                item_id: selectedItem.value.id,
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

        cartCount.value = data.cart?.items_count || cartCount.value + 1;
        cartTotal.value = data.cart?.total || 0;
        successMessage.value = `${selectedItem.value.name} agregado al carrito`;

        setTimeout(() => { successMessage.value = null; }, 3000);

        // Go back to item list
        selectedItem.value = null;
    } catch (e) {
        error.value = 'No se pudo conectar al servidor.';
    } finally {
        submitting.value = false;
    }
}

async function addSimpleItem(item) {
    submitting.value = true;
    error.value = null;

    try {
        const res = await fetch(`${apiBase}/${props.token}/add`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                item_id: item.id,
                quantity: 1,
                variants: [],
                optionals: [],
            }),
        });

        const data = await res.json();

        if (!res.ok) {
            error.value = data.error || 'Error al agregar al carrito.';
            return;
        }

        cartCount.value = data.cart?.items_count || cartCount.value + 1;
        cartTotal.value = data.cart?.total || 0;
        successMessage.value = `${item.name} agregado al carrito`;

        setTimeout(() => { successMessage.value = null; }, 3000);
    } catch (e) {
        error.value = 'No se pudo conectar al servidor.';
    } finally {
        submitting.value = false;
    }
}

function hasModifiers(item) {
    const m = item.modifiers || {};
    return (m.variant_groups?.length > 0) || (m.optional_groups?.length > 0);
}
</script>

<template>
    <div class="min-h-screen bg-gray-50" :style="themeVars">
        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center min-h-screen">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600"></div>
        </div>

        <!-- Error -->
        <div v-else-if="error && !categories.length" class="flex items-center justify-center min-h-screen p-6">
            <div class="text-center">
                <div class="text-5xl mb-4">😕</div>
                <p class="text-gray-600 text-lg">{{ error }}</p>
            </div>
        </div>

        <template v-else>
            <!-- Header -->
            <div class="bg-white border-b border-gray-100 px-4 py-3 sticky top-0 z-20">
                <div class="flex items-center gap-3">
                    <button
                        v-if="selectedCategory"
                        @click="goBack"
                        class="p-1 -ml-1 text-gray-500 hover:text-gray-700"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                    <div class="flex-1 flex flex-col items-center gap-0.5">
                        <img v-if="theme.logo_url" :src="theme.logo_url" alt="logo" class="h-7 object-contain" />
                        <p v-if="!theme.logo_url || theme.show_restaurant_name" class="text-sm font-semibold text-primary-600 leading-tight">{{ tenant?.name }}</p>
                        <p v-if="selectedCategory && !selectedItem" class="text-xs text-gray-400">{{ selectedCategory.name }}</p>
                    </div>
                    <div class="w-5"></div>
                </div>
            </div>

            <!-- Success toast -->
            <transition name="toast">
                <div v-if="successMessage" class="fixed top-16 left-4 right-4 z-30 bg-green-500 text-white text-sm font-medium px-4 py-3 rounded-xl shadow-lg text-center">
                    {{ successMessage }}
                </div>
            </transition>

            <!-- Error toast -->
            <div v-if="error && categories.length" class="mx-4 mt-3 p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">
                {{ error }}
            </div>

            <!-- ITEM DETAIL VIEW -->
            <div v-if="selectedItem" class="pb-32">
                <div v-if="selectedItem.image_url" class="w-full aspect-[16/10] bg-gray-200 overflow-hidden">
                    <img :src="selectedItem.image_url" :alt="selectedItem.name" class="w-full h-full object-cover" loading="eager">
                </div>

                <div class="px-4 pt-4 pb-2">
                    <h1 class="text-2xl font-bold text-gray-900">{{ selectedItem.name }}</h1>
                    <p v-if="selectedItem.description" class="text-gray-500 mt-1">{{ selectedItem.description }}</p>
                    <p class="text-lg font-semibold text-primary-600 mt-2">{{ formatPrice(basePrice) }}</p>
                </div>

                <!-- Variant groups -->
                <div v-for="group in variantGroups" :key="group.name" class="px-4 py-4 border-t border-gray-100">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-gray-900">{{ group.name }}</h3>
                        <span class="text-xs font-medium bg-red-100 text-red-600 px-2 py-0.5 rounded-full">Requerido</span>
                    </div>
                    <div class="space-y-2">
                        <label v-for="option in group.options" :key="option.name"
                            class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all"
                            :class="selectedVariants[group.name]?.name === option.name ? 'border-primary-500 bg-primary-50' : 'border-gray-200'"
                            @click="selectVariant(group.name, option)">
                            <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0"
                                :class="selectedVariants[group.name]?.name === option.name ? 'border-primary-500' : 'border-gray-300'">
                                <div v-if="selectedVariants[group.name]?.name === option.name" class="w-2.5 h-2.5 rounded-full bg-primary-500"></div>
                            </div>
                            <span class="flex-1 text-gray-800">{{ option.name }}</span>
                            <span class="text-sm font-medium text-gray-600">{{ formatPrice(option.price) }}</span>
                        </label>
                    </div>
                </div>

                <!-- Optional groups -->
                <div v-for="group in optionalGroups" :key="group.name" class="px-4 py-4 border-t border-gray-100">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-gray-900">{{ group.name }}</h3>
                        <span class="text-xs text-gray-400">{{ group.min > 0 ? `Min ${group.min}` : 'Opcional' }}{{ group.max ? ` - Max ${group.max}` : '' }}</span>
                    </div>
                    <div class="space-y-2">
                        <label v-for="option in group.options" :key="option.name"
                            class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all"
                            :class="isOptionalSelected(group.name, option.name) ? 'border-primary-500 bg-primary-50' : 'border-gray-200'"
                            @click="toggleOptional(group.name, option, group.max || group.options.length)">
                            <div class="w-5 h-5 rounded-md border-2 flex items-center justify-center flex-shrink-0"
                                :class="isOptionalSelected(group.name, option.name) ? 'border-primary-500 bg-primary-500' : 'border-gray-300'">
                                <svg v-if="isOptionalSelected(group.name, option.name)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            <span class="flex-1 text-gray-800">{{ option.name }}</span>
                            <span class="text-sm font-medium" :class="option.price > 0 ? 'text-gray-600' : 'text-green-600'">{{ option.price > 0 ? '+' + formatPrice(option.price) : 'Gratis' }}</span>
                        </label>
                    </div>
                </div>

                <!-- Quantity -->
                <div class="px-4 py-4 border-t border-gray-100">
                    <h3 class="font-semibold text-gray-900 mb-3">Cantidad</h3>
                    <div class="flex items-center gap-4">
                        <button @click="decreaseQty" class="w-10 h-10 rounded-full border-2 border-gray-200 flex items-center justify-center" :disabled="quantity <= 1" :class="{ 'opacity-40': quantity <= 1 }">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                        </button>
                        <span class="text-xl font-bold text-gray-900 w-8 text-center">{{ quantity }}</span>
                        <button @click="increaseQty" class="w-10 h-10 rounded-full border-2 border-gray-200 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Fixed bottom bar -->
                <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-4 z-20">
                    <button @click="addToCart" :disabled="submitting"
                        class="w-full py-4 rounded-xl font-bold text-white text-lg transition-all"
                        :class="submitting ? 'bg-gray-400' : 'bg-primary-600 hover:bg-primary-700 active:scale-[0.98]'">
                        <span v-if="submitting" class="flex items-center justify-center gap-2">
                            <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white"></div>
                            Agregando...
                        </span>
                        <span v-else>Agregar al pedido - {{ formatPrice(totalPrice) }}</span>
                    </button>
                </div>
            </div>

            <!-- ITEMS LIST VIEW -->
            <div v-else-if="selectedCategory" :class="cartCount > 0 ? 'pb-40' : 'pb-24'">
                <div v-if="!availableItems.length" class="text-center py-12 text-gray-400">No hay items disponibles.</div>

                <div class="divide-y divide-gray-100">
                    <div v-for="item in availableItems" :key="item.id"
                        class="flex gap-3 p-4 bg-white hover:bg-gray-50 cursor-pointer transition-colors"
                        @click="hasModifiers(item) ? openItem(item) : addSimpleItem(item)">
                        <div v-if="item.image_url" class="w-20 h-20 rounded-xl bg-gray-200 overflow-hidden flex-shrink-0">
                            <img :src="item.image_url" :alt="item.name" class="w-full h-full object-cover" loading="lazy">
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900 truncate">{{ item.name }}</h3>
                            <p v-if="item.description" class="text-xs text-gray-400 mt-0.5 line-clamp-2">{{ item.description }}</p>
                            <div class="flex items-center gap-2 mt-1.5">
                                <span class="text-sm font-semibold text-primary-600">{{ itemPriceText(item) }}</span>
                                <span v-if="hasModifiers(item)" class="text-xs text-gray-400">Personalizable</span>
                            </div>
                        </div>
                        <div class="flex items-center flex-shrink-0">
                            <div class="w-8 h-8 rounded-full bg-primary-50 flex items-center justify-center text-primary-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CATEGORIES VIEW -->
            <div v-else class="p-4 space-y-3" :class="cartCount > 0 ? 'pb-40' : 'pb-24'">
                <h2 class="text-lg font-bold text-gray-900 mb-1">Nuestro Menu</h2>

                <div v-for="cat in categories" :key="cat.id"
                    class="bg-white rounded-xl border border-gray-100 p-4 hover:shadow-md cursor-pointer transition-all active:scale-[0.98]"
                    @click="openCategory(cat)">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ cat.name }}</h3>
                            <p v-if="cat.description" class="text-xs text-gray-400 mt-0.5">{{ cat.description }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ (cat.items || []).filter(i => i.is_available !== false).length }} items</p>
                        </div>
                        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </div>
                </div>
            </div>

            <!-- Floating bottom bar -->
            <div v-if="!selectedItem" class="fixed bottom-0 left-0 right-0 z-20 p-4 space-y-2">
                <!-- Cart bar (only when items in cart) -->
                <button v-if="cartCount > 0"
                    @click="returnToWhatsApp"
                    class="flex items-center justify-between w-full py-3.5 px-5 rounded-xl font-bold text-white bg-green-500 hover:bg-green-600 transition-colors shadow-lg">
                    <span class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        Finalizar en WhatsApp
                    </span>
                    <span>{{ cartCount }} item{{ cartCount > 1 ? 's' : '' }} - {{ formatPrice(cartTotal) }}</span>
                </button>

                <!-- Back to WhatsApp (only when cart is empty) -->
                <button v-if="cartCount === 0" @click="returnToWhatsApp"
                    class="flex items-center justify-center gap-2 w-full py-3 px-5 rounded-xl font-medium text-green-700 bg-white border border-green-200 hover:bg-green-50 transition-colors shadow-sm text-sm">
                    <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Regresar a WhatsApp
                </button>
            </div>
        </template>
    </div>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.toast-enter-active { transition: all 0.3s ease; }
.toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from { opacity: 0; transform: translateY(-20px); }
.toast-leave-to { opacity: 0; transform: translateY(-20px); }
</style>
