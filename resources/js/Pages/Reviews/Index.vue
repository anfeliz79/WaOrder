<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Star, MessageSquare, TrendingUp, ThumbsUp, ThumbsDown, Search, Filter } from 'lucide-vue-next';
import AppCard from '@/Components/AppCard.vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppPagination from '@/Components/AppPagination.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import StatCard from '@/Components/StatCard.vue';
import { formatCurrency, formatRelativeTime, formatDate } from '@/Utils/formatters';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    reviews: Object,
    stats: Object,
    distribution: Object,
    qualityDistribution: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const ratingFilter = ref(props.filters?.rating || '');
const qualityFilter = ref(props.filters?.food_quality || '');

const applyFilters = () => {
    const params = {};
    if (search.value) params.search = search.value;
    if (ratingFilter.value) params.rating = ratingFilter.value;
    if (qualityFilter.value) params.food_quality = qualityFilter.value;
    router.get('/reviews', params, { preserveState: true, preserveScroll: true });
};

const clearFilters = () => {
    search.value = '';
    ratingFilter.value = '';
    qualityFilter.value = '';
    router.get('/reviews', {}, { preserveState: true });
};

const satisfactionRate = computed(() => {
    if (!props.stats?.total_reviews) return 0;
    return Math.round((props.stats.positive_count / props.stats.total_reviews) * 100);
});

const maxDistribution = computed(() => {
    return Math.max(...Object.values(props.distribution || { 1: 0 }), 1);
});

const qualityLabels = {
    excellent: 'Excelente',
    good: 'Buena',
    regular: 'Regular',
    bad: 'Mala',
};

const qualityColors = {
    excellent: 'text-emerald-600 bg-emerald-50',
    good: 'text-blue-600 bg-blue-50',
    regular: 'text-amber-600 bg-amber-50',
    bad: 'text-red-600 bg-red-50',
};
</script>

<template>
    <div>
        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <StatCard
                title="Total resenas"
                :value="stats?.total_reviews || 0"
                :icon="Star"
                color="primary"
            />
            <StatCard
                title="Calificacion promedio"
                :value="stats?.avg_rating ? stats.avg_rating + '/5' : 'N/A'"
                :icon="TrendingUp"
                color="amber"
            />
            <StatCard
                title="Satisfaccion"
                :value="satisfactionRate + '%'"
                :icon="ThumbsUp"
                color="emerald"
            />
            <StatCard
                title="Negativas"
                :value="stats?.negative_count || 0"
                :icon="ThumbsDown"
                color="red"
            />
            <StatCard
                title="Con comentarios"
                :value="stats?.with_comments || 0"
                :icon="MessageSquare"
                color="blue"
            />
        </div>

        <!-- Distribution charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            <!-- Rating distribution -->
            <AppCard>
                <template #header>
                    <h3 class="font-semibold text-gray-900">Distribucion de calificaciones</h3>
                </template>
                <div class="space-y-3">
                    <div v-for="rating in [5, 4, 3, 2, 1]" :key="rating" class="flex items-center gap-3">
                        <div class="flex items-center gap-1 w-16 shrink-0">
                            <Star class="w-3.5 h-3.5 text-amber-400 fill-amber-400" />
                            <span class="text-sm font-medium text-gray-700">{{ rating }}</span>
                        </div>
                        <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500"
                                 :class="rating >= 4 ? 'bg-emerald-400' : rating === 3 ? 'bg-amber-400' : 'bg-red-400'"
                                 :style="{ width: `${((distribution?.[rating] || 0) / maxDistribution) * 100}%` }">
                            </div>
                        </div>
                        <span class="text-sm text-gray-500 w-8 text-right">{{ distribution?.[rating] || 0 }}</span>
                    </div>
                </div>
            </AppCard>

            <!-- Quality distribution -->
            <AppCard>
                <template #header>
                    <h3 class="font-semibold text-gray-900">Calidad de la comida</h3>
                </template>
                <div class="grid grid-cols-2 gap-3">
                    <div v-for="(label, key) in qualityLabels" :key="key"
                         class="rounded-xl p-4 text-center" :class="qualityColors[key]">
                        <p class="text-2xl font-bold">{{ qualityDistribution?.[key] || 0 }}</p>
                        <p class="text-sm font-medium mt-1">{{ label }}</p>
                    </div>
                </div>
            </AppCard>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <div class="relative flex-1 min-w-[200px] max-w-md">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input v-model="search" @keyup.enter="applyFilters" type="text"
                       placeholder="Buscar por comentario o cliente..."
                       class="w-full pl-9 pr-3 py-2 border rounded-lg text-sm" />
            </div>
            <select v-model="ratingFilter" @change="applyFilters"
                    class="border rounded-lg px-3 py-2 text-sm text-gray-700">
                <option value="">Todas las estrellas</option>
                <option value="5">5 estrellas</option>
                <option value="4">4 estrellas</option>
                <option value="3">3 estrellas</option>
                <option value="2">2 estrellas</option>
                <option value="1">1 estrella</option>
            </select>
            <select v-model="qualityFilter" @change="applyFilters"
                    class="border rounded-lg px-3 py-2 text-sm text-gray-700">
                <option value="">Toda calidad</option>
                <option value="excellent">Excelente</option>
                <option value="good">Buena</option>
                <option value="regular">Regular</option>
                <option value="bad">Mala</option>
            </select>
            <button v-if="search || ratingFilter || qualityFilter" @click="clearFilters"
                    class="text-sm text-gray-500 hover:text-gray-700">
                Limpiar filtros
            </button>
        </div>

        <!-- Reviews list -->
        <div class="space-y-3">
            <AppCard v-for="review in reviews?.data" :key="review.id" hoverable>
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="font-medium text-gray-900">
                            {{ review.customer?.name || 'Cliente' }}
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Orden #{{ review.order?.order_number }} - {{ formatDate(review.created_at) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-0.5">
                        <Star v-for="i in 5" :key="i" class="w-5 h-5"
                              :class="i <= review.rating ? 'text-amber-400 fill-amber-400' : 'text-gray-200'" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <AppBadge v-if="review.food_quality"
                              :variant="review.food_quality === 'excellent' ? 'emerald' : review.food_quality === 'good' ? 'blue' : review.food_quality === 'regular' ? 'amber' : 'red'"
                              size="xs">
                        Comida: {{ qualityLabels[review.food_quality] || review.food_quality }}
                    </AppBadge>
                    <AppBadge v-if="review.delivery_speed" variant="gray" size="xs">
                        Entrega: {{ review.delivery_speed === 'fast' ? 'Rapida' : review.delivery_speed === 'normal' ? 'Normal' : 'Lenta' }}
                    </AppBadge>
                    <span v-if="review.order?.total" class="text-xs text-gray-400">
                        {{ formatCurrency(review.order.total) }}
                    </span>
                </div>

                <p v-if="review.comment" class="text-sm text-gray-600 bg-gray-50 rounded-lg p-3 italic">
                    "{{ review.comment }}"
                </p>
            </AppCard>
        </div>

        <AppEmptyState
            v-if="!reviews?.data?.length"
            :icon="Star"
            title="Sin resenas"
            description="Las resenas de los clientes apareceran aqui cuando respondan las encuestas post-entrega"
        />

        <AppPagination :data="reviews" routePath="/reviews" />
    </div>
</template>
