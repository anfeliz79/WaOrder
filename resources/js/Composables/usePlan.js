import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function usePlan() {
    const page = usePage()
    const plan = computed(() => page.props.plan)

    const isActive = computed(() => ['active', 'trialing'].includes(plan.value?.status))
    const isTrial = computed(() => plan.value?.is_trial === true)
    const planName = computed(() => plan.value?.name || 'Sin plan')
    const status = computed(() => plan.value?.status)

    const limits = computed(() => plan.value?.limits || {})
    const usage = computed(() => plan.value?.usage || {})
    const features = computed(() => plan.value?.features || {})

    const canCreate = (resource) => {
        // resource: 'branches', 'menu_items', 'drivers', 'users', 'orders_this_month'
        const limitMap = {
            branches: 'max_branches',
            menu_items: 'max_menu_items',
            drivers: 'max_drivers',
            users: 'max_users',
            orders_this_month: 'max_orders_per_month',
        }
        const limitKey = limitMap[resource]
        if (!limitKey) return true
        const max = limits.value[limitKey]
        if (!max) return true // null/0 = unlimited
        const current = usage.value[resource] || 0
        return current < max
    }

    const usagePercent = (resource) => {
        const limitMap = {
            branches: 'max_branches',
            menu_items: 'max_menu_items',
            drivers: 'max_drivers',
            users: 'max_users',
            orders_this_month: 'max_orders_per_month',
        }
        const limitKey = limitMap[resource]
        if (!limitKey) return 0
        const max = limits.value[limitKey]
        if (!max) return 0
        const current = usage.value[resource] || 0
        return Math.min(100, Math.round((current / max) * 100))
    }

    const isFeatureEnabled = (feature) => {
        return features.value[feature] === true
    }

    return {
        plan, isActive, isTrial, planName, status,
        limits, usage, features,
        canCreate, usagePercent, isFeatureEnabled,
    }
}
