<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ArrowLeft, ArrowRight, Check, Store, Sparkles } from 'lucide-vue-next'

const props = defineProps({
    plans: Array,
    selectedPlan: String,
})

const step = ref(1)
const totalSteps = 3

const form = useForm({
    restaurant_name: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    plan_slug: props.selectedPlan || (props.plans[0]?.slug ?? 'free'),
    billing_period: 'monthly',
})

const selectedPlanData = computed(() => {
    return props.plans.find(p => p.slug === form.plan_slug)
})

const selectedPrice = computed(() => {
    const plan = selectedPlanData.value
    if (!plan) return 0
    if (form.billing_period === 'annual' && parseFloat(plan.price_annual) > 0) {
        return parseFloat(plan.price_annual)
    }
    return parseFloat(plan.price_monthly)
})

const hasAnnualPrice = computed(() => {
    const plan = selectedPlanData.value
    return plan && parseFloat(plan.price_annual) > 0
})

const annualSavings = computed(() => {
    const plan = selectedPlanData.value
    if (!plan || !parseFloat(plan.price_annual) || !parseFloat(plan.price_monthly)) return 0
    const monthlyTotal = parseFloat(plan.price_monthly) * 12
    const annualTotal = parseFloat(plan.price_annual)
    return Math.round(((monthlyTotal - annualTotal) / monthlyTotal) * 100)
})

const canProceedStep1 = computed(() => {
    return form.restaurant_name.trim() && form.name.trim() && form.email.trim() && form.password.length >= 8 && form.password === form.password_confirmation
})

const nextStep = () => {
    if (step.value < totalSteps) step.value++
}

const prevStep = () => {
    if (step.value > 1) step.value--
}

const submit = () => {
    form.post('/register')
}

const formatPrice = (price, currency) => {
    if (!price || parseFloat(price) === 0) return 'Gratis'
    const cur = currency || 'DOP'
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: cur, maximumFractionDigits: 0 }).format(price)
}

const formatLimit = (value, singular, plural) => {
    if (!value) return plural + ' ilimitados'
    return value + ' ' + (value === 1 ? singular : plural)
}
</script>

<template>
    <Head title="Crear Cuenta — WaOrder" />

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-blue-50 flex items-center justify-center py-12 px-4">
        <div class="w-full max-w-xl">
            <!-- Logo -->
            <div class="text-center mb-8">
                <Link href="/" class="inline-flex items-center gap-2.5">
                    <img src="/images/logo.png" alt="WaOrder" class="h-10" />
                </Link>
            </div>

            <!-- Progress -->
            <div class="flex items-center justify-center gap-2 mb-8">
                <div v-for="s in totalSteps" :key="s"
                    :class="[
                        'h-2 rounded-full transition-all duration-300',
                        s === step ? 'w-10 bg-[#0052FF]' : s < step ? 'w-6 bg-[#3385ff]' : 'w-6 bg-gray-200'
                    ]"></div>
            </div>

            <form @submit.prevent="submit">
                <!-- Step 1: Account Info -->
                <div v-show="step === 1" class="bg-white rounded-2xl shadow-xl shadow-gray-200/50 border border-gray-100 p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                            <Store class="w-5 h-5 text-[#0052FF]" />
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Tu Restaurante</h2>
                            <p class="text-sm text-gray-500">Informacion basica para crear tu cuenta.</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Restaurante</label>
                            <input v-model="form.restaurant_name" type="text" required autofocus
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052FF] focus:border-[#0052FF] transition-all"
                                placeholder="Ej: Pizza Express" />
                            <p v-if="form.errors.restaurant_name" class="mt-1 text-sm text-red-600">{{ form.errors.restaurant_name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tu Nombre</label>
                            <input v-model="form.name" type="text" required
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052FF] focus:border-[#0052FF] transition-all"
                                placeholder="Nombre completo" />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input v-model="form.email" type="email" required
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052FF] focus:border-[#0052FF] transition-all"
                                placeholder="tu@email.com" />
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contrasena</label>
                            <input v-model="form.password" type="password" required minlength="8"
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052FF] focus:border-[#0052FF] transition-all"
                                placeholder="Minimo 8 caracteres" />
                            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar Contrasena</label>
                            <input v-model="form.password_confirmation" type="password" required
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#0052FF] focus:border-[#0052FF] transition-all"
                                placeholder="Repite la contrasena" />
                        </div>
                    </div>

                    <div class="mt-8 flex items-center justify-between">
                        <Link href="/login" class="text-sm text-gray-500 hover:text-gray-700">Ya tengo cuenta</Link>
                        <button type="button" @click="nextStep" :disabled="!canProceedStep1"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#0052FF] text-white font-medium rounded-xl hover:bg-[#0047DB] disabled:opacity-40 disabled:cursor-not-allowed transition-all text-sm">
                            Siguiente
                            <ArrowRight class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Step 2: Plan Selection -->
                <div v-show="step === 2" class="bg-white rounded-2xl shadow-xl shadow-gray-200/50 border border-gray-100 p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                            <Sparkles class="w-5 h-5 text-[#0052FF]" />
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Elige tu Plan</h2>
                            <p class="text-sm text-gray-500">Puedes cambiar de plan en cualquier momento.</p>
                        </div>
                    </div>

                    <!-- Billing period toggle -->
                    <div class="flex items-center justify-center mb-6">
                        <div class="inline-flex items-center bg-gray-100 rounded-xl p-1">
                            <button type="button" @click="form.billing_period = 'monthly'"
                                :class="[
                                    'px-4 py-2 text-sm font-medium rounded-lg transition-all',
                                    form.billing_period === 'monthly'
                                        ? 'bg-white text-gray-900 shadow-sm'
                                        : 'text-gray-500 hover:text-gray-700'
                                ]">
                                Mensual
                            </button>
                            <button type="button" @click="form.billing_period = 'annual'"
                                :class="[
                                    'px-4 py-2 text-sm font-medium rounded-lg transition-all',
                                    form.billing_period === 'annual'
                                        ? 'bg-white text-gray-900 shadow-sm'
                                        : 'text-gray-500 hover:text-gray-700'
                                ]">
                                Anual
                                <span v-if="annualSavings > 0" class="ml-1 text-xs text-green-600 font-semibold">-{{ annualSavings }}%</span>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <label v-for="plan in plans" :key="plan.slug"
                            :class="[
                                'block border rounded-xl p-5 cursor-pointer transition-all',
                                form.plan_slug === plan.slug
                                    ? 'border-[#0052FF] bg-blue-50/50 ring-2 ring-[#0052FF]'
                                    : 'border-gray-200 hover:border-gray-300'
                            ]">
                            <input type="radio" v-model="form.plan_slug" :value="plan.slug" class="sr-only" />
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-gray-900">{{ plan.name }}</span>
                                    </div>
                                    <p class="text-sm text-gray-500 mt-1">{{ plan.description }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500">
                                        <span>{{ formatLimit(plan.max_branches, 'sucursal', 'sucursales') }}</span>
                                        <span class="text-gray-300">·</span>
                                        <span>{{ formatLimit(plan.max_menu_items, 'item', 'items') }}</span>
                                        <span class="text-gray-300">·</span>
                                        <span>{{ formatLimit(plan.max_drivers, 'driver', 'drivers') }}</span>
                                        <span class="text-gray-300">·</span>
                                        <span>{{ formatLimit(plan.max_orders_per_month, 'orden/mes', 'ordenes/mes') }}</span>
                                    </div>
                                </div>
                                <div class="text-right shrink-0 ml-4">
                                    <template v-if="form.billing_period === 'annual' && parseFloat(plan.price_annual) > 0">
                                        <span class="text-xl font-bold text-gray-900">{{ formatPrice(plan.price_annual, plan.currency) }}</span>
                                        <span class="text-xs text-gray-500">/anual</span>
                                        <div class="text-xs text-gray-400 line-through">{{ formatPrice(parseFloat(plan.price_monthly) * 12, plan.currency) }}</div>
                                    </template>
                                    <template v-else>
                                        <span class="text-xl font-bold text-gray-900">{{ formatPrice(plan.price_monthly, plan.currency) }}</span>
                                        <span v-if="parseFloat(plan.price_monthly) > 0" class="text-xs text-gray-500">/mes</span>
                                    </template>
                                </div>
                            </div>
                            <div class="mt-2 flex items-center gap-1.5" v-if="form.plan_slug === plan.slug">
                                <Check class="w-4 h-4 text-[#0052FF]" />
                                <span class="text-xs text-[#0052FF] font-medium">Seleccionado</span>
                            </div>
                        </label>
                    </div>

                    <div class="mt-8 flex items-center justify-between">
                        <button type="button" @click="prevStep"
                            class="inline-flex items-center gap-2 px-4 py-2.5 text-gray-600 hover:text-gray-900 font-medium text-sm transition-colors">
                            <ArrowLeft class="w-4 h-4" />
                            Atras
                        </button>
                        <button type="button" @click="nextStep"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#0052FF] text-white font-medium rounded-xl hover:bg-[#0047DB] transition-all text-sm">
                            Siguiente
                            <ArrowRight class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Step 3: Confirmation -->
                <div v-show="step === 3" class="bg-white rounded-2xl shadow-xl shadow-gray-200/50 border border-gray-100 p-8">
                    <div class="mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Confirmar Registro</h2>
                        <p class="text-sm text-gray-500 mt-1">Revisa tus datos antes de crear la cuenta.</p>
                    </div>

                    <div class="space-y-4">
                        <div class="bg-gray-50 rounded-xl p-5 space-y-3">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Restaurante</span>
                                <span class="font-medium text-gray-900">{{ form.restaurant_name }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Admin</span>
                                <span class="font-medium text-gray-900">{{ form.name }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Email</span>
                                <span class="font-medium text-gray-900">{{ form.email }}</span>
                            </div>
                            <hr class="border-gray-200">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Plan</span>
                                <span class="font-semibold text-[#0052FF]">{{ selectedPlanData?.name }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Modalidad</span>
                                <span class="font-medium text-gray-900">{{ form.billing_period === 'annual' ? 'Anual' : 'Mensual' }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Precio</span>
                                <span class="font-medium text-gray-900">
                                    {{ formatPrice(selectedPrice, selectedPlanData?.currency) }}{{ form.billing_period === 'annual' ? '/anual' : '/mes' }}
                                </span>
                            </div>
                        </div>

                        <!-- Free plan notice -->
                        <div v-if="selectedPlanData && selectedPrice === 0" class="bg-green-50 border border-green-100 rounded-xl p-4">
                            <p class="text-sm text-green-800 font-medium">
                                Plan gratuito — sin cobros
                            </p>
                            <p class="text-sm text-green-600 mt-1">
                                Puedes actualizar a un plan superior en cualquier momento.
                            </p>
                        </div>

                        <!-- Annual savings notice -->
                        <div v-else-if="form.billing_period === 'annual' && annualSavings > 0" class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                            <p class="text-sm text-blue-800 font-medium">
                                Ahorras {{ annualSavings }}% con el plan anual
                            </p>
                        </div>
                    </div>

                    <div class="mt-8 flex items-center justify-between">
                        <button type="button" @click="prevStep"
                            class="inline-flex items-center gap-2 px-4 py-2.5 text-gray-600 hover:text-gray-900 font-medium text-sm transition-colors">
                            <ArrowLeft class="w-4 h-4" />
                            Atras
                        </button>
                        <button type="submit" :disabled="form.processing"
                            class="inline-flex items-center gap-2 px-8 py-3 bg-[#0052FF] text-white font-semibold rounded-xl hover:bg-[#0047DB] disabled:opacity-50 transition-all text-sm shadow-lg shadow-blue-200">
                            {{ form.processing ? 'Creando cuenta...' : 'Crear Cuenta' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
