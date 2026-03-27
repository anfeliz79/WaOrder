<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    tenant: Object,
    user: Object,
    verifyToken: String,
    hasCategories: Boolean,
});

const currentStep = ref(1);
const totalSteps = 4;
const saving = ref(false);
const testResult = ref(null);

const steps = [
    { number: 1, title: 'Restaurante', description: 'Datos basicos' },
    { number: 2, title: 'WhatsApp', description: 'Conectar API' },
    { number: 3, title: 'Menu', description: 'Productos' },
    { number: 4, title: 'Admin', description: 'Tu cuenta' },
];

// Step 1: Restaurant
const restaurantForm = useForm({
    name: props.tenant?.name === 'Pizzeria Don Mario' ? '' : (props.tenant?.name || ''),
    timezone: props.tenant?.timezone || 'America/Santo_Domingo',
    currency: props.tenant?.currency || 'DOP',
    delivery_fee: props.tenant?.settings?.delivery_fee ?? 150,
    min_order: props.tenant?.settings?.min_order ?? 0,
    estimated_time: props.tenant?.settings?.estimated_time ?? 30,
});

// Step 2: WhatsApp
const whatsappForm = useForm({
    whatsapp_phone_number_id: props.tenant?.whatsapp_phone_number_id || '',
    whatsapp_business_account_id: props.tenant?.whatsapp_business_account_id || '',
    whatsapp_access_token: '',
});
const testPhone = ref('');

// Step 3: Menu
const menuSource = ref('internal');
const categories = ref([
    { name: '', items: [{ name: '', price: '', description: '' }] },
]);
const externalUrl = ref('');
const externalKey = ref('');
const apiTestResult = ref(null);

// Step 4: Admin
const adminForm = useForm({
    name: props.user?.name === 'Admin' ? '' : (props.user?.name || ''),
    email: props.user?.email === 'admin@waorder.com' ? '' : (props.user?.email || ''),
    password: '',
    password_confirmation: '',
});

const saveStep1 = async () => {
    saving.value = true;
    restaurantForm.post('/setup/restaurant', {
        preserveScroll: true,
        onSuccess: () => { currentStep.value = 2; saving.value = false; },
        onError: () => { saving.value = false; },
    });
};

const saveStep2 = async () => {
    saving.value = true;
    whatsappForm.post('/setup/whatsapp', {
        preserveScroll: true,
        onSuccess: () => { currentStep.value = 3; saving.value = false; },
        onError: () => { saving.value = false; },
    });
};

const testWhatsApp = async () => {
    testResult.value = null;
    try {
        const response = await fetch('/setup/test-whatsapp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ phone: testPhone.value }),
        });
        const data = await response.json();
        testResult.value = data.success ? 'success' : `error: ${data.error || 'Fallo el envio'}`;
    } catch (e) {
        testResult.value = 'error: No se pudo conectar';
    }
};

const addCategory = () => {
    categories.value.push({ name: '', items: [{ name: '', price: '', description: '' }] });
};

const removeCategory = (index) => {
    categories.value.splice(index, 1);
};

const addItem = (catIndex) => {
    categories.value[catIndex].items.push({ name: '', price: '', description: '' });
};

const removeItem = (catIndex, itemIndex) => {
    categories.value[catIndex].items.splice(itemIndex, 1);
};

const saveStep3 = () => {
    saving.value = true;
    const payload = menuSource.value === 'internal'
        ? { menu_source: 'internal', categories: categories.value }
        : { menu_source: 'external', menu_api_url: externalUrl.value, menu_api_key: externalKey.value };

    router.post('/setup/menu', payload, {
        preserveScroll: true,
        onSuccess: () => { currentStep.value = 4; saving.value = false; },
        onError: () => { saving.value = false; },
    });
};

const testMenuApi = async () => {
    apiTestResult.value = null;
    try {
        const response = await fetch('/setup/test-menu-api', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ url: externalUrl.value, api_key: externalKey.value }),
        });
        const data = await response.json();
        apiTestResult.value = data.success ? 'success' : `error: ${data.error}`;
    } catch (e) {
        apiTestResult.value = 'error: No se pudo conectar';
    }
};

const saveStep4 = async () => {
    saving.value = true;
    adminForm.post('/setup/admin', {
        preserveScroll: true,
        onSuccess: () => {
            // Complete setup
            router.post('/setup/complete');
        },
        onError: () => { saving.value = false; },
    });
};

const skipStep = () => {
    if (currentStep.value < totalSteps) {
        currentStep.value++;
    }
};
</script>

<template>
    <div class="min-h-screen bg-gray-50 py-8">
        <div class="max-w-3xl mx-auto px-4">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-primary-600">WaOrder</h1>
                <p class="text-gray-500 mt-1">Configuracion inicial de tu restaurante</p>
            </div>

            <!-- Stepper -->
            <div class="flex items-center justify-center mb-10">
                <div v-for="(step, index) in steps" :key="step.number" class="flex items-center">
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold transition-colors"
                             :class="currentStep >= step.number ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-500'">
                            <svg v-if="currentStep > step.number" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span v-else>{{ step.number }}</span>
                        </div>
                        <p class="text-xs mt-1 font-medium" :class="currentStep >= step.number ? 'text-primary-600' : 'text-gray-400'">
                            {{ step.title }}
                        </p>
                    </div>
                    <div v-if="index < steps.length - 1" class="w-16 h-0.5 mx-2 mb-5"
                         :class="currentStep > step.number ? 'bg-primary-600' : 'bg-gray-200'" />
                </div>
            </div>

            <!-- Step 1: Restaurant -->
            <div v-if="currentStep === 1" class="bg-white rounded-xl shadow-sm border p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-1">Datos del Restaurante</h2>
                <p class="text-gray-500 mb-6">Informacion basica de tu negocio</p>

                <form @submit.prevent="saveStep1" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del restaurante *</label>
                        <input v-model="restaurantForm.name" type="text" required placeholder="Ej: Pizzeria Don Mario"
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Zona horaria</label>
                            <select v-model="restaurantForm.timezone" class="w-full px-3 py-2 border rounded-lg">
                                <option value="America/Santo_Domingo">Rep. Dominicana (AST)</option>
                                <option value="America/New_York">Eastern (EST)</option>
                                <option value="America/Chicago">Central (CST)</option>
                                <option value="America/Mexico_City">Mexico City</option>
                                <option value="America/Bogota">Colombia</option>
                                <option value="America/Lima">Peru</option>
                                <option value="America/Argentina/Buenos_Aires">Argentina</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                            <select v-model="restaurantForm.currency" class="w-full px-3 py-2 border rounded-lg">
                                <option value="DOP">DOP (RD$)</option>
                                <option value="USD">USD ($)</option>
                                <option value="MXN">MXN ($)</option>
                                <option value="COP">COP ($)</option>
                                <option value="ARS">ARS ($)</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Delivery fee</label>
                            <input v-model="restaurantForm.delivery_fee" type="number" step="0.01" min="0"
                                   class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pedido minimo</label>
                            <input v-model="restaurantForm.min_order" type="number" step="0.01" min="0"
                                   class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tiempo est. (min)</label>
                            <input v-model="restaurantForm.estimated_time" type="number" min="1" max="180"
                                   class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                    </div>
                    <div class="pt-4">
                        <button type="submit" :disabled="saving || !restaurantForm.name"
                                class="w-full bg-primary-600 text-white py-2.5 rounded-lg font-medium hover:bg-primary-700 disabled:opacity-50">
                            {{ saving ? 'Guardando...' : 'Siguiente' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Step 2: WhatsApp -->
            <div v-if="currentStep === 2" class="bg-white rounded-xl shadow-sm border p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-1">Conectar WhatsApp</h2>
                <p class="text-gray-500 mb-6">Credenciales de tu app en Meta for Developers</p>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-blue-800">
                        <strong>Donde encontrar estos datos:</strong><br>
                        1. Ir a <a href="https://developers.facebook.com/apps/" target="_blank" class="underline">developers.facebook.com/apps</a><br>
                        2. Seleccionar tu app > WhatsApp > Getting Started<br>
                        3. Copiar Phone Number ID y Access Token de esa pagina
                    </p>
                </div>

                <form @submit.prevent="saveStep2" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number ID *</label>
                        <input v-model="whatsappForm.whatsapp_phone_number_id" type="text" required
                               placeholder="Ej: 123456789012345"
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Business Account ID *</label>
                        <input v-model="whatsappForm.whatsapp_business_account_id" type="text" required
                               placeholder="Ej: 987654321098765"
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Access Token *</label>
                        <input v-model="whatsappForm.whatsapp_access_token" type="password" required
                               placeholder="Pegar access token aqui"
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500" />
                        <p class="text-xs text-gray-400 mt-1">Token temporal (24h) o permanente</p>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">Verify Token para el Webhook:</p>
                        <code class="bg-white px-3 py-1.5 rounded border text-sm select-all">{{ verifyToken }}</code>
                        <p class="text-xs text-gray-400 mt-1">Copia este token cuando configures el webhook en Meta</p>
                    </div>

                    <!-- Test connection -->
                    <div class="border-t pt-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">Probar conexion (opcional)</p>
                        <div class="flex gap-2">
                            <input v-model="testPhone" type="text" placeholder="+18091234567"
                                   class="flex-1 px-3 py-2 border rounded-lg text-sm" />
                            <button type="button" @click="testWhatsApp" :disabled="!testPhone || !whatsappForm.whatsapp_access_token"
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 disabled:opacity-50">
                                Enviar prueba
                            </button>
                        </div>
                        <p v-if="testResult === 'success'" class="text-green-600 text-sm mt-2">Mensaje enviado correctamente</p>
                        <p v-else-if="testResult" class="text-red-600 text-sm mt-2">{{ testResult }}</p>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button type="button" @click="currentStep = 1" class="px-6 py-2.5 border rounded-lg text-gray-600 hover:bg-gray-50">
                            Atras
                        </button>
                        <button type="submit" :disabled="saving"
                                class="flex-1 bg-primary-600 text-white py-2.5 rounded-lg font-medium hover:bg-primary-700 disabled:opacity-50">
                            {{ saving ? 'Guardando...' : 'Siguiente' }}
                        </button>
                        <button type="button" @click="skipStep" class="px-4 py-2.5 text-gray-400 hover:text-gray-600 text-sm">
                            Omitir
                        </button>
                    </div>
                </form>
            </div>

            <!-- Step 3: Menu -->
            <div v-if="currentStep === 3" class="bg-white rounded-xl shadow-sm border p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-1">Menu del Restaurante</h2>
                <p class="text-gray-500 mb-6">Configura tu menu de productos</p>

                <div class="flex gap-3 mb-6">
                    <button @click="menuSource = 'internal'"
                            class="flex-1 py-3 px-4 rounded-lg border-2 text-center transition-colors"
                            :class="menuSource === 'internal' ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-gray-200 text-gray-600'">
                        <p class="font-medium">Crear manualmente</p>
                        <p class="text-xs mt-0.5">Agregar categorias y productos aqui</p>
                    </button>
                    <button @click="menuSource = 'external'"
                            class="flex-1 py-3 px-4 rounded-lg border-2 text-center transition-colors"
                            :class="menuSource === 'external' ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-gray-200 text-gray-600'">
                        <p class="font-medium">API externa</p>
                        <p class="text-xs mt-0.5">Importar desde otro sistema</p>
                    </button>
                </div>

                <!-- Internal menu -->
                <div v-if="menuSource === 'internal'" class="space-y-4">
                    <div v-for="(cat, ci) in categories" :key="ci" class="border rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <input v-model="cat.name" placeholder="Nombre de categoria (Ej: Pizzas)" required
                                   class="flex-1 px-3 py-2 border rounded-lg font-medium" />
                            <button v-if="categories.length > 1" @click="removeCategory(ci)" class="text-red-400 hover:text-red-600 p-1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                        <div v-for="(item, ii) in cat.items" :key="ii" class="flex gap-2 mb-2">
                            <input v-model="item.name" placeholder="Nombre del producto" required
                                   class="flex-1 px-2 py-1.5 border rounded text-sm" />
                            <input v-model="item.price" type="number" step="0.01" placeholder="Precio" required
                                   class="w-24 px-2 py-1.5 border rounded text-sm" />
                            <input v-model="item.description" placeholder="Descripcion (opcional)"
                                   class="flex-1 px-2 py-1.5 border rounded text-sm" />
                            <button v-if="cat.items.length > 1" @click="removeItem(ci, ii)" class="text-red-300 hover:text-red-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <button @click="addItem(ci)" class="text-sm text-primary-600 hover:text-primary-700 mt-1">
                            + Agregar producto
                        </button>
                    </div>
                    <button @click="addCategory" class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                        + Agregar categoria
                    </button>
                </div>

                <!-- External API -->
                <div v-if="menuSource === 'external'" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">URL de la API *</label>
                        <input v-model="externalUrl" type="url" placeholder="https://mi-sistema.com/api/menu"
                               class="w-full px-3 py-2 border rounded-lg" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">API Key (opcional)</label>
                        <input v-model="externalKey" type="text" placeholder="Bearer token o API key"
                               class="w-full px-3 py-2 border rounded-lg" />
                    </div>
                    <button @click="testMenuApi" :disabled="!externalUrl"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 disabled:opacity-50">
                        Probar conexion
                    </button>
                    <p v-if="apiTestResult === 'success'" class="text-green-600 text-sm">Conexion exitosa</p>
                    <p v-else-if="apiTestResult" class="text-red-600 text-sm">{{ apiTestResult }}</p>
                </div>

                <div class="flex gap-3 pt-6">
                    <button @click="currentStep = 2" class="px-6 py-2.5 border rounded-lg text-gray-600 hover:bg-gray-50">
                        Atras
                    </button>
                    <button @click="saveStep3" :disabled="saving"
                            class="flex-1 bg-primary-600 text-white py-2.5 rounded-lg font-medium hover:bg-primary-700 disabled:opacity-50">
                        {{ saving ? 'Guardando...' : 'Siguiente' }}
                    </button>
                    <button @click="skipStep" class="px-4 py-2.5 text-gray-400 hover:text-gray-600 text-sm">
                        Omitir
                    </button>
                </div>
            </div>

            <!-- Step 4: Admin -->
            <div v-if="currentStep === 4" class="bg-white rounded-xl shadow-sm border p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-1">Tu Cuenta de Administrador</h2>
                <p class="text-gray-500 mb-6">Actualiza tus datos de acceso</p>

                <form @submit.prevent="saveStep4" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tu nombre *</label>
                        <input v-model="adminForm.name" type="text" required placeholder="Tu nombre completo"
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input v-model="adminForm.email" type="email" required placeholder="tu@email.com"
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nueva contrasena *</label>
                        <input v-model="adminForm.password" type="password" required minlength="8"
                               placeholder="Minimo 8 caracteres"
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar contrasena *</label>
                        <input v-model="adminForm.password_confirmation" type="password" required
                               placeholder="Repetir contrasena"
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500" />
                        <p v-if="adminForm.errors.password" class="text-red-500 text-sm mt-1">{{ adminForm.errors.password }}</p>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button type="button" @click="currentStep = 3" class="px-6 py-2.5 border rounded-lg text-gray-600 hover:bg-gray-50">
                            Atras
                        </button>
                        <button type="submit" :disabled="saving || !adminForm.password || adminForm.password !== adminForm.password_confirmation"
                                class="flex-1 bg-primary-600 text-white py-2.5 rounded-lg font-medium hover:bg-primary-700 disabled:opacity-50">
                            {{ saving ? 'Finalizando...' : 'Completar configuracion' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
