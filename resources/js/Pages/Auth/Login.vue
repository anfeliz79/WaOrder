<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppButton from '@/Components/AppButton.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 via-white to-primary-50/30 px-4">
        <!-- Background pattern -->
        <div class="fixed inset-0 opacity-[0.015]" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23000&quot; fill-opacity=&quot;1&quot;%3E%3Cpath d=&quot;M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')" />

        <div class="max-w-md w-full relative">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-14 h-14 bg-gradient-to-br from-primary-500 to-violet-500 rounded-xl shadow-lg shadow-primary-600/30 mb-4">
                    <span class="text-2xl font-bold text-white">W</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Bienvenido a WaOrder</h1>
                <p class="text-gray-500 mt-1">Gestion de pedidos via WhatsApp</p>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="bg-white rounded-2xl shadow-xl shadow-gray-200/60 border border-gray-100 p-8">
                <div class="space-y-4">
                    <AppInput
                        v-model="form.email"
                        type="email"
                        label="Email"
                        :error="form.errors.email"
                        required
                        autofocus
                        placeholder="tu@email.com"
                    />

                    <AppInput
                        v-model="form.password"
                        type="password"
                        label="Contrasena"
                        :error="form.errors.password"
                        required
                        placeholder="Tu contrasena"
                    />

                    <div class="flex items-center">
                        <input v-model="form.remember" type="checkbox" id="remember"
                               class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        <label for="remember" class="ml-2 text-sm text-gray-600">Recordarme</label>
                    </div>
                </div>

                <AppButton type="submit" :loading="form.processing" class="w-full mt-6" size="lg">
                    Ingresar
                </AppButton>
            </form>
        </div>
    </div>
</template>
