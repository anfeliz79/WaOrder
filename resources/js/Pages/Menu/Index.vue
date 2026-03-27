<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { BookOpen, Globe, Plus, X, Pencil, Layers, ListPlus, Search, ChevronDown, ChevronUp, Image } from 'lucide-vue-next';
import AppButton from '@/Components/AppButton.vue';
import AppInput from '@/Components/AppInput.vue';
import AppCard from '@/Components/AppCard.vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppSwitch from '@/Components/AppSwitch.vue';
import AppModal from '@/Components/AppModal.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import ModifiersEditor from '@/Components/ModifiersEditor.vue';
import { formatCurrency } from '@/Utils/formatters';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    categories: Array,
    menuSource: { type: String, default: 'internal' },
});

const isExternal = props.menuSource === 'external';

const showAddCategory = ref(false);
const showItemModal = ref(false);
const editingItem = ref(null);
const editingCategoryId = ref(null);

// Search / filter
const searchQuery = ref('');

// Collapse state: track collapsed category IDs
const collapsedCategories = ref(new Set());

function toggleCategory(categoryId) {
    const next = new Set(collapsedCategories.value);
    if (next.has(categoryId)) {
        next.delete(categoryId);
    } else {
        next.add(categoryId);
    }
    collapsedCategories.value = next;
}

function isCategoryCollapsed(categoryId) {
    return collapsedCategories.value.has(categoryId);
}

function expandAll() {
    collapsedCategories.value = new Set();
}

function collapseAll() {
    const all = new Set((props.categories || []).map(c => c.id));
    collapsedCategories.value = all;
}

// Accent-insensitive, case-insensitive normalize
function normalize(str) {
    return (str || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

// Filtered categories based on search
const filteredCategories = computed(() => {
    const cats = props.categories || [];
    const q = normalize(searchQuery.value).trim();
    if (!q) return cats;

    return cats
        .map(cat => {
            const matchingItems = (cat.items || []).filter(item =>
                normalize(item.name).includes(q)
            );
            if (!matchingItems.length) return null;
            return { ...cat, items: matchingItems };
        })
        .filter(Boolean);
});

const isSearching = computed(() => normalize(searchQuery.value).trim().length > 0);

const filteredItemCount = computed(() => {
    return filteredCategories.value.reduce((sum, cat) => sum + (cat.items?.length || 0), 0);
});

// Summary stats (always from full data, not filtered)
const totalCategories = computed(() => (props.categories || []).length);
const totalItems = computed(() =>
    (props.categories || []).reduce((sum, cat) => sum + (cat.items?.length || 0), 0)
);
const itemsWithVariants = computed(() =>
    (props.categories || []).reduce((sum, cat) =>
        sum + (cat.items || []).filter(i => itemHasVariants(i)).length, 0
    )
);
const itemsWithOptionals = computed(() =>
    (props.categories || []).reduce((sum, cat) =>
        sum + (cat.items || []).filter(i => itemHasOptionals(i)).length, 0
    )
);

const categoryForm = useForm({ name: '', description: '' });
const itemForm = useForm({
    category_id: null,
    name: '',
    description: '',
    price: '',
    modifiers: { variant_groups: [], optional_groups: [] },
});

const isEditing = computed(() => !!editingItem.value);
const modalTitle = computed(() => isEditing.value ? 'Editar Item' : 'Nuevo Item');

const hasVariants = computed(() => (itemForm.modifiers?.variant_groups?.length || 0) > 0);

function openAddItem(categoryId) {
    editingItem.value = null;
    editingCategoryId.value = categoryId;
    itemForm.reset();
    itemForm.category_id = categoryId;
    itemForm.modifiers = { variant_groups: [], optional_groups: [] };
    showItemModal.value = true;
}

function openEditItem(item, categoryId) {
    editingItem.value = item;
    editingCategoryId.value = categoryId;
    itemForm.category_id = categoryId;
    itemForm.name = item.name;
    itemForm.description = item.description || '';
    itemForm.price = item.price;
    itemForm.modifiers = item.modifiers && (item.modifiers.variant_groups || item.modifiers.optional_groups)
        ? JSON.parse(JSON.stringify(item.modifiers))
        : { variant_groups: [], optional_groups: [] };
    showItemModal.value = true;
}

function closeItemModal() {
    showItemModal.value = false;
    editingItem.value = null;
    itemForm.reset();
}

function submitItem() {
    if (isEditing.value) {
        itemForm.put(`/menu/items/${editingItem.value.id}`, {
            onSuccess: closeItemModal,
        });
    } else {
        itemForm.post('/menu/items', {
            onSuccess: closeItemModal,
        });
    }
}

const submitCategory = () => {
    categoryForm.post('/menu/categories', {
        onSuccess: () => {
            categoryForm.reset();
            showAddCategory.value = false;
        },
    });
};

const toggleAvailability = (itemId, available) => {
    router.patch(`/menu/items/${itemId}/availability`, {
        is_available: !available,
    }, { preserveScroll: true });
};

function itemHasVariants(item) {
    return item.modifiers?.variant_groups?.length > 0;
}

function itemHasOptionals(item) {
    return item.modifiers?.optional_groups?.length > 0;
}

function itemPriceDisplay(item) {
    if (!itemHasVariants(item)) return formatCurrency(item.price);
    const prices = [];
    for (const group of item.modifiers.variant_groups) {
        for (const opt of group.options || []) {
            prices.push(Number(opt.price) || 0);
        }
    }
    if (!prices.length) return formatCurrency(item.price);
    const min = Math.min(...prices);
    const max = Math.max(...prices);
    return min === max ? formatCurrency(min) : `Desde ${formatCurrency(min)}`;
}

function clearSearch() {
    searchQuery.value = '';
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 sr-only">Menu</h1>
                <AppBadge v-if="isExternal" variant="gray">
                    <Globe class="w-3 h-3" />
                    API Externa (solo lectura)
                </AppBadge>
            </div>
            <AppButton v-if="!isExternal" @click="showAddCategory = true" size="sm">
                <Plus class="w-4 h-4" />
                Categoria
            </AppButton>
        </div>

        <!-- Summary bar -->
        <div v-if="totalCategories > 0" class="flex flex-wrap items-center gap-3 mb-4 text-sm text-gray-600">
            <span class="inline-flex items-center gap-1.5 bg-gray-50 px-3 py-1.5 rounded-lg ring-1 ring-gray-200">
                <BookOpen class="w-3.5 h-3.5 text-gray-400" />
                {{ totalCategories }} {{ totalCategories === 1 ? 'categoria' : 'categorias' }}
            </span>
            <span class="inline-flex items-center gap-1.5 bg-gray-50 px-3 py-1.5 rounded-lg ring-1 ring-gray-200">
                {{ totalItems }} {{ totalItems === 1 ? 'item' : 'items' }}
            </span>
            <span v-if="itemsWithVariants > 0" class="inline-flex items-center gap-1.5 bg-blue-50 px-3 py-1.5 rounded-lg ring-1 ring-blue-200 text-blue-700">
                <Layers class="w-3.5 h-3.5" />
                {{ itemsWithVariants }} con variantes
            </span>
            <span v-if="itemsWithOptionals > 0" class="inline-flex items-center gap-1.5 bg-violet-50 px-3 py-1.5 rounded-lg ring-1 ring-violet-200 text-violet-700">
                <ListPlus class="w-3.5 h-3.5" />
                {{ itemsWithOptionals }} con opcionales
            </span>
        </div>

        <!-- Search & collapse controls -->
        <div v-if="totalCategories > 0" class="flex flex-col sm:flex-row gap-3 mb-5">
            <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Buscar items por nombre..."
                    class="w-full pl-9 pr-9 py-2 rounded-lg border border-gray-300 text-gray-900 placeholder:text-gray-400 transition-colors duration-150 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 text-sm"
                />
                <button v-if="searchQuery"
                        @click="clearSearch"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                    <X class="w-4 h-4" />
                </button>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <AppButton variant="ghost" size="sm" @click="expandAll">
                    <ChevronDown class="w-3.5 h-3.5" />
                    Expandir todo
                </AppButton>
                <AppButton variant="ghost" size="sm" @click="collapseAll">
                    <ChevronUp class="w-3.5 h-3.5" />
                    Colapsar todo
                </AppButton>
            </div>
        </div>

        <!-- Searching results count -->
        <div v-if="isSearching" class="mb-4 text-sm text-gray-500">
            {{ filteredItemCount }} {{ filteredItemCount === 1 ? 'resultado' : 'resultados' }}
            <span v-if="filteredItemCount === 0"> &mdash; intenta con otro termino</span>
        </div>

        <!-- Add Category Form -->
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <AppCard v-if="showAddCategory" class="mb-6">
                <h3 class="font-semibold text-gray-900 mb-4">Nueva Categoria</h3>
                <form @submit.prevent="submitCategory" class="flex flex-col sm:flex-row gap-3">
                    <AppInput v-model="categoryForm.name" placeholder="Nombre" required class="flex-1" />
                    <AppInput v-model="categoryForm.description" placeholder="Descripcion (opcional)" class="flex-1" />
                    <div class="flex gap-2">
                        <AppButton type="submit" :loading="categoryForm.processing">Guardar</AppButton>
                        <AppButton variant="ghost" @click="showAddCategory = false">Cancelar</AppButton>
                    </div>
                </form>
            </AppCard>
        </Transition>

        <!-- Categories -->
        <div class="space-y-4">
            <AppCard v-for="category in filteredCategories" :key="category.id" noPadding>
                <template #header>
                    <div class="flex items-center justify-between cursor-pointer select-none"
                         @click="toggleCategory(category.id)">
                        <div class="flex items-center gap-2">
                            <component
                                :is="isCategoryCollapsed(category.id) ? ChevronDown : ChevronUp"
                                class="w-4 h-4 text-gray-400 transition-transform duration-200"
                                :class="{ 'rotate-180': isCategoryCollapsed(category.id) }"
                            />
                            <h2 class="font-semibold text-gray-900">{{ category.name }}</h2>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-gray-400">{{ category.items?.length || 0 }} items</span>
                            <button v-if="!isExternal" @click.stop="openAddItem(category.id)"
                                    class="text-sm text-primary-600 hover:text-primary-700 font-medium flex items-center gap-1 transition-colors">
                                <Plus class="w-3.5 h-3.5" />
                                Item
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Items list (collapsible) -->
                <Transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 max-h-0"
                    enter-to-class="opacity-100 max-h-[5000px]"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 max-h-[5000px]"
                    leave-to-class="opacity-0 max-h-0"
                >
                    <div v-show="!isCategoryCollapsed(category.id)" class="divide-y divide-gray-100 overflow-hidden">
                        <div v-for="item in category.items" :key="item.id"
                             class="px-6 py-4 hover:bg-gray-50/50 transition-colors"
                             :class="{ 'cursor-pointer': !isExternal }"
                             @click="!isExternal && openEditItem(item, category.id)">

                            <!-- Item row: responsive layout -->
                            <div class="flex flex-col sm:flex-row sm:items-start gap-3">

                                <!-- Thumbnail for external items with image -->
                                <div v-if="isExternal && item.image_url"
                                     class="w-14 h-14 rounded-lg overflow-hidden bg-gray-100 shrink-0 ring-1 ring-gray-200">
                                    <img :src="item.image_url" :alt="item.name"
                                         class="w-full h-full object-cover"
                                         @error="$event.target.style.display='none'" />
                                </div>

                                <!-- Item info -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-medium text-gray-900 text-sm">{{ item.name }}</p>
                                        <AppBadge v-if="itemHasVariants(item)" variant="blue" size="xs">
                                            <Layers class="w-2.5 h-2.5" />
                                            Variantes
                                        </AppBadge>
                                        <AppBadge v-if="itemHasOptionals(item)" variant="violet" size="xs">
                                            <ListPlus class="w-2.5 h-2.5" />
                                            Opcionales
                                        </AppBadge>
                                    </div>
                                    <p v-if="item.description"
                                       class="text-xs text-gray-500 mt-1 line-clamp-2 leading-relaxed">
                                        {{ item.description }}
                                    </p>
                                </div>

                                <!-- Price + availability (stacks on mobile) -->
                                <div class="flex items-center gap-4 shrink-0 sm:ml-auto">
                                    <p class="font-semibold text-sm whitespace-nowrap"
                                       :class="itemHasVariants(item) ? 'text-blue-700' : 'text-gray-900'">
                                        {{ itemPriceDisplay(item) }}
                                    </p>
                                    <AppSwitch
                                        v-if="!isExternal"
                                        :modelValue="item.is_available"
                                        @update:modelValue="toggleAvailability(item.id, item.is_available)"
                                        @click.stop
                                    />
                                    <AppBadge v-else :variant="item.is_available ? 'emerald' : 'red'" size="xs">
                                        {{ item.is_available ? 'Disponible' : 'No disponible' }}
                                    </AppBadge>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>
            </AppCard>
        </div>

        <AppEmptyState
            v-if="!categories?.length"
            :icon="BookOpen"
            title="Menu vacio"
            description="Agrega categorias y productos para empezar a recibir pedidos"
            :actionLabel="!isExternal ? '+ Agregar categoria' : undefined"
            @action="showAddCategory = true"
        />

        <!-- No search results -->
        <AppEmptyState
            v-else-if="isSearching && filteredCategories.length === 0"
            :icon="Search"
            title="Sin resultados"
            :description="`No se encontraron items que coincidan con '${searchQuery}'`"
            actionLabel="Limpiar busqueda"
            @action="clearSearch"
        />

        <!-- Item Modal (Add/Edit) -->
        <AppModal :show="showItemModal" :title="modalTitle" max-width="lg" @close="closeItemModal">
            <form @submit.prevent="submitItem" class="space-y-4">
                <AppInput v-model="itemForm.name" label="Nombre" placeholder="Ej: Pizza Pepperoni" required />
                <AppInput v-model="itemForm.description" label="Descripcion" placeholder="Descripcion del item (opcional)" />
                <AppInput v-model="itemForm.price" label="Precio base" type="number" step="0.01" min="0"
                          placeholder="0.00" required
                          :hint="hasVariants ? 'El precio sera determinado por las variantes seleccionadas' : ''" />

                <div class="border-t border-gray-100 pt-4">
                    <ModifiersEditor v-model="itemForm.modifiers" />
                </div>
            </form>

            <template #footer>
                <AppButton variant="ghost" @click="closeItemModal">Cancelar</AppButton>
                <AppButton @click="submitItem" :loading="itemForm.processing">
                    {{ isEditing ? 'Guardar cambios' : 'Crear item' }}
                </AppButton>
            </template>
        </AppModal>
    </div>
</template>
