<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import AppSwitch from '@/Components/AppSwitch.vue';
import { playNotificationSound } from '@/Composables/useOrderNotification';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    tenant: Object,
    whatsappConfig: Object,
    hasWhatsAppToken: Boolean,
    hasWhatsAppAppSecret: Boolean,
    hasAiKey: Boolean,
    hasDeliveryAppAddon: Boolean,
});

const activeTab = ref('general');

const generalForm = useForm({
    name: props.tenant?.name || '',
    timezone: props.tenant?.timezone || 'America/Santo_Domingo',
    currency: props.tenant?.currency || 'DOP',
    settings: {
        delivery_fee: props.tenant?.settings?.delivery_fee ?? 150,
        min_order: props.tenant?.settings?.min_order ?? 0,
        estimated_time: props.tenant?.settings?.estimated_time ?? 30,
        restaurant_phone: props.tenant?.settings?.restaurant_phone || '',
    },
});

const whatsappForm = useForm({
    whatsapp_phone_number_id: props.tenant?.whatsapp_phone_number_id || '',
    whatsapp_business_account_id: props.tenant?.whatsapp_business_account_id || '',
    whatsapp_access_token: '',
    whatsapp_app_secret: '',
});

const showToken = ref(false);
const showAppSecret = ref(false);
const hasExistingToken = ref(!!props.hasWhatsAppToken);
const hasExistingAppSecret = ref(!!props.hasWhatsAppAppSecret);
const testResult = ref(null);
const testing = ref(false);

const menuForm = useForm({
    settings: {
        menu_source: props.tenant?.settings?.menu_source || 'internal',
        menu_api_url: props.tenant?.settings?.menu_api_url || '',
        menu_api_key: props.tenant?.settings?.menu_api_key || '',
        menu_api_secret: props.tenant?.settings?.menu_api_secret || '',
        menu_api_auth_mode: props.tenant?.settings?.menu_api_auth_mode || 'bearer',
        menu_sync_interval: props.tenant?.settings?.menu_sync_interval ?? 15,
    },
});

const paymentForm = useForm({
    settings: {
        payment: {
            methods: props.tenant?.settings?.payment?.methods || ['cash'],
            transfer_info: {
                bank: props.tenant?.settings?.payment?.transfer_info?.bank || '',
                account_type: props.tenant?.settings?.payment?.transfer_info?.account_type || 'Ahorro',
                account_number: props.tenant?.settings?.payment?.transfer_info?.account_number || '',
                holder_name: props.tenant?.settings?.payment?.transfer_info?.holder_name || '',
                rnc: props.tenant?.settings?.payment?.transfer_info?.rnc || '',
            },
            card_link: {
                gateway: props.tenant?.settings?.payment?.card_link?.gateway || 'cardnet',
                url: props.tenant?.settings?.payment?.card_link?.url || '',
                instructions: props.tenant?.settings?.payment?.card_link?.instructions || '',
            },
            custom_methods: props.tenant?.settings?.payment?.custom_methods || {},
            cardnet: {
                public_key: props.tenant?.settings?.payment?.cardnet?.public_key || '',
                private_key: props.tenant?.settings?.payment?.cardnet?.private_key || '',
                commerce_id: props.tenant?.settings?.payment?.cardnet?.commerce_id || '',
            },
        },
    },
});

const newMethodName = ref('');
const newMethodError = ref('');
const builtInKeys = ['cash', 'transfer', 'card_link', 'cardnet'];

const slugify = (name) => {
    return name.toLowerCase().trim()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_|_$/g, '');
};

const togglePaymentMethod = (method) => {
    const methods = paymentForm.settings.payment.methods;
    const idx = methods.indexOf(method);
    if (idx > -1) {
        if (methods.length > 1) {
            methods.splice(idx, 1);
        }
    } else {
        methods.push(method);
    }
};

const addCustomMethod = () => {
    const name = newMethodName.value.trim();
    if (!name) {
        newMethodError.value = 'Ingresa un nombre';
        return;
    }
    if (name.length > 20) {
        newMethodError.value = 'Maximo 20 caracteres (limite de WhatsApp)';
        return;
    }
    const slug = slugify(name);
    if (!slug) {
        newMethodError.value = 'Nombre invalido';
        return;
    }
    if (builtInKeys.includes(slug)) {
        newMethodError.value = 'Ese nombre esta reservado';
        return;
    }
    if (paymentForm.settings.payment.custom_methods[slug]) {
        newMethodError.value = 'Ya existe un metodo con ese nombre';
        return;
    }

    paymentForm.settings.payment.custom_methods[slug] = { name, instructions: '' };
    paymentForm.settings.payment.methods.push(slug);
    newMethodName.value = '';
    newMethodError.value = '';
};

const removeCustomMethod = (slug) => {
    delete paymentForm.settings.payment.custom_methods[slug];
    const idx = paymentForm.settings.payment.methods.indexOf(slug);
    if (idx > -1) {
        paymentForm.settings.payment.methods.splice(idx, 1);
    }
};

const taxForm = useForm({
    settings: {
        taxes: props.tenant?.settings?.taxes || [],
    },
});

const addTax = () => {
    taxForm.settings.taxes.push({ name: '', rate: 0, enabled: true });
};

const removeTax = (index) => {
    taxForm.settings.taxes.splice(index, 1);
};

const saveTaxes = () => {
    taxForm.put('/settings', { preserveScroll: true });
};

const showApiDocs = ref(false);
const showMetaGuide = ref(false);

const saveGeneral = () => {
    generalForm.put('/settings', { preserveScroll: true });
};

const saveWhatsApp = () => {
    // Remove empty secrets so we don't overwrite existing ones with blank
    const hadToken = !!whatsappForm.whatsapp_access_token;
    const hadAppSecret = !!whatsappForm.whatsapp_app_secret;

    whatsappForm.transform((data) => {
        const result = { ...data };
        if (!hadToken) delete result.whatsapp_access_token;
        if (!hadAppSecret) delete result.whatsapp_app_secret;
        return result;
    });

    whatsappForm.put('/settings', {
        preserveScroll: true,
        onSuccess: () => {
            if (hadToken) {
                hasExistingToken.value = true;
                whatsappForm.whatsapp_access_token = '';
                showToken.value = false;
            }
            if (hadAppSecret) {
                hasExistingAppSecret.value = true;
                whatsappForm.whatsapp_app_secret = '';
                showAppSecret.value = false;
            }
        },
    });
};

const testWhatsApp = async () => {
    testing.value = true;
    testResult.value = null;
    try {
        const response = await fetch('/settings/test-whatsapp', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        testResult.value = await response.json();
    } catch (e) {
        testResult.value = { success: false, message: 'Error de red: ' + e.message };
    } finally {
        testing.value = false;
    }
};

const saveMenu = () => {
    menuForm.put('/settings', { preserveScroll: true });
};

const savePayment = () => {
    paymentForm.put('/settings', { preserveScroll: true });
};

const defaultSurveyQuestions = [
    {
        key: 'rating',
        label: '¿Cómo calificarías tu experiencia de hoy?',
        type: 'rating',
        enabled: true,
        options: [
            { id: 'rate_5', title: '⭐⭐⭐⭐⭐ (5)' },
            { id: 'rate_4', title: '⭐⭐⭐⭐ (4)' },
            { id: 'rate_3', title: '⭐⭐⭐ (3 o menos)' },
        ],
    },
    {
        key: 'food_quality',
        label: '¿Cómo estuvo la calidad de la comida?',
        type: 'buttons',
        enabled: true,
        options: [
            { id: 'food_excellent', title: 'Excelente' },
            { id: 'food_good', title: 'Buena' },
            { id: 'food_regular', title: 'Regular' },
        ],
    },
    {
        key: 'comment',
        label: '¿Tienes algún comentario adicional?',
        type: 'text',
        enabled: true,
        options: [],
    },
];

const surveyForm = useForm({
    settings: {
        survey: {
            enabled: props.tenant?.settings?.survey?.enabled ?? true,
            thank_you_message: props.tenant?.settings?.survey?.thank_you_message || 'Muchas gracias por tu opinion! Nos ayuda a mejorar cada dia.',
            questions: props.tenant?.settings?.survey?.questions
                ? JSON.parse(JSON.stringify(props.tenant.settings.survey.questions))
                : JSON.parse(JSON.stringify(defaultSurveyQuestions)),
        },
    },
});

const saveSurvey = () => {
    surveyForm.put('/settings', { preserveScroll: true });
};

const moveSurveyQuestion = (index, direction) => {
    const questions = surveyForm.settings.survey.questions;
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= questions.length) return;
    const [removed] = questions.splice(index, 1);
    questions.splice(newIndex, 0, removed);
};

const addSurveyOption = (questionIndex) => {
    const question = surveyForm.settings.survey.questions[questionIndex];
    const newId = `${question.key}_opt_${question.options.length}`;
    question.options.push({ id: newId, title: '' });
};

const removeSurveyOption = (questionIndex, optionIndex) => {
    surveyForm.settings.survey.questions[questionIndex].options.splice(optionIndex, 1);
};

const driverForm = useForm({
    settings: {
        driver_mode: props.tenant?.settings?.driver_mode || 'whatsapp',
    },
});

const saveDriverMode = () => {
    driverForm.put('/settings', { preserveScroll: true });
};

const hoursForm = useForm({
    settings: {
        business_hours: {
            enabled: props.tenant?.settings?.business_hours?.enabled ?? false,
            open: props.tenant?.settings?.business_hours?.open || '08:00',
            close: props.tenant?.settings?.business_hours?.close || '22:00',
            days: props.tenant?.settings?.business_hours?.days || [1, 2, 3, 4, 5, 6],
            closed_message: props.tenant?.settings?.business_hours?.closed_message || '',
        },
    },
});

const dayNames = [
    { value: 0, label: 'Dom' },
    { value: 1, label: 'Lun' },
    { value: 2, label: 'Mar' },
    { value: 3, label: 'Mie' },
    { value: 4, label: 'Jue' },
    { value: 5, label: 'Vie' },
    { value: 6, label: 'Sab' },
];

const toggleDay = (day) => {
    const idx = hoursForm.settings.business_hours.days.indexOf(day);
    if (idx > -1) {
        hoursForm.settings.business_hours.days.splice(idx, 1);
    } else {
        hoursForm.settings.business_hours.days.push(day);
        hoursForm.settings.business_hours.days.sort();
    }
};

const saveHours = () => {
    hoursForm.put('/settings', { preserveScroll: true });
};

const notificationForm = useForm({
    settings: {
        notifications: {
            sound_enabled: props.tenant?.settings?.notifications?.sound_enabled ?? false,
            polling_interval: props.tenant?.settings?.notifications?.polling_interval ?? 20,
        },
    },
});

const hasCustomSound = ref(!!props.tenant?.settings?.notifications?.custom_sound_path);
const soundUploading = ref(false);

const saveNotifications = () => {
    notificationForm.put('/settings', { preserveScroll: true });
};

const testSound = () => {
    const customUrl = props.tenant?.settings?.notifications?.custom_sound_path
        ? `/storage/${props.tenant.settings.notifications.custom_sound_path}`
        : null;
    playNotificationSound(customUrl);
};

const uploadSound = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const form = new FormData();
    form.append('sound_file', file);

    soundUploading.value = true;
    router.post('/settings/notification-sound', form, {
        preserveScroll: true,
        onSuccess: () => {
            hasCustomSound.value = true;
            soundUploading.value = false;
        },
        onError: () => {
            soundUploading.value = false;
        },
    });
    // Reset input so the same file can be re-uploaded
    event.target.value = '';
};

const deleteSound = () => {
    router.delete('/settings/notification-sound', {
        preserveScroll: true,
        onSuccess: () => {
            hasCustomSound.value = false;
        },
    });
};

const planFeatures = computed(() => usePage().props.plan?.features || {});

const tabs = computed(() => {
    const allTabs = [
        { id: 'general', label: 'General' },
        { id: 'taxes', label: 'Impuestos' },
        { id: 'hours', label: 'Horario' },
        { id: 'whatsapp', label: 'WhatsApp' },
        { id: 'menu', label: 'Menu' },
        { id: 'marca', label: 'Marca' },
        { id: 'payment', label: 'Pagos' },
        { id: 'drivers', label: 'Mensajeros' },
        { id: 'survey', label: 'Encuesta' },
        { id: 'notifications', label: 'Notificaciones' },
        { id: 'app_movil', label: 'App Movil', feature: 'delivery_app' },
    ];
    return allTabs.filter(t => !t.feature || planFeatures.value[t.feature]);
});

// ─── App Móvil / EAS Build ────────────────────────────────────────────────────

const easForm = useForm({
    settings: {
        eas: {
            token: '',
            mobile_path: props.tenant?.settings?.eas?.mobile_path || '',
        },
    },
});
const easHasToken   = ref(!!props.tenant?.settings?.eas?.token);
const easShowToken  = ref(false);

const saveEas = () => {
    easForm.put('/settings', { preserveScroll: true, onSuccess: () => { easHasToken.value = true; } });
};

// Builds state
const builds          = ref([]);
const buildsLoading   = ref(false);
const buildsError     = ref('');
const buildsPlatform  = ref('all');

// Trigger state
const triggering      = ref({ android: false, ios: false });
const triggerMsg      = ref({ android: null, ios: null });   // { type: 'success'|'error', text, url }

const fetchBuilds = async () => {
    buildsLoading.value = true;
    buildsError.value   = '';
    try {
        const res = await axios.get(`/api/mobile-app/builds?platform=${buildsPlatform.value}`);
        builds.value = res.data ?? [];
    } catch (e) {
        buildsError.value = e.response?.data?.error || 'Error al cargar los builds.';
        builds.value = [];
    } finally {
        buildsLoading.value = false;
    }
};

watch(activeTab, (tab) => {
    if (tab === 'app_movil') fetchBuilds();
});

watch(buildsPlatform, fetchBuilds);

// ─── AI / NLP ─────────────────────────────────────────────────────────────────

const aiForm = useForm({
    ai_api_key: '',
    settings: {
        ai: {
            enabled: props.tenant?.settings?.ai?.enabled ?? false,
            provider: props.tenant?.settings?.ai?.provider || 'groq',
            model: props.tenant?.settings?.ai?.model || '',
        },
    },
});
const aiHasKey   = ref(props.hasAiKey ?? false);
const aiShowKey  = ref(false);
const aiTestResult = ref(null);
const aiTesting    = ref(false);

const aiProviderModels = {
    groq:   ['llama-3.1-8b-instant', 'llama-3.1-70b-versatile', 'mixtral-8x7b-32768'],
    openai: ['gpt-4o-mini', 'gpt-4o', 'gpt-3.5-turbo'],
};

const saveAi = () => {
    aiForm.put('/settings', {
        preserveScroll: true,
        onSuccess: () => {
            if (aiForm.ai_api_key) aiHasKey.value = true;
            if (aiForm.ai_api_key === '') aiHasKey.value = false;
            aiForm.ai_api_key = '';
            aiShowKey.value = false;
            aiTestResult.value = null;
        },
    });
};

const testAi = async () => {
    aiTesting.value = true;
    aiTestResult.value = null;
    try {
        const { data } = await axios.post('/settings/test-ai');
        aiTestResult.value = data;
    } catch (e) {
        aiTestResult.value = { success: false, message: e.response?.data?.message || e.message };
    } finally {
        aiTesting.value = false;
    }
};

const triggerBuild = async (platform, profile = 'preview') => {
    triggering.value[platform] = true;
    triggerMsg.value[platform] = null;
    try {
        const res = await axios.post('/api/mobile-app/builds/trigger', { platform, profile });
        triggerMsg.value[platform] = {
            type: 'success',
            text: 'Build iniciado correctamente. Puede tardar 10–25 min.',
            url: res.data.buildUrl,
        };
        setTimeout(fetchBuilds, 4000);
    } catch (e) {
        triggerMsg.value[platform] = {
            type: 'error',
            text: e.response?.data?.detail || e.response?.data?.error || 'Error al iniciar el build.',
        };
    } finally {
        triggering.value[platform] = false;
    }
};

const buildStatusLabel = (status) => ({
    FINISHED:    { label: 'Listo',      cls: 'bg-green-100 text-green-700' },
    IN_PROGRESS: { label: 'Compilando', cls: 'bg-amber-100 text-amber-700' },
    NEW:         { label: 'En cola',    cls: 'bg-blue-100  text-blue-700'  },
    ERRORED:     { label: 'Error',      cls: 'bg-red-100   text-red-700'   },
    CANCELED:    { label: 'Cancelado',  cls: 'bg-gray-100  text-gray-500'  },
}[status] ?? { label: status, cls: 'bg-gray-100 text-gray-500' });

const platformIcon = (platform) => platform === 'ANDROID' ? '🤖' : '🍎';

const formatBuildDate = (iso) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('es-DO', { dateStyle: 'short', timeStyle: 'short' });
};

// ─── Marca (Branding) ───────────────────────────────────────────────────────

const marcaForm = useForm({
    settings: {
        menu_theme: {
            primary_color: props.tenant?.settings?.menu_theme?.primary_color || '#4f46e5',
            show_restaurant_name: props.tenant?.settings?.menu_theme?.show_restaurant_name ?? true,
        },
    },
});

const currentLogoUrl = ref(props.tenant?.settings?.menu_theme?.logo_url || null);
const logoUploading = ref(false);

const saveMarca = () => {
    marcaForm.put('/settings', { preserveScroll: true });
};

const uploadLogo = (event) => {
    const file = event.target.files[0];
    if (!file) return;
    const form = new FormData();
    form.append('logo', file);
    logoUploading.value = true;
    router.post('/settings/logo', form, {
        preserveScroll: true,
        onSuccess: () => {
            logoUploading.value = false;
            // reload to get new URL
            router.reload({ only: ['tenant'] });
        },
        onError: () => { logoUploading.value = false; },
    });
    event.target.value = '';
};

const deleteLogo = () => {
    router.delete('/settings/logo', {
        preserveScroll: true,
        onSuccess: () => { currentLogoUrl.value = null; },
    });
};

// Color palette preview helper
function hexToHsl(hex) {
    let r = parseInt(hex.slice(1,3),16)/255, g = parseInt(hex.slice(3,5),16)/255, b = parseInt(hex.slice(5,7),16)/255;
    const max = Math.max(r,g,b), min = Math.min(r,g,b);
    let h, s, l = (max+min)/2;
    if (max===min) { h=s=0; } else {
        const d=max-min; s=l>0.5?d/(2-max-min):d/(max+min);
        switch(max){case r:h=((g-b)/d+(g<b?6:0))/6;break;case g:h=((b-r)/d+2)/6;break;case b:h=((r-g)/d+4)/6;break;}
    }
    return [Math.round(h*360), Math.round(s*100), Math.round(l*100)];
}

const previewThemeVars = computed(() => {
    const hex = marcaForm.settings.menu_theme.primary_color || '#4f46e5';
    const [h, s] = hexToHsl(hex);
    return {
        '--color-primary-50':  `hsl(${h},${s}%,97%)`,
        '--color-primary-100': `hsl(${h},${s}%,93%)`,
        '--color-primary-200': `hsl(${h},${s}%,86%)`,
        '--color-primary-500': `hsl(${h},${s}%,53%)`,
        '--color-primary-600': hex,
        '--color-primary-700': `hsl(${h},${s}%,36%)`,
    };
});

const previewLogo = computed(() => props.tenant?.settings?.menu_theme?.logo_url || null);
const previewName = computed(() => props.tenant?.name || 'Mi Restaurante');
</script>

<template>
    <div>
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Configuracion</h1>

        <!-- Tabs -->
        <div class="border-b mb-6">
            <div class="flex gap-6">
                <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
                        class="pb-3 text-sm font-medium border-b-2 transition-colors"
                        :class="activeTab === tab.id ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <!-- General Tab -->
        <div v-if="activeTab === 'general'" class="bg-white rounded-xl shadow-sm border p-6 max-w-2xl">
            <h2 class="text-lg font-semibold mb-4">Datos del Restaurante</h2>
            <form @submit.prevent="saveGeneral" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <input v-model="generalForm.name" type="text" class="w-full px-3 py-2 border rounded-lg" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefono del restaurante</label>
                    <input v-model="generalForm.settings.restaurant_phone" type="text" placeholder="+18091234567"
                           class="w-full px-3 py-2 border rounded-lg" />
                    <p class="text-xs text-gray-400 mt-1">Para que los clientes puedan llamar</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Zona horaria</label>
                        <select v-model="generalForm.timezone" class="w-full px-3 py-2 border rounded-lg">
                            <option value="America/Santo_Domingo">Rep. Dominicana</option>
                            <option value="America/New_York">Eastern</option>
                            <option value="America/Mexico_City">Mexico City</option>
                            <option value="America/Bogota">Colombia</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Moneda</label>
                        <select v-model="generalForm.currency" class="w-full px-3 py-2 border rounded-lg">
                            <option value="DOP">DOP (RD$)</option>
                            <option value="USD">USD ($)</option>
                            <option value="MXN">MXN ($)</option>
                            <option value="COP">COP ($)</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Delivery fee</label>
                        <input v-model="generalForm.settings.delivery_fee" type="number" step="0.01" class="w-full px-3 py-2 border rounded-lg" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pedido minimo</label>
                        <input v-model="generalForm.settings.min_order" type="number" step="0.01" class="w-full px-3 py-2 border rounded-lg" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tiempo est. (min)</label>
                        <input v-model="generalForm.settings.estimated_time" type="number" class="w-full px-3 py-2 border rounded-lg" />
                    </div>
                </div>
                <button type="submit" :disabled="generalForm.processing"
                        class="bg-primary-600 text-white px-6 py-2 rounded-lg hover:bg-primary-700 disabled:opacity-50">
                    {{ generalForm.processing ? 'Guardando...' : 'Guardar cambios' }}
                </button>
                <p v-if="generalForm.recentlySuccessful" class="text-green-600 text-sm">Guardado</p>
            </form>
        </div>

        <!-- Taxes Tab -->
        <div v-if="activeTab === 'taxes'" class="bg-white rounded-xl shadow-sm border p-6 max-w-2xl">
            <h2 class="text-lg font-semibold mb-2">Impuestos</h2>
            <p class="text-sm text-gray-500 mb-6">Configura los impuestos que se aplican a los productos. Los precios en el menu de WhatsApp se mostraran con impuestos incluidos. El delivery no aplica impuesto.</p>

            <form @submit.prevent="saveTaxes" class="space-y-4">
                <div v-if="taxForm.settings.taxes.length === 0" class="text-center py-8 text-gray-400">
                    <p class="text-sm">No hay impuestos configurados</p>
                    <p class="text-xs mt-1">Los precios se mostraran sin impuestos</p>
                </div>

                <div v-for="(tax, index) in taxForm.settings.taxes" :key="index"
                     class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                    <input v-model="tax.enabled" type="checkbox"
                           class="rounded border-gray-300 text-orange-600 focus:ring-orange-500" />
                    <input v-model="tax.name" type="text" placeholder="Ej: ITBIS"
                           class="flex-1 px-3 py-2 border rounded-lg text-sm" />
                    <div class="flex items-center gap-1">
                        <input v-model="tax.rate" type="number" step="0.01" min="0" max="100"
                               class="w-20 px-3 py-2 border rounded-lg text-sm text-right" />
                        <span class="text-sm text-gray-500">%</span>
                    </div>
                    <button type="button" @click="removeTax(index)"
                            class="text-red-400 hover:text-red-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>

                <button type="button" @click="addTax"
                        class="w-full py-2 border-2 border-dashed border-gray-300 rounded-lg text-sm text-gray-500 hover:border-orange-400 hover:text-orange-600 transition-colors">
                    + Agregar impuesto
                </button>

                <div v-if="taxForm.settings.taxes.length > 0" class="bg-orange-50 border border-orange-200 rounded-lg p-3">
                    <p class="text-sm text-orange-800">
                        <strong>Impuesto total:</strong>
                        {{ taxForm.settings.taxes.filter(t => t.enabled).reduce((sum, t) => sum + Number(t.rate || 0), 0) }}%
                    </p>
                    <p class="text-xs text-orange-600 mt-1">
                        Un producto de RD$100 se mostrara como RD${{ Math.round(100 * (1 + taxForm.settings.taxes.filter(t => t.enabled).reduce((sum, t) => sum + Number(t.rate || 0), 0) / 100)) }} en el menu
                    </p>
                </div>

                <button type="submit" :disabled="taxForm.processing"
                        class="bg-orange-600 text-white px-6 py-2 rounded-lg hover:bg-orange-700 disabled:opacity-50">
                    {{ taxForm.processing ? 'Guardando...' : 'Guardar' }}
                </button>
                <p v-if="taxForm.recentlySuccessful" class="text-green-600 text-sm">Guardado</p>
            </form>
        </div>

        <!-- Hours Tab -->
        <div v-if="activeTab === 'hours'" class="bg-white rounded-xl shadow-sm border p-6 max-w-2xl">
            <h2 class="text-lg font-semibold mb-4">Horario de Pedidos por WhatsApp</h2>
            <p class="text-sm text-gray-500 mb-6">Configura el horario en que los clientes pueden hacer pedidos. Fuera de este horario recibiran un mensaje automatico.</p>
            <form @submit.prevent="saveHours" class="space-y-6">
                <div class="flex items-center gap-3">
                    <input v-model="hoursForm.settings.business_hours.enabled" type="checkbox" id="hours_enabled"
                           class="rounded border-gray-300 text-orange-600 focus:ring-orange-500" />
                    <label for="hours_enabled" class="text-sm font-medium text-gray-700">Restringir horario de pedidos</label>
                </div>

                <div v-if="hoursForm.settings.business_hours.enabled" class="space-y-5 pl-6 border-l-2 border-orange-200">
                    <!-- Time range -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hora de apertura</label>
                            <input v-model="hoursForm.settings.business_hours.open" type="time"
                                   class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hora de cierre</label>
                            <input v-model="hoursForm.settings.business_hours.close" type="time"
                                   class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                    </div>

                    <!-- Days -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Dias habilitados</label>
                        <div class="flex gap-2">
                            <button v-for="day in dayNames" :key="day.value" type="button"
                                    @click="toggleDay(day.value)"
                                    class="w-10 h-10 rounded-lg text-sm font-medium transition-colors"
                                    :class="hoursForm.settings.business_hours.days.includes(day.value)
                                        ? 'bg-orange-600 text-white'
                                        : 'bg-gray-100 text-gray-500 hover:bg-gray-200'">
                                {{ day.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Custom message -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje personalizado (opcional)</label>
                        <textarea v-model="hoursForm.settings.business_hours.closed_message" rows="3"
                                  class="w-full px-3 py-2 border rounded-lg text-sm"
                                  placeholder="Deja vacio para usar el mensaje automatico con el horario" />
                        <p class="text-xs text-gray-400 mt-1">Si dejas vacio, se enviara un mensaje automatico indicando el horario de apertura.</p>
                    </div>
                </div>

                <button type="submit" :disabled="hoursForm.processing"
                        class="bg-orange-600 text-white px-6 py-2 rounded-lg hover:bg-orange-700 disabled:opacity-50">
                    {{ hoursForm.processing ? 'Guardando...' : 'Guardar' }}
                </button>
                <p v-if="hoursForm.recentlySuccessful" class="text-green-600 text-sm">Guardado</p>
            </form>
        </div>

        <!-- WhatsApp Tab -->
        <div v-if="activeTab === 'whatsapp'" class="space-y-6 max-w-2xl">
            <!-- Credenciales -->
            <form @submit.prevent="saveWhatsApp" class="bg-white rounded-xl shadow-sm border p-6">
                <h2 class="text-lg font-semibold mb-4">Credenciales de la API</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number ID</label>
                        <input v-model="whatsappForm.whatsapp_phone_number_id" type="text"
                               placeholder="Ej: 123456789012345"
                               class="w-full px-3 py-2 border rounded-lg" />
                        <p class="text-xs text-gray-400 mt-1">Se encuentra en Meta Developer Portal > WhatsApp > API Setup</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Business Account ID</label>
                        <input v-model="whatsappForm.whatsapp_business_account_id" type="text"
                               placeholder="Ej: 123456789012345"
                               class="w-full px-3 py-2 border rounded-lg" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Access Token</label>
                        <div class="relative">
                            <input v-model="whatsappForm.whatsapp_access_token"
                                   :type="showToken ? 'text' : 'password'"
                                   :placeholder="hasExistingToken ? 'Token guardado - deja vacio para mantener el actual' : 'Pega tu access token aqui'"
                                   class="w-full px-3 py-2 border rounded-lg pr-20" />
                            <button type="button" @click="showToken = !showToken"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-gray-500 hover:text-gray-700 px-2 py-1">
                                {{ showToken ? 'Ocultar' : 'Mostrar' }}
                            </button>
                        </div>
                        <p v-if="hasExistingToken" class="text-xs text-green-600 mt-1">Ya hay un token configurado. Solo ingresa uno nuevo si deseas reemplazarlo.</p>
                        <p v-else class="text-xs text-amber-600 mt-1">No hay token configurado. Genera uno en Meta Developer Portal.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">App Secret</label>
                        <div class="relative">
                            <input v-model="whatsappForm.whatsapp_app_secret"
                                   :type="showAppSecret ? 'text' : 'password'"
                                   :placeholder="hasExistingAppSecret ? 'Secret guardado - deja vacio para mantener el actual' : 'Pega tu App Secret aqui'"
                                   class="w-full px-3 py-2 border rounded-lg pr-20" />
                            <button type="button" @click="showAppSecret = !showAppSecret"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-gray-500 hover:text-gray-700 px-2 py-1">
                                {{ showAppSecret ? 'Ocultar' : 'Mostrar' }}
                            </button>
                        </div>
                        <p v-if="hasExistingAppSecret" class="text-xs text-green-600 mt-1">App Secret configurado. Solo ingresa uno nuevo si deseas reemplazarlo.</p>
                        <p v-else class="text-xs text-amber-600 mt-1">Requerido para validar webhooks. Se encuentra en Meta Developer Portal > Settings > Basic > App Secret.</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="whatsappForm.processing"
                                class="bg-primary-600 text-white px-6 py-2 rounded-lg hover:bg-primary-700 disabled:opacity-50">
                            {{ whatsappForm.processing ? 'Guardando...' : 'Guardar credenciales' }}
                        </button>
                        <p v-if="whatsappForm.recentlySuccessful" class="text-green-600 text-sm">Guardado</p>
                    </div>
                </div>
            </form>

            <!-- Test de Conexion -->
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h2 class="text-lg font-semibold mb-4">Probar Conexion</h2>
                <p class="text-sm text-gray-500 mb-4">Verifica que las credenciales sean validas y el token no haya expirado.</p>
                <button @click="testWhatsApp" :disabled="testing"
                        class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 disabled:opacity-50">
                    {{ testing ? 'Verificando...' : 'Probar conexion' }}
                </button>

                <div v-if="testResult" class="mt-4 rounded-lg p-4"
                     :class="testResult.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                    <div class="flex items-start gap-2">
                        <span :class="testResult.success ? 'text-green-600' : 'text-red-600'" class="text-lg leading-none">
                            {{ testResult.success ? '&#10003;' : '&#10007;' }}
                        </span>
                        <div>
                            <p :class="testResult.success ? 'text-green-800' : 'text-red-800'" class="text-sm font-medium">
                                {{ testResult.message }}
                            </p>
                            <div v-if="testResult.details" class="mt-2 text-sm text-green-700 space-y-1">
                                <p v-if="testResult.details.name"><span class="font-medium">Nombre verificado:</span> {{ testResult.details.name }}</p>
                                <p v-if="testResult.details.phone"><span class="font-medium">Telefono:</span> {{ testResult.details.phone }}</p>
                                <p v-if="testResult.details.quality"><span class="font-medium">Calidad:</span> {{ testResult.details.quality }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Webhook Info -->
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h2 class="text-lg font-semibold mb-4">Configuracion del Webhook</h2>
                <p class="text-sm text-gray-500 mb-4">Usa estos datos para configurar el webhook en Meta Developer Portal.</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Webhook URL</label>
                        <div class="flex gap-2">
                            <input :value="whatsappConfig?.webhook_url" type="text" readonly
                                   class="w-full px-3 py-2 border rounded-lg bg-gray-50 text-gray-600 text-sm font-mono" />
                            <button type="button" @click="navigator.clipboard.writeText(whatsappConfig?.webhook_url)"
                                    class="px-3 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 text-sm whitespace-nowrap">
                                Copiar
                            </button>
                        </div>
                        <p class="text-xs text-amber-500 mt-1">En produccion usa tu dominio real. Si usas ngrok, actualiza la URL en Meta cada vez que reinicies el tunnel.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Verify Token</label>
                        <div class="flex gap-2">
                            <input :value="whatsappConfig?.verify_token" type="text" readonly
                                   class="w-full px-3 py-2 border rounded-lg bg-gray-50 text-gray-600 text-sm font-mono" />
                            <button type="button" @click="navigator.clipboard.writeText(whatsappConfig?.verify_token)"
                                    class="px-3 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 text-sm whitespace-nowrap">
                                Copiar
                            </button>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Configurado en el archivo .env (WHATSAPP_VERIFY_TOKEN)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Version de la API</label>
                        <input :value="whatsappConfig?.api_version" type="text" readonly
                               class="w-full px-3 py-2 border rounded-lg bg-gray-50 text-gray-600 text-sm" />
                    </div>
                </div>
            </div>

            <!-- Guia Meta App -->
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <button type="button" @click="showMetaGuide = !showMetaGuide"
                        class="w-full flex items-center justify-between p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600 text-sm font-bold">M</div>
                        <div class="text-left">
                            <p class="text-sm font-semibold text-gray-800">Guia de configuracion de Meta</p>
                            <p class="text-xs text-gray-500">Como crear la app, obtener credenciales y configurar el webhook</p>
                        </div>
                    </div>
                    <svg :class="showMetaGuide ? 'rotate-180' : ''" class="w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div v-if="showMetaGuide" class="border-t px-5 pb-5 space-y-5 text-sm">

                    <!-- Paso 1 -->
                    <div class="pt-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center shrink-0">1</span>
                            <h4 class="font-semibold text-gray-800">Crear la app en Meta for Developers</h4>
                        </div>
                        <ol class="text-gray-600 space-y-1 list-decimal pl-8">
                            <li>Ve a <strong class="text-gray-800">developers.facebook.com</strong> e inicia sesion con tu cuenta de Facebook.</li>
                            <li>Haz clic en <strong>Mis apps</strong> &gt; <strong>Crear app</strong>.</li>
                            <li>Selecciona el tipo de app: <strong>Business</strong> (o <em>Otro</em> si no aparece Business directamente).</li>
                            <li>Ingresa un nombre para la app y selecciona tu cuenta de <strong>Meta Business</strong>.</li>
                            <li>Haz clic en <strong>Crear app</strong>. Puede pedirte verificar tu contrasena.</li>
                        </ol>
                    </div>

                    <!-- Paso 2 -->
                    <div class="border-t pt-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center shrink-0">2</span>
                            <h4 class="font-semibold text-gray-800">Agregar WhatsApp Business a la app</h4>
                        </div>
                        <ol class="text-gray-600 space-y-1 list-decimal pl-8">
                            <li>En el dashboard de tu app, busca el producto <strong>WhatsApp</strong> y haz clic en <strong>Configurar</strong>.</li>
                            <li>Selecciona (o crea) la cuenta de <strong>WhatsApp Business</strong> que usaras.</li>
                            <li>Acepta los terminos de servicio de WhatsApp Business Platform si es la primera vez.</li>
                        </ol>
                    </div>

                    <!-- Paso 3 -->
                    <div class="border-t pt-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center shrink-0">3</span>
                            <h4 class="font-semibold text-gray-800">Obtener Phone Number ID y Business Account ID</h4>
                        </div>
                        <ol class="text-gray-600 space-y-1 list-decimal pl-8">
                            <li>En el menu lateral, ve a <strong>WhatsApp &gt; API Setup</strong>.</li>
                            <li>En la seccion <em>Send and receive messages</em>, encontraras el <strong>Phone Number ID</strong> bajo el numero de telefono seleccionado. Copialo aqui arriba.</li>
                            <li>El <strong>WhatsApp Business Account ID (WABA ID)</strong> aparece tambien en esa misma pantalla, un poco mas arriba. Copialo en el campo correspondiente.</li>
                        </ol>
                        <div class="mt-3 bg-amber-50 border border-amber-200 rounded-lg p-3 text-amber-800">
                            <strong>Nota:</strong> El numero de prueba gratuito de Meta solo permite enviar mensajes a numeros previamente verificados. Para produccion necesitas agregar y verificar tu numero real de WhatsApp Business.
                        </div>
                    </div>

                    <!-- Paso 4 -->
                    <div class="border-t pt-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center shrink-0">4</span>
                            <h4 class="font-semibold text-gray-800">Obtener un Access Token permanente</h4>
                        </div>
                        <p class="text-gray-500 mb-2">El token temporal que aparece en <em>API Setup</em> expira en 24 horas. Para produccion crea un <strong>System User</strong>:</p>
                        <ol class="text-gray-600 space-y-1 list-decimal pl-8">
                            <li>Ve a <strong>business.facebook.com</strong> y selecciona tu negocio.</li>
                            <li>En <strong>Configuracion del negocio &gt; Usuarios &gt; Usuarios del sistema</strong>, haz clic en <strong>Agregar</strong>.</li>
                            <li>Crea un usuario con rol <em>Admin</em> y asignale la app de WhatsApp con permisos <strong>whatsapp_business_messaging</strong> y <strong>whatsapp_business_management</strong>.</li>
                            <li>Haz clic en <strong>Generar token</strong>, selecciona tu app, marca los permisos mencionados y copia el token generado.</li>
                            <li>Pega ese token en el campo <em>Access Token</em> de arriba y guarda.</li>
                        </ol>
                    </div>

                    <!-- Paso 5 -->
                    <div class="border-t pt-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold flex items-center justify-center shrink-0">5</span>
                            <h4 class="font-semibold text-gray-800">Configurar el Webhook en Meta</h4>
                        </div>
                        <ol class="text-gray-600 space-y-1 list-decimal pl-8">
                            <li>En tu app de Meta, ve a <strong>WhatsApp &gt; Configuration</strong>.</li>
                            <li>Haz clic en <strong>Edit</strong> en la seccion de Webhook.</li>
                            <li>En <em>Callback URL</em> pega la <strong>Webhook URL</strong> que aparece en la seccion de arriba.</li>
                            <li>En <em>Verify Token</em> pega el <strong>Verify Token</strong> que aparece en la seccion de arriba.</li>
                            <li>Haz clic en <strong>Verify and save</strong>. Si todo esta correcto, Meta confirmara la verificacion.</li>
                            <li>Luego haz clic en <strong>Manage</strong> y activa la suscripcion a <strong>messages</strong>.</li>
                        </ol>
                        <div class="mt-3 bg-blue-50 border border-blue-200 rounded-lg p-3 text-blue-800">
                            <strong>Desarrollo local:</strong> Si no tienes dominio publico, usa <strong>ngrok</strong> para exponer tu servidor local: <code class="bg-blue-100 px-1 rounded">ngrok http 8000</code>. Copia la URL HTTPS que te da y usala como webhook. Recuerda actualizarla cada vez que reinicies ngrok.
                        </div>
                    </div>

                    <!-- Paso 6 -->
                    <div class="border-t pt-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-6 h-6 rounded-full bg-green-600 text-white text-xs font-bold flex items-center justify-center shrink-0">✓</span>
                            <h4 class="font-semibold text-gray-800">Verificar que todo funciona</h4>
                        </div>
                        <ol class="text-gray-600 space-y-1 list-decimal pl-8">
                            <li>Guarda las credenciales en esta pantalla.</li>
                            <li>Usa el boton <strong>Probar conexion</strong> para verificar que el token sea valido.</li>
                            <li>Envia un mensaje de WhatsApp al numero configurado y verifica que el bot responda.</li>
                        </ol>
                    </div>

                </div>
            </div>
        </div>

        <!-- Menu Tab -->
        <div v-if="activeTab === 'menu'" class="bg-white rounded-xl shadow-sm border p-6 max-w-2xl">
            <h2 class="text-lg font-semibold mb-4">Fuente del Menu</h2>
            <form @submit.prevent="saveMenu" class="space-y-4">
                <div class="flex gap-3">
                    <label class="flex items-center gap-2">
                        <input v-model="menuForm.settings.menu_source" type="radio" value="internal" class="text-primary-600" />
                        <span class="text-sm">Menu interno (gestionado aqui)</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input v-model="menuForm.settings.menu_source" type="radio" value="external" class="text-primary-600" />
                        <span class="text-sm">API externa</span>
                    </label>
                </div>

                <div v-if="menuForm.settings.menu_source === 'external'" class="space-y-3 pl-6 border-l-2 border-primary-200">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-2">
                        <p class="text-sm text-blue-800 font-medium">Integracion con SelfOrder</p>
                        <p class="text-xs text-blue-600 mt-1">Por defecto, esta configuracion se conecta con la API de SelfOrder. Usa el modo "Headers" con tus credenciales de SelfOrder.</p>
                    </div>

                    <div>
                        <button type="button" @click="showApiDocs = !showApiDocs"
                                class="text-sm text-primary-600 hover:text-primary-800 font-medium flex items-center gap-1">
                            <svg class="w-4 h-4 transition-transform" :class="showApiDocs ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            Crear API personalizada
                        </button>
                        <div v-if="showApiDocs" class="mt-3 bg-gray-50 border rounded-lg p-4 text-xs space-y-3">
                            <p class="text-gray-700 font-medium">Si deseas conectar tu propio sistema, tu API debe cumplir con esta estructura minima:</p>
                            <pre class="bg-gray-800 text-green-400 p-3 rounded-lg overflow-x-auto whitespace-pre">{
  "categories": [
    {
      "id": "cat_1",
      "name": "Pizzas",
      "items": [
        {
          "id": "item_1",
          "name": "Pepperoni",
          "description": "Pizza de pepperoni",
          "price": 450,
          "is_available": true
        }
      ]
    }
  ]
}</pre>
                            <div class="space-y-2 text-gray-600">
                                <p><strong>Autenticacion:</strong> Bearer token o headers (X-Api-Key / X-Api-Secret)</p>
                                <p><strong>Metodo:</strong> GET</p>
                                <p><strong>Query param:</strong> El sistema agrega <code class="bg-gray-200 px-1 rounded">status=active</code> automaticamente</p>
                                <p><strong>Campos opcionales por item:</strong></p>
                                <ul class="list-disc pl-4 space-y-1">
                                    <li><code class="bg-gray-200 px-1 rounded">image_url</code> - URL de la imagen del producto</li>
                                    <li><code class="bg-gray-200 px-1 rounded">modifiers.variant_groups</code> - Variantes con precios (tamanos, sabores)</li>
                                    <li><code class="bg-gray-200 px-1 rounded">modifiers.optional_groups</code> - Extras opcionales</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">URL de la API</label>
                        <input v-model="menuForm.settings.menu_api_url" type="url" placeholder="https://..."
                               class="w-full px-3 py-2 border rounded-lg" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Modo de autenticacion</label>
                        <select v-model="menuForm.settings.menu_api_auth_mode"
                                class="w-full px-3 py-2 border rounded-lg">
                            <option value="bearer">Bearer Token</option>
                            <option value="headers">Headers (X-Api-Key + X-Api-Secret)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                        <input v-model="menuForm.settings.menu_api_key" type="text"
                               :placeholder="menuForm.settings.menu_api_auth_mode === 'headers' ? 'cat_xxx...' : 'Bearer token'"
                               class="w-full px-3 py-2 border rounded-lg" />
                    </div>
                    <div v-if="menuForm.settings.menu_api_auth_mode === 'headers'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">API Secret</label>
                        <input v-model="menuForm.settings.menu_api_secret" type="password" placeholder="sec_xxx..."
                               class="w-full px-3 py-2 border rounded-lg" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Intervalo de sync (min)</label>
                        <input v-model="menuForm.settings.menu_sync_interval" type="number" min="1"
                               class="w-32 px-3 py-2 border rounded-lg" />
                    </div>
                </div>

                <button type="submit" :disabled="menuForm.processing"
                        class="bg-primary-600 text-white px-6 py-2 rounded-lg hover:bg-primary-700 disabled:opacity-50">
                    {{ menuForm.processing ? 'Guardando...' : 'Guardar' }}
                </button>
            </form>
        </div>

        <!-- Pagos Tab -->
        <div v-if="activeTab === 'payment'" class="space-y-6 max-w-2xl">
            <form @submit.prevent="savePayment" class="space-y-6">
                <!-- Metodos de Pago Habilitados -->
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <h2 class="text-lg font-semibold mb-4">Metodos de Pago Habilitados</h2>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3">
                            <input type="checkbox" :checked="paymentForm.settings.payment.methods.includes('cash')"
                                   @change="togglePaymentMethod('cash')"
                                   class="rounded text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm font-medium text-gray-700">Efectivo</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input type="checkbox" :checked="paymentForm.settings.payment.methods.includes('transfer')"
                                   @change="togglePaymentMethod('transfer')"
                                   class="rounded text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm font-medium text-gray-700">Transferencia bancaria</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input type="checkbox" :checked="paymentForm.settings.payment.methods.includes('card_link')"
                                   @change="togglePaymentMethod('card_link')"
                                   class="rounded text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm font-medium text-gray-700">Pago con link (manual)</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input type="checkbox" :checked="paymentForm.settings.payment.methods.includes('cardnet')"
                                   @change="togglePaymentMethod('cardnet')"
                                   class="rounded text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm font-medium text-gray-700">Tarjeta de credito/debito (Cardnet)</span>
                        </label>

                        <!-- Custom Methods -->
                        <div v-if="Object.keys(paymentForm.settings.payment.custom_methods).length > 0"
                             class="border-t pt-3 mt-3">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Metodos personalizados</p>
                            <div v-for="(method, slug) in paymentForm.settings.payment.custom_methods" :key="slug" class="space-y-2 mb-3">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" :checked="paymentForm.settings.payment.methods.includes(slug)"
                                           @change="togglePaymentMethod(slug)"
                                           class="rounded text-primary-600 focus:ring-primary-500" />
                                    <span class="text-sm font-medium text-gray-700 flex-1">{{ method.name }}</span>
                                    <button type="button" @click="removeCustomMethod(slug)"
                                            class="text-red-400 hover:text-red-600 text-sm">
                                        Eliminar
                                    </button>
                                </div>
                                <div v-if="paymentForm.settings.payment.methods.includes(slug)" class="ml-8">
                                    <textarea v-model="method.instructions" rows="2"
                                              placeholder="Instrucciones para el cliente (opcional)"
                                              class="w-full px-3 py-2 border rounded-lg text-sm"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Add Custom Method -->
                        <div class="border-t pt-3 mt-3">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Agregar metodo</p>
                            <div class="flex gap-2">
                                <input v-model="newMethodName" type="text" placeholder="Nombre (ej: Yappy, PayPal)"
                                       maxlength="20"
                                       @keyup.enter="addCustomMethod"
                                       class="flex-1 px-3 py-2 border rounded-lg text-sm" />
                                <button type="button" @click="addCustomMethod"
                                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">
                                    Agregar
                                </button>
                            </div>
                            <p v-if="newMethodError" class="text-red-500 text-xs mt-1">{{ newMethodError }}</p>
                        </div>
                    </div>
                </div>

                <!-- Card Link Config -->
                <div v-if="paymentForm.settings.payment.methods.includes('card_link')"
                     class="bg-white rounded-xl shadow-sm border p-6">
                    <h2 class="text-lg font-semibold mb-4">Configuracion de Pago con Link</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pasarela de pago</label>
                            <select v-model="paymentForm.settings.payment.card_link.gateway"
                                    class="w-full px-3 py-2 border rounded-lg">
                                <option value="cardnet">Cardnet</option>
                                <option value="azul">Azul</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">URL del link de pago</label>
                            <input v-model="paymentForm.settings.payment.card_link.url" type="url"
                                   placeholder="https://checkout.cardnet.com.do/..."
                                   class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Instrucciones para el cliente</label>
                            <textarea v-model="paymentForm.settings.payment.card_link.instructions" rows="3"
                                      placeholder="Paga con tu tarjeta en el siguiente enlace"
                                      class="w-full px-3 py-2 border rounded-lg"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Cardnet API Config -->
                <div v-if="paymentForm.settings.payment.methods.includes('cardnet')"
                     class="bg-white rounded-xl shadow-sm border p-6">
                    <h2 class="text-lg font-semibold mb-4">Configuracion Cardnet</h2>
                    <p class="text-sm text-gray-500 mb-4">Credenciales de tu cuenta Cardnet para procesar pagos con tarjeta. Obtenlas desde el portal de comercios de Cardnet.</p>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Commerce ID</label>
                            <input v-model="paymentForm.settings.payment.cardnet.commerce_id" type="text"
                                   placeholder="Tu ID de comercio"
                                   class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Public Key</label>
                            <input v-model="paymentForm.settings.payment.cardnet.public_key" type="text"
                                   placeholder="pk_..."
                                   class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Private Key</label>
                            <input v-model="paymentForm.settings.payment.cardnet.private_key" type="password"
                                   placeholder="sk_..."
                                   class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                    </div>
                </div>

                <!-- Transfer Info -->
                <div v-if="paymentForm.settings.payment.methods.includes('transfer')"
                     class="bg-white rounded-xl shadow-sm border p-6">
                    <h2 class="text-lg font-semibold mb-4">Datos de Transferencia</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Banco</label>
                            <input v-model="paymentForm.settings.payment.transfer_info.bank" type="text"
                                   placeholder="Banco Popular"
                                   class="w-full px-3 py-2 border rounded-lg" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de cuenta</label>
                                <select v-model="paymentForm.settings.payment.transfer_info.account_type"
                                        class="w-full px-3 py-2 border rounded-lg">
                                    <option value="Ahorro">Ahorro</option>
                                    <option value="Corriente">Corriente</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Numero de cuenta</label>
                                <input v-model="paymentForm.settings.payment.transfer_info.account_number" type="text"
                                       placeholder="123456789"
                                       class="w-full px-3 py-2 border rounded-lg" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Titular de la cuenta</label>
                                <input v-model="paymentForm.settings.payment.transfer_info.holder_name" type="text"
                                       placeholder="Juan Perez"
                                       class="w-full px-3 py-2 border rounded-lg" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">RNC / Cedula</label>
                                <input v-model="paymentForm.settings.payment.transfer_info.rnc" type="text"
                                       placeholder="123-456789-0"
                                       class="w-full px-3 py-2 border rounded-lg" />
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" :disabled="paymentForm.processing"
                        class="bg-primary-600 text-white px-6 py-2 rounded-lg hover:bg-primary-700 disabled:opacity-50">
                    {{ paymentForm.processing ? 'Guardando...' : 'Guardar configuracion de pagos' }}
                </button>
                <p v-if="paymentForm.recentlySuccessful" class="text-green-600 text-sm">Guardado</p>
            </form>
        </div>

        <!-- Drivers Tab -->
        <div v-if="activeTab === 'drivers'" class="bg-white rounded-xl shadow-sm border p-6 max-w-2xl">
            <h2 class="text-lg font-semibold mb-4">Canal de Comunicacion con Mensajeros</h2>
            <p class="text-sm text-gray-500 mb-6">Elige como reciben las asignaciones de entrega tus mensajeros.</p>
            <form @submit.prevent="saveDriverMode" class="space-y-6">
                <div class="space-y-3">
                    <label class="flex items-start gap-3 p-4 rounded-lg border-2 cursor-pointer transition-colors"
                           :class="driverForm.settings.driver_mode === 'whatsapp' ? 'border-primary-500 bg-primary-50' : 'border-gray-200 hover:border-gray-300'">
                        <input v-model="driverForm.settings.driver_mode" type="radio" value="whatsapp" class="mt-1 text-primary-600" />
                        <div>
                            <span class="text-sm font-medium text-gray-900">WhatsApp</span>
                            <p class="text-xs text-gray-500 mt-1">Los mensajeros reciben notificaciones por WhatsApp con botones interactivos para gestionar entregas.</p>
                        </div>
                    </label>
                    <!-- App de Delivery — locked if addon not purchased -->
                    <div v-if="!hasDeliveryAppAddon"
                         class="flex items-start gap-3 p-4 rounded-lg border-2 border-gray-200 bg-gray-50 opacity-70 cursor-not-allowed">
                        <input type="radio" disabled class="mt-1 text-gray-400 cursor-not-allowed" />
                        <div class="flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-medium text-gray-500">App de Delivery</span>
                                <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full font-medium">Addon requerido</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Los mensajeros usan la app movil dedicada. Reciben push notifications y gestionan entregas desde la app.</p>
                            <a href="/billing" class="inline-flex items-center gap-1 mt-2 text-xs font-semibold text-primary-600 hover:text-primary-800 underline underline-offset-2">
                                Actualizar plan para activar esta funcion
                            </a>
                        </div>
                    </div>
                    <label v-else
                           class="flex items-start gap-3 p-4 rounded-lg border-2 cursor-pointer transition-colors"
                           :class="driverForm.settings.driver_mode === 'app' ? 'border-primary-500 bg-primary-50' : 'border-gray-200 hover:border-gray-300'">
                        <input v-model="driverForm.settings.driver_mode" type="radio" value="app" class="mt-1 text-primary-600" />
                        <div>
                            <span class="text-sm font-medium text-gray-900">App de Delivery</span>
                            <p class="text-xs text-gray-500 mt-1">Los mensajeros usan la app movil dedicada. Reciben push notifications y gestionan entregas desde la app.</p>
                        </div>
                    </label>
                </div>

                <div v-if="driverForm.settings.driver_mode === 'app'" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-blue-800 mb-2">Como vincular la app</h3>
                    <ol class="text-sm text-blue-700 space-y-1 list-decimal pl-4">
                        <li>Instala la app <strong>WaOrder Delivery</strong> en el celular del mensajero</li>
                        <li>Ve a <strong>Mensajeros</strong> en el panel y haz clic en el icono de QR</li>
                        <li>Escanea el QR desde la app para vincularla automaticamente</li>
                    </ol>
                    <p class="text-xs text-blue-600 mt-3">Si un mensajero no tiene la app vinculada, recibira las notificaciones por WhatsApp como respaldo.</p>
                </div>

                <button type="submit" :disabled="driverForm.processing"
                        class="bg-primary-600 text-white px-6 py-2 rounded-lg hover:bg-primary-700 disabled:opacity-50">
                    {{ driverForm.processing ? 'Guardando...' : 'Guardar' }}
                </button>
                <p v-if="driverForm.recentlySuccessful" class="text-green-600 text-sm">Guardado</p>
            </form>
        </div>

        <!-- Survey Tab -->
        <div v-if="activeTab === 'survey'" class="bg-white rounded-xl shadow-sm border p-6 max-w-2xl">
            <h2 class="text-lg font-semibold mb-4">Encuesta Post-Entrega</h2>
            <p class="text-sm text-gray-500 mb-6">Se envia automaticamente al cliente despues de cada entrega</p>
            <form @submit.prevent="saveSurvey" class="space-y-6">
                <div class="flex items-center gap-3">
                    <input v-model="surveyForm.settings.survey.enabled" type="checkbox" id="survey_enabled"
                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                    <label for="survey_enabled" class="text-sm font-medium text-gray-700">Encuesta habilitada</label>
                </div>

                <div v-if="surveyForm.settings.survey.enabled" class="space-y-5">
                    <!-- Questions editor -->
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-3">Preguntas</p>
                        <div class="space-y-3">
                            <div v-for="(question, qIdx) in surveyForm.settings.survey.questions" :key="question.key"
                                 class="border rounded-lg p-4"
                                 :class="question.enabled ? 'bg-white' : 'bg-gray-50 opacity-60'">
                                <div class="flex items-start gap-3">
                                    <!-- Enable toggle -->
                                    <input v-model="question.enabled" type="checkbox"
                                           class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500 shrink-0" />
                                    <div class="flex-1 min-w-0">
                                        <!-- Type badge -->
                                        <span class="inline-block text-xs font-medium px-2 py-0.5 rounded mb-2"
                                              :class="{
                                                  'bg-yellow-100 text-yellow-700': question.type === 'rating',
                                                  'bg-blue-100 text-blue-700': question.type === 'buttons',
                                                  'bg-gray-100 text-gray-600': question.type === 'text',
                                              }">
                                            {{ question.type === 'rating' ? '⭐ Calificación' : question.type === 'buttons' ? '🔘 Botones' : '✏️ Texto libre' }}
                                        </span>
                                        <!-- Label -->
                                        <input v-model="question.label" type="text"
                                               class="w-full px-3 py-1.5 border rounded-lg text-sm focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                               placeholder="Texto de la pregunta..." />
                                        <!-- Options for rating / buttons -->
                                        <div v-if="question.type !== 'text'" class="mt-3">
                                            <p class="text-xs text-gray-500 mb-2">Opciones (máx. 3 — límite de WhatsApp):</p>
                                            <div class="space-y-2">
                                                <div v-for="(opt, optIdx) in question.options" :key="optIdx"
                                                     class="flex items-center gap-2">
                                                    <input v-model="opt.title" type="text"
                                                           class="flex-1 px-2 py-1 border rounded text-sm focus:ring-1 focus:ring-primary-500"
                                                           placeholder="Texto del botón..." />
                                                    <button v-if="question.type === 'buttons' && question.options.length > 1"
                                                            type="button" @click="removeSurveyOption(qIdx, optIdx)"
                                                            class="text-red-400 hover:text-red-600 text-sm leading-none px-1 shrink-0">✕</button>
                                                </div>
                                            </div>
                                            <button v-if="question.type === 'buttons' && question.options.length < 3"
                                                    type="button" @click="addSurveyOption(qIdx)"
                                                    class="mt-2 text-xs text-primary-600 hover:text-primary-700 flex items-center gap-1">
                                                + Agregar opción
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Reorder arrows -->
                                    <div class="flex flex-col gap-1 shrink-0">
                                        <button type="button" @click="moveSurveyQuestion(qIdx, -1)" :disabled="qIdx === 0"
                                                class="text-gray-400 hover:text-gray-600 disabled:opacity-25 text-xs leading-none">▲</button>
                                        <button type="button" @click="moveSurveyQuestion(qIdx, 1)"
                                                :disabled="qIdx === surveyForm.settings.survey.questions.length - 1"
                                                class="text-gray-400 hover:text-gray-600 disabled:opacity-25 text-xs leading-none">▼</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Thank you message -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje de agradecimiento</label>
                        <textarea v-model="surveyForm.settings.survey.thank_you_message" rows="2"
                                  class="w-full px-3 py-2 border rounded-lg text-sm" />
                        <p class="text-xs text-gray-400 mt-1">Se envía al cliente al finalizar la encuesta.</p>
                    </div>
                </div>

                <button type="submit" :disabled="surveyForm.processing"
                        class="bg-primary-600 text-white px-6 py-2 rounded-lg hover:bg-primary-700 disabled:opacity-50">
                    {{ surveyForm.processing ? 'Guardando...' : 'Guardar' }}
                </button>
                <p v-if="surveyForm.recentlySuccessful" class="text-green-600 text-sm">Guardado</p>
            </form>
        </div>

        <!-- Notifications Tab -->
        <div v-if="activeTab === 'notifications'" class="bg-white rounded-xl shadow-sm border p-6 max-w-2xl">
            <h2 class="text-lg font-semibold mb-4">Notificaciones</h2>
            <p class="text-sm text-gray-500 mb-6">Configura alertas sonoras cuando llegan nuevas ordenes</p>
            <form @submit.prevent="saveNotifications" class="space-y-6">
                <AppSwitch
                    v-model="notificationForm.settings.notifications.sound_enabled"
                    label="Sonido de nueva orden"
                    description="Reproduce un sonido de alerta cuando llega una nueva orden por WhatsApp"
                />

                <div v-if="notificationForm.settings.notifications.sound_enabled" class="space-y-6 pl-6 border-l-2 border-primary-200">
                    <!-- Polling interval -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Intervalo de verificacion (segundos)
                        </label>
                        <input
                            v-model.number="notificationForm.settings.notifications.polling_interval"
                            type="number" min="10" max="120" step="5"
                            class="w-32 px-3 py-2 border rounded-lg text-sm"
                        />
                        <p class="text-xs text-gray-500 mt-1">
                            Cada cuantos segundos se verifica si hay ordenes nuevas (10-120s)
                        </p>
                    </div>

                    <!-- Custom sound upload -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Sonido personalizado
                        </label>
                        <div v-if="hasCustomSound" class="flex items-center gap-3 mb-3 bg-gray-50 rounded-lg p-3">
                            <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-sm text-gray-700">Sonido personalizado cargado</span>
                            <button type="button" @click="deleteSound"
                                    class="ml-auto text-xs text-red-600 hover:text-red-700 font-medium">
                                Eliminar
                            </button>
                        </div>
                        <label class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg cursor-pointer transition-colors">
                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span class="text-sm text-gray-700">
                                {{ soundUploading ? 'Subiendo...' : (hasCustomSound ? 'Cambiar archivo' : 'Subir archivo MP3 o WAV') }}
                            </span>
                            <input type="file" accept=".mp3,.wav,audio/mpeg,audio/wav" class="hidden" @change="uploadSound" :disabled="soundUploading" />
                        </label>
                        <p class="text-xs text-gray-500 mt-1">
                            Formato: MP3 o WAV. Tamano maximo: 2MB. Si no se carga un archivo, se usara el sonido por defecto.
                        </p>
                    </div>

                    <!-- Test sound -->
                    <button type="button" @click="testSound"
                            class="inline-flex items-center gap-2 text-sm text-primary-600 hover:text-primary-700 font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                        </svg>
                        Probar sonido
                    </button>
                </div>

                <div>
                    <button type="submit" :disabled="notificationForm.processing"
                            class="bg-primary-600 text-white px-6 py-2 rounded-lg hover:bg-primary-700 disabled:opacity-50">
                        {{ notificationForm.processing ? 'Guardando...' : 'Guardar' }}
                    </button>
                    <p v-if="notificationForm.recentlySuccessful" class="text-green-600 text-sm mt-2">Guardado</p>
                </div>
            </form>
        </div>

        <!-- ── App Móvil Tab ─────────────────────────────────────────────── -->
        <div v-if="activeTab === 'app_movil'" class="space-y-6 max-w-4xl">

            <!-- Header -->
            <div class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-xl p-6 text-white flex items-center gap-5">
                <div class="w-14 h-14 rounded-2xl overflow-hidden bg-orange-500 flex items-center justify-center shrink-0 shadow-lg">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-semibold">WaOrder Delivery App</h2>
                    <p class="text-sm text-gray-400 mt-0.5">Compila e instala la app nativa en los dispositivos de tus mensajeros</p>
                </div>
            </div>

            <!-- EAS Configuration -->
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-1 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                    Credenciales de Expo (EAS)
                </h3>
                <p class="text-xs text-gray-500 mb-4">
                    Obtén tu token en
                    <a href="https://expo.dev/accounts/[account]/settings/access-tokens" target="_blank" class="text-primary-600 underline">expo.dev → Access Tokens</a>.
                    Necesitas una cuenta gratuita de Expo.
                </p>
                <form @submit.prevent="saveEas" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expo Access Token</label>
                        <div class="relative">
                            <input
                                v-model="easForm.settings.eas.token"
                                :type="easShowToken ? 'text' : 'password'"
                                :placeholder="easHasToken ? '••••••••  (token guardado)' : 'expo_xxx...'"
                                class="w-full px-3 py-2 border rounded-lg pr-10 font-mono text-sm"
                                autocomplete="off"
                            />
                            <button type="button" @click="easShowToken = !easShowToken"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path v-if="!easShowToken" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="easForm.errors['settings.eas.token']" class="text-xs text-red-500 mt-1">{{ easForm.errors['settings.eas.token'] }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ruta del proyecto móvil</label>
                        <input
                            v-model="easForm.settings.eas.mobile_path"
                            type="text"
                            placeholder="/ruta/al/proyecto/mobile  (vacío = carpeta mobile/ del proyecto)"
                            class="w-full px-3 py-2 border rounded-lg font-mono text-sm"
                        />
                        <p class="text-xs text-gray-400 mt-1">Dejar vacío usa la carpeta <code class="bg-gray-100 px-1 rounded">mobile/</code> dentro del proyecto.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="easForm.processing"
                                class="bg-primary-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                            {{ easForm.processing ? 'Guardando...' : 'Guardar credenciales' }}
                        </button>
                        <span v-if="easForm.recentlySuccessful" class="text-green-600 text-sm flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Guardado
                        </span>
                    </div>
                </form>
            </div>

            <!-- Build Actions -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <!-- Android APK -->
                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center">
                            <span class="text-xl">🤖</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Android APK</p>
                            <p class="text-xs text-gray-500">Instalable directo, sin Play Store</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <button @click="triggerBuild('android', 'preview')"
                                :disabled="triggering.android || !easHasToken"
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50 transition-colors">
                            <svg v-if="triggering.android" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            {{ triggering.android ? 'Iniciando build...' : 'Compilar APK (preview)' }}
                        </button>
                        <button @click="triggerBuild('android', 'production')"
                                :disabled="triggering.android || !easHasToken"
                                class="w-full px-4 py-2 border-2 border-gray-300 text-gray-700 font-medium rounded-lg text-sm hover:bg-gray-100 hover:border-gray-400 disabled:opacity-40 transition-colors">
                            Compilar AAB (Play Store)
                        </button>
                    </div>

                    <div v-if="!easHasToken" class="mt-2 text-xs text-amber-600 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Guarda tu EXPO_TOKEN primero
                    </div>

                    <div v-if="triggerMsg.android" class="mt-3 p-3 rounded-lg text-xs"
                         :class="triggerMsg.android.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                        <p>{{ triggerMsg.android.text }}</p>
                        <a v-if="triggerMsg.android.url" :href="triggerMsg.android.url" target="_blank"
                           class="underline font-medium mt-1 inline-block">Ver en Expo Dashboard →</a>
                    </div>
                </div>

                <!-- iOS IPA -->
                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <span class="text-xl">🍎</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">iOS IPA</p>
                            <p class="text-xs text-gray-500">Requiere Apple Developer ($99/año)</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <button @click="triggerBuild('ios', 'preview')"
                                :disabled="triggering.ios || !easHasToken"
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors">
                            <svg v-if="triggering.ios" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            {{ triggering.ios ? 'Iniciando build...' : 'Compilar IPA (preview)' }}
                        </button>
                        <button @click="triggerBuild('ios', 'production')"
                                :disabled="triggering.ios || !easHasToken"
                                class="w-full px-4 py-2 border-2 border-gray-300 text-gray-700 font-medium rounded-lg text-sm hover:bg-gray-100 hover:border-gray-400 disabled:opacity-40 transition-colors">
                            Compilar IPA (App Store)
                        </button>
                    </div>

                    <div v-if="!easHasToken" class="mt-2 text-xs text-amber-600 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Guarda tu EXPO_TOKEN primero
                    </div>

                    <div v-if="triggerMsg.ios" class="mt-3 p-3 rounded-lg text-xs"
                         :class="triggerMsg.ios.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                        <p>{{ triggerMsg.ios.text }}</p>
                        <a v-if="triggerMsg.ios.url" :href="triggerMsg.ios.url" target="_blank"
                           class="underline font-medium mt-1 inline-block">Ver en Expo Dashboard →</a>
                    </div>
                </div>
            </div>

            <!-- Build History -->
            <div class="bg-white rounded-xl shadow-sm border p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-gray-900">Historial de Builds</h3>
                    <div class="flex items-center gap-2">
                        <!-- Platform filter -->
                        <div class="flex border rounded-lg overflow-hidden text-xs font-medium">
                            <button v-for="p in [['all','Todos'],['android','Android'],['ios','iOS']]" :key="p[0]"
                                    @click="buildsPlatform = p[0]"
                                    class="px-3 py-1.5 transition-colors"
                                    :class="buildsPlatform === p[0] ? 'bg-gray-900 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'">
                                {{ p[1] }}
                            </button>
                        </div>
                        <button @click="fetchBuilds" :disabled="buildsLoading"
                                class="p-1.5 rounded-lg border text-gray-500 hover:bg-gray-50 disabled:opacity-50 transition-colors">
                            <svg class="w-4 h-4" :class="buildsLoading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Error state -->
                <div v-if="buildsError" class="flex items-start gap-3 p-4 bg-red-50 rounded-lg text-sm text-red-700 mb-4">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ buildsError }}
                </div>

                <!-- Loading skeleton -->
                <div v-if="buildsLoading && builds.length === 0" class="space-y-3">
                    <div v-for="i in 3" :key="i" class="h-16 bg-gray-100 rounded-lg animate-pulse" />
                </div>

                <!-- Empty state -->
                <div v-else-if="!buildsLoading && builds.length === 0 && !buildsError"
                     class="text-center py-12 text-gray-400">
                    <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <p class="text-sm">No hay builds todavía.</p>
                    <p class="text-xs mt-1">Inicia tu primer build con los botones de arriba.</p>
                </div>

                <!-- Build list -->
                <div v-else class="divide-y">
                    <div v-for="build in builds" :key="build.id"
                         class="flex items-center gap-4 py-3 first:pt-0 last:pb-0">
                        <!-- Platform icon -->
                        <span class="text-xl w-8 text-center">{{ platformIcon(build.platform) }}</span>

                        <!-- Build info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-900">
                                    {{ build.platform === 'ANDROID' ? 'Android' : 'iOS' }}
                                    <span class="font-normal text-gray-500">v{{ build.appVersion || '?' }}</span>
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                      :class="buildStatusLabel(build.status).cls">
                                    {{ buildStatusLabel(build.status).label }}
                                </span>
                                <span v-if="build.buildProfile" class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                                    {{ build.buildProfile }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 mt-0.5">{{ formatBuildDate(build.createdAt) }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 shrink-0">
                            <!-- Download button (if finished) -->
                            <a v-if="build.status === 'FINISHED' && build.artifacts?.buildUrl"
                               :href="build.artifacts.buildUrl"
                               target="_blank"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-900 text-white rounded-lg text-xs font-medium hover:bg-gray-700 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Descargar
                            </a>

                            <!-- In-progress spinner -->
                            <span v-else-if="build.status === 'IN_PROGRESS' || build.status === 'NEW'"
                                  class="flex items-center gap-1.5 text-xs text-amber-600 px-3 py-1.5">
                                <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                En proceso...
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Tip -->
                <div class="mt-6 flex items-start gap-2 text-xs text-gray-400 bg-gray-50 rounded-lg p-3">
                    <svg class="w-4 h-4 mt-0.5 shrink-0 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>
                        Los builds se procesan en los servidores de Expo y pueden tardar 10–25 minutos.
                        El APK de Android se puede compartir directamente por WhatsApp al mensajero.
                        iOS requiere distribución via TestFlight.
                    </span>
                </div>
            </div>
        </div>

        <!-- ── Marca Tab ─────────────────────────────────────────────────── -->
        <div v-if="activeTab === 'marca'" class="flex gap-6 items-start flex-wrap">

            <!-- Left: controls -->
            <div class="bg-white rounded-xl shadow-sm border p-6 flex-1 min-w-[320px] max-w-lg">
                <h2 class="text-lg font-semibold mb-5">Identidad del menu</h2>

                <!-- Logo upload -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Logo</label>
                    <div v-if="currentLogoUrl || previewLogo" class="flex items-center gap-3 mb-3 bg-gray-50 rounded-lg p-3">
                        <img :src="currentLogoUrl || previewLogo" alt="logo" class="h-10 object-contain rounded" />
                        <span class="text-sm text-gray-600 flex-1">Logo actual</span>
                        <button type="button" @click="deleteLogo" class="text-xs text-red-600 hover:text-red-700 font-medium">Eliminar</button>
                    </div>
                    <label class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg cursor-pointer transition-colors">
                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span class="text-sm text-gray-700">{{ logoUploading ? 'Subiendo...' : (currentLogoUrl || previewLogo ? 'Cambiar logo' : 'Subir logo') }}</span>
                        <input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden" @change="uploadLogo" :disabled="logoUploading" />
                    </label>
                    <p class="text-xs text-gray-400 mt-1">PNG, JPG, WebP o SVG. Max 2MB. Recomendado: fondo transparente.</p>
                </div>

                <form @submit.prevent="saveMarca" class="space-y-5">
                    <!-- Primary color -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Color principal</label>
                        <div class="flex items-center gap-3">
                            <input type="color" v-model="marcaForm.settings.menu_theme.primary_color"
                                   class="w-12 h-10 rounded-lg border border-gray-200 cursor-pointer p-0.5 bg-white" />
                            <input type="text" v-model="marcaForm.settings.menu_theme.primary_color"
                                   placeholder="#4f46e5"
                                   class="flex-1 px-3 py-2 border rounded-lg font-mono text-sm uppercase"
                                   pattern="^#[0-9a-fA-F]{6}$" />
                        </div>
                        <!-- Palette preview -->
                        <div class="flex gap-1 mt-3" :style="previewThemeVars">
                            <div class="h-6 flex-1 rounded-l-md bg-[--color-primary-50] border"></div>
                            <div class="h-6 flex-1 bg-[--color-primary-100]"></div>
                            <div class="h-6 flex-1 bg-[--color-primary-200]"></div>
                            <div class="h-6 flex-1 bg-[--color-primary-500]"></div>
                            <div class="h-6 flex-1 bg-[--color-primary-600]"></div>
                            <div class="h-6 flex-1 rounded-r-md bg-[--color-primary-700]"></div>
                        </div>
                    </div>

                    <!-- Show restaurant name -->
                    <div class="flex items-center justify-between py-3 border-t">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Mostrar nombre del restaurante</p>
                            <p class="text-xs text-gray-400">Aparece debajo del logo en el menu</p>
                        </div>
                        <AppSwitch v-model="marcaForm.settings.menu_theme.show_restaurant_name" />
                    </div>

                    <button type="submit" :disabled="marcaForm.processing"
                            class="w-full bg-primary-600 text-white py-2 rounded-lg hover:bg-primary-700 disabled:opacity-50 font-medium">
                        {{ marcaForm.processing ? 'Guardando...' : 'Guardar cambios' }}
                    </button>
                    <p v-if="marcaForm.recentlySuccessful" class="text-green-600 text-sm text-center">Guardado correctamente</p>
                </form>
            </div>

            <!-- Right: Phone preview -->
            <div class="flex-shrink-0">
                <p class="text-sm font-medium text-gray-500 mb-3 text-center">Vista previa del cliente</p>

                <!-- Phone frame -->
                <div class="relative mx-auto" style="width:260px">
                    <!-- Phone shell -->
                    <div class="relative bg-gray-900 rounded-[36px] p-[10px] shadow-2xl">
                        <!-- Screen -->
                        <div class="bg-gray-50 rounded-[28px] overflow-hidden" style="height:520px">

                            <!-- Status bar -->
                            <div class="bg-gray-900 flex justify-between items-center px-5 pt-2 pb-1">
                                <span class="text-white text-[10px] font-semibold">9:41</span>
                                <div class="flex gap-1 items-center">
                                    <div class="flex gap-0.5 items-end h-3">
                                        <div class="w-[3px] bg-white rounded-sm h-1"></div>
                                        <div class="w-[3px] bg-white rounded-sm h-1.5"></div>
                                        <div class="w-[3px] bg-white rounded-sm h-2"></div>
                                        <div class="w-[3px] bg-white rounded-sm h-3"></div>
                                    </div>
                                    <svg class="w-3 h-3 text-white ml-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M17.778 8.222c-4.296-4.296-11.26-4.296-15.556 0A1 1 0 01.808 6.808c5.076-5.077 13.308-5.077 18.384 0a1 1 0 01-1.414 1.414zM14.95 11.05a7 7 0 00-9.9 0 1 1 0 01-1.414-1.414 9 9 0 0112.728 0 1 1 0 01-1.414 1.414zM12.12 13.88a3 3 0 00-4.242 0 1 1 0 01-1.414-1.414 5 5 0 017.072 0 1 1 0 01-1.416 1.414zM10 17a1 1 0 110-2 1 1 0 010 2z" clip-rule="evenodd"/></svg>
                                    <svg class="w-4 h-2.5 text-white ml-1" viewBox="0 0 20 10" fill="none"><rect x="0" y="1" width="16" height="8" rx="2" stroke="white" stroke-width="1.5"/><rect x="1.5" y="2.5" width="11" height="5" rx="1" fill="white"/><path d="M17 3.5v3a1.5 1.5 0 000-3z" fill="white"/></svg>
                                </div>
                            </div>

                            <!-- App content -->
                            <div class="overflow-auto h-full" :style="previewThemeVars">
                                <!-- Header -->
                                <div class="bg-white border-b border-gray-100 px-4 py-2.5 sticky top-0 z-10">
                                    <div class="flex flex-col items-center">
                                        <img v-if="previewLogo" :src="previewLogo" alt="logo" class="h-6 object-contain mb-0.5" />
                                        <p v-if="!previewLogo || marcaForm.settings.menu_theme.show_restaurant_name"
                                           class="text-xs font-semibold text-[--color-primary-600]">
                                            {{ previewName }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Categories -->
                                <div class="p-3 space-y-2">
                                    <p class="text-xs font-bold text-gray-800 px-1">Nuestro Menu</p>
                                    <div v-for="cat in ['Hamburguesas', 'Pizzas', 'Bebidas', 'Postres']" :key="cat"
                                         class="bg-white rounded-xl border border-gray-100 px-3 py-2.5 flex items-center justify-between shadow-sm">
                                        <div>
                                            <p class="text-xs font-semibold text-gray-900">{{ cat }}</p>
                                            <p class="text-[10px] text-gray-400 mt-0.5">4 items</p>
                                        </div>
                                        <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                </div>

                                <!-- Sample item detail (bottom) -->
                                <div class="mx-3 mt-1 rounded-xl overflow-hidden border border-gray-100 bg-white shadow-sm">
                                    <div class="h-14 flex items-center justify-center" :style="{ background: `hsl(from ${marcaForm.settings.menu_theme.primary_color} h s 95%)` }">
                                        <svg class="w-8 h-8 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14"/></svg>
                                    </div>
                                    <div class="px-3 py-2">
                                        <p class="text-xs font-bold text-gray-900">Hamburguesa Clasica</p>
                                        <p class="text-[10px] text-gray-400">Con papas fritas</p>
                                        <div class="flex justify-between items-center mt-1.5">
                                            <span class="text-xs font-bold text-[--color-primary-600]">RD$350</span>
                                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs font-bold"
                                                 :style="{ background: marcaForm.settings.menu_theme.primary_color }">+</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Home indicator -->
                        <div class="flex justify-center mt-2">
                            <div class="w-20 h-1 bg-white opacity-50 rounded-full"></div>
                        </div>
                    </div>

                    <!-- Side buttons -->
                    <div class="absolute left-[-3px] top-20 w-[3px] h-8 bg-gray-700 rounded-l-sm"></div>
                    <div class="absolute left-[-3px] top-32 w-[3px] h-12 bg-gray-700 rounded-l-sm"></div>
                    <div class="absolute left-[-3px] top-48 w-[3px] h-12 bg-gray-700 rounded-l-sm"></div>
                    <div class="absolute right-[-3px] top-28 w-[3px] h-16 bg-gray-700 rounded-r-sm"></div>
                </div>

                <p class="text-xs text-gray-400 text-center mt-3">Preview en tiempo real</p>
            </div>
        </div>


    </div>
</template>
