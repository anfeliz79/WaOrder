<script setup>
import { router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    data: Object, // Laravel paginator { current_page, last_page, total, from, to, per_page }
    routePath: String,
    routeParams: { type: Object, default: () => ({}) },
});

const goToPage = (page) => {
    router.get(props.routePath, { ...props.routeParams, page }, { preserveState: true, replace: true });
};

const visiblePages = (current, last) => {
    const pages = [];
    const delta = 1;
    const left = Math.max(2, current - delta);
    const right = Math.min(last - 1, current + delta);

    pages.push(1);
    if (left > 2) pages.push('...');
    for (let i = left; i <= right; i++) pages.push(i);
    if (right < last - 1) pages.push('...');
    if (last > 1) pages.push(last);
    return pages;
};
</script>

<template>
    <div v-if="data?.last_page > 1" class="flex items-center justify-between pt-4">
        <p class="text-sm text-gray-500">
            {{ data.from }}-{{ data.to }} de {{ data.total }}
        </p>
        <div class="flex items-center gap-1">
            <button
                @click="goToPage(data.current_page - 1)"
                :disabled="data.current_page === 1"
                class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 disabled:opacity-30 disabled:pointer-events-none transition-colors"
            >
                <ChevronLeft class="w-4 h-4" />
            </button>
            <template v-for="page in visiblePages(data.current_page, data.last_page)" :key="page">
                <span v-if="page === '...'" class="px-2 text-sm text-gray-400">...</span>
                <button
                    v-else
                    @click="goToPage(page)"
                    class="min-w-[32px] h-8 px-2 rounded-lg text-sm font-medium transition-colors"
                    :class="data.current_page === page
                        ? 'bg-primary-600 text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-100'"
                >
                    {{ page }}
                </button>
            </template>
            <button
                @click="goToPage(data.current_page + 1)"
                :disabled="data.current_page === data.last_page"
                class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 disabled:opacity-30 disabled:pointer-events-none transition-colors"
            >
                <ChevronRight class="w-4 h-4" />
            </button>
        </div>
    </div>
</template>
