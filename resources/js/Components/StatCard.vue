<script setup>
defineProps({
    title: String,
    value: [String, Number],
    icon: Object,
    color: { type: String, default: 'primary' },
    pulse: Boolean,
    trend: String,
    trendUp: Boolean,
});

const bgColors = {
    primary: 'bg-gradient-to-br from-primary-50 to-primary-100 text-primary-600',
    blue: 'bg-gradient-to-br from-blue-50 to-blue-100 text-blue-600',
    emerald: 'bg-gradient-to-br from-emerald-50 to-emerald-100 text-emerald-600',
    violet: 'bg-gradient-to-br from-violet-50 to-violet-100 text-violet-600',
    amber: 'bg-gradient-to-br from-amber-50 to-amber-100 text-amber-600',
};

const borderColors = {
    primary: 'border-l-primary-500',
    blue: 'border-l-blue-500',
    emerald: 'border-l-emerald-500',
    violet: 'border-l-violet-500',
    amber: 'border-l-amber-500',
};
</script>

<template>
    <div class="bg-surface rounded-xl border border-gray-200 border-l-4 shadow-sm p-5 hover:shadow-md transition-shadow duration-200"
         :class="borderColors[color]">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">{{ title }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ value }}</p>
                <div v-if="trend" class="flex items-center gap-1 mt-1">
                    <svg v-if="trendUp" class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                    </svg>
                    <svg v-else class="w-3.5 h-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                    <span class="text-xs font-semibold" :class="trendUp ? 'text-emerald-600' : 'text-red-600'">{{ trend }}</span>
                </div>
            </div>
            <div class="relative">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="bgColors[color]">
                    <component :is="icon" class="w-5 h-5" />
                </div>
                <span v-if="pulse" class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-emerald-500 rounded-full ring-2 ring-white animate-pulse" />
            </div>
        </div>
    </div>
</template>
