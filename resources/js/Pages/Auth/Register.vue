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
})

const selectedPlanData = computed(() => {
    return props.plans.find(p => p.slug === form.plan_slug)
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

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-indigo-50 flex items-center justify-center py-12 px-4">
        <div class="w-full max-w-xl">
            <!-- Logo -->
            <div class="text-center mb-8">
                <Link href="/" class="inline-flex items-center gap-2.5">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-xl flex items-center justify-center">
                        <span class="text-white font-bold text-lg">W</span>
                    </div>
                    <span class="font-bold text-2xl text-gray-900">WaOrder</span>
                </Link>
            </div>

            <!-- Progress -->
            <div class="flex items-center justify-center gap-2 mb-8">
                <div v-for="s in totalSteps" :key="s"
                    :class="[
                        'h-2 rounded-full transition-all duration-300',
                        s === step ? 'w-10 bg-indigo-600' : s < step ? 'w-6 bg-indigo-400' : 'w-6 bg-gray-200'
                    ]"></div>
            </div>

            <form @submit.prevent="submit">
                <!-- Step 1: Account Info -->
                <div v-show="step === 1" class="bg-white rounded-2xl shadow-xl shadow-gray-200/50 border border-gray-100 p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center">
                            <Store class="w-5 h-5 text-indigo-600" />
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
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                placeholder="Ej: Pizza Express" />
                            <p v-if="form.errors.restaurant_name" class="mt-1 text-sm text-red-600">{{ form.errors.restaurant_name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tu Nombre</label>
                            <input v-model="form.name" type="text" required
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                placeholder="Nombre completo" />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input v-model="form.email" type="email" required
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                placeholder="tu@email.com" />
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contrasena</label>
                            <input v-model="form.password" type="password" required minlength="8"
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                placeholder="Minimo 8 caracteres" />
                            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar Contrasena</label>
                            <input v-model="form.password_confirmation" type="password" required
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                placeholder="Repite la contrasena" />
                        </div>
                    </div>

                    <div class="mt-8 flex items-center justify-between">
                        <Link href="/login" class="text-sm text-gray-500 hover:text-gray-700">Ya tengo cuenta</Link>
                        <button type="button" @click="nextStep" :disabled="!canProceedStep1"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all text-sm">
                            Siguiente
                            <ArrowRight class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Step 2: Plan Selection -->
                <div v-show="step === 2" class="bg-white rounded-2xl shadow-xl shadow-gray-200/50 border border-gray-100 p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center">
                            <Sparkles class="w-5 h-5 text-indigo-600" />
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Elige tu Plan</h2>
                            <p class="text-sm text-gray-500">Puedes cambiar de plan en cualquier momento.</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <label v-for="plan in plans" :key="plan.slug"
                            :class="[
                                'block border rounded-xl p-5 cursor-pointer transition-all',
                                form.plan_slug === plan.slug
                                    ? 'border-indigo-500 bg-indigo-50/50 ring-2 ring-indigo-500'
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
                                    <span class="text-xl font-bold text-gray-900">{{ formatPrice(plan.price_monthly, plan.currency) }}</span>
                                    <span v-if="parseFloat(plan.price_monthly) > 0" class="text-xs text-gray-500">/mes</span>
                                </div>
                            </div>
                            <div class="mt-2 flex items-center gap-1.5" v-if="form.plan_slug === plan.slug">
                                <Check class="w-4 h-4 text-indigo-600" />
                                <span class="text-xs text-indigo-600 font-medium">Seleccionado</span>
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
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-all text-sm">
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
                                <span class="font-semibold text-indigo-600">{{ selectedPlanData?.name }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Precio</span>
                                <span class="font-medium text-gray-900">{{ formatPrice(selectedPlanData?.price_monthly, selectedPlanData?.currency) }}/mes</span>
                            </div>
                        </div>

                        <!-- Free plan notice -->
                        <div v-if="selectedPlanData && parseFloat(selectedPlanData.price_monthly) === 0" class="bg-green-50 border border-green-100 rounded-xl p-4">
                            <p class="text-sm text-green-800 font-medium">
                                Plan gratuito — sin cobros
                            </p>
                            <p class="text-sm text-green-600 mt-1">
                                Puedes actualizar a un plan superior en cualquier momento.
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
                            class="inline-flex items-center gap-2 px-8 py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-all text-sm shadow-lg shadow-indigo-200">
                            {{ form.processing ? 'Creando cuenta...' : 'Crear Cuenta' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
