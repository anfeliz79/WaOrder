<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import {
    CheckCircle, XCircle, AlertCircle, RefreshCw, Database,
    Layers, RotateCcw, HardDrive, Terminal,
    Server, AlertTriangle, Loader2, Info, ChevronDown, ChevronUp,
    Rocket, Shield, ExternalLink,
} from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    status: Object,
});

const status = ref({ ...props.status });
const refreshing = ref(false);
const logs = ref([]);
const logsLoading = ref(false);
const logsVisible = ref(false);
const expandedHelp = ref(null); // which checklist item has help expanded

const actions = ref({
    migrate:       { loading: false, result: null },
    clearCache:    { loading: false, result: null },
    rebuildCache:  { loading: false, result: null },
    restartWorkers:{ loading: false, result: null },
    flushFailed:   { loading: false, result: null },
    storageLink:   { loading: false, result: null },
});

let statusInterval = null;

onMounted(() => {
    statusInterval = setInterval(refreshStatus, 30000);
});

onUnmounted(() => clearInterval(statusInterval));

// Checklist progress
const checklistDone = computed(() => {
    if (!status.value.checklist) return 0;
    return status.value.checklist.filter(c => c.ok === true).length;
});
const checklistTotal = computed(() => {
    if (!status.value.checklist) return 0;
    return status.value.checklist.filter(c => c.ok !== null).length;
});

async function refreshStatus() {
    refreshing.value = true;
    try {
        const { data } = await axios.get('/system/status');
        status.value = data;
    } finally {
        refreshing.value = false;
    }
}

async function runAction(key, endpoint) {
    actions.value[key].loading = true;
    actions.value[key].result = null;
    try {
        const { data } = await axios.post(endpoint);
        actions.value[key].result = data;
        if (data.success) await refreshStatus();
    } catch (e) {
        actions.value[key].result = {
            success: false,
            output: e.response?.data?.output || e.message,
        };
    } finally {
        actions.value[key].loading = false;
    }
}

async function loadLogs() {
    logsLoading.value = true;
    logsVisible.value = true;
    try {
        const { data } = await axios.get('/system/logs');
        logs.value = data.lines;
    } finally {
        logsLoading.value = false;
    }
}

function logLineClass(line) {
    if (line.includes('.ERROR') || line.includes('ERROR:')) return 'text-red-400';
    if (line.includes('.WARNING')) return 'text-yellow-400';
    if (line.includes('.INFO')) return 'text-sky-400';
    return 'text-slate-400';
}

function toggleHelp(key) {
    expandedHelp.value = expandedHelp.value === key ? null : key;
}
</script>

<template>
    <AdminLayout>
        <div class="space-y-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Sistema</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Estado del servidor, deploy a producción y mantenimiento</p>
                </div>
                <button
                    @click="refreshStatus"
                    :disabled="refreshing"
                    class="flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors disabled:opacity-50"
                >
                    <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': refreshing }" />
                    Actualizar
                </button>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- CHECKLIST DE PRODUCCIÓN                                        -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-primary-500 to-accent-end flex items-center justify-center">
                            <Rocket class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Checklist para producción</h3>
                            <p class="text-xs text-gray-400">Completa cada paso antes de hacer deploy. Toca <span class="text-primary-500">"¿Qué es esto?"</span> para ver la explicación.</p>
                        </div>
                    </div>
                    <div v-if="checklistTotal > 0" class="text-right">
                        <p class="text-sm font-semibold" :class="checklistDone === checklistTotal ? 'text-emerald-600' : 'text-amber-600'">
                            {{ checklistDone }} / {{ checklistTotal }}
                        </p>
                        <div class="w-24 h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500"
                                 :class="checklistDone === checklistTotal ? 'bg-emerald-500' : 'bg-amber-400'"
                                 :style="{ width: `${(checklistDone / checklistTotal) * 100}%` }"></div>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-gray-50">
                    <div v-for="item in (status.checklist || [])" :key="item.key" class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <!-- Status icon -->
                            <div class="shrink-0 w-6 h-6 flex items-center justify-center">
                                <CheckCircle v-if="item.ok === true" class="w-5 h-5 text-emerald-500" />
                                <XCircle v-else-if="item.ok === false" class="w-5 h-5 text-red-500" />
                                <Info v-else class="w-5 h-5 text-blue-400" />
                            </div>

                            <!-- Label + value -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-medium" :class="item.ok === false ? 'text-red-700' : 'text-gray-800'">
                                        {{ item.label }}
                                    </p>
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 font-mono">
                                        {{ item.value }}
                                    </span>
                                </div>

                                <!-- Fix instruction (only when failing) -->
                                <p v-if="item.ok === false" class="text-xs text-red-500 mt-0.5 flex items-center gap-1">
                                    <Shield class="w-3 h-3 shrink-0" />
                                    {{ item.fix }}
                                </p>
                            </div>

                            <!-- Help toggle -->
                            <button
                                @click="toggleHelp(item.key)"
                                class="shrink-0 text-xs px-2 py-1 rounded-lg transition-colors"
                                :class="expandedHelp === item.key ? 'bg-primary-50 text-primary-600' : 'text-gray-400 hover:text-primary-500 hover:bg-gray-50'"
                            >
                                {{ expandedHelp === item.key ? 'Cerrar' : '¿Qué es esto?' }}
                            </button>
                        </div>

                        <!-- Expanded help -->
                        <Transition
                            enter-active-class="transition duration-200 ease-out"
                            enter-from-class="opacity-0 max-h-0"
                            enter-to-class="opacity-100 max-h-40"
                            leave-active-class="transition duration-150 ease-in"
                            leave-from-class="opacity-100 max-h-40"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <div v-if="expandedHelp === item.key"
                                 class="mt-2 ml-9 p-3 rounded-lg bg-blue-50 border border-blue-100 text-sm text-blue-800 leading-relaxed overflow-hidden">
                                {{ item.help }}
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- STATUS CARDS                                                   -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">PHP</p>
                    <p class="mt-1 text-base font-semibold text-gray-900">{{ status.php_version }}</p>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Laravel</p>
                    <p class="mt-1 text-base font-semibold text-gray-900">{{ status.laravel_version }}</p>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4"
                     :class="{ 'border-red-200 bg-red-50': !status.database?.ok }">
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Base de datos</p>
                        <CheckCircle v-if="status.database?.ok" class="w-4 h-4 text-emerald-500" />
                        <XCircle v-else class="w-4 h-4 text-red-500" />
                    </div>
                    <p class="mt-1 text-base font-semibold text-gray-900 capitalize">{{ status.database?.driver }}</p>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Redis</p>
                        <CheckCircle v-if="status.redis?.ok === true" class="w-4 h-4 text-emerald-500" />
                        <XCircle v-else-if="status.redis?.ok === false" class="w-4 h-4 text-red-500" />
                        <AlertCircle v-else class="w-4 h-4 text-gray-300" />
                    </div>
                    <p class="mt-1 text-base font-semibold text-gray-900">
                        {{ status.redis?.ok === true ? 'Conectado' : status.redis?.ok === false ? 'Error' : 'N/A' }}
                    </p>
                </div>

                <div class="bg-white rounded-xl border p-4"
                     :class="status.pending_migrations > 0 ? 'border-amber-300 bg-amber-50' : 'border-gray-200'">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium uppercase tracking-wide"
                           :class="status.pending_migrations > 0 ? 'text-amber-600' : 'text-gray-500'">Migraciones</p>
                        <AlertTriangle v-if="status.pending_migrations > 0" class="w-4 h-4 text-amber-500" />
                        <CheckCircle v-else class="w-4 h-4 text-emerald-500" />
                    </div>
                    <p class="mt-1 text-base font-semibold"
                       :class="status.pending_migrations > 0 ? 'text-amber-700' : 'text-gray-900'">
                        {{ status.pending_migrations > 0 ? `${status.pending_migrations} pendiente${status.pending_migrations > 1 ? 's' : ''}` : 'Al día' }}
                    </p>
                </div>

                <div class="bg-white rounded-xl border p-4"
                     :class="!status.storage_link ? 'border-red-200 bg-red-50' : 'border-gray-200'">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium uppercase tracking-wide"
                           :class="!status.storage_link ? 'text-red-600' : 'text-gray-500'">Storage</p>
                        <CheckCircle v-if="status.storage_link" class="w-4 h-4 text-emerald-500" />
                        <XCircle v-else class="w-4 h-4 text-red-500" />
                    </div>
                    <p class="mt-1 text-base font-semibold"
                       :class="!status.storage_link ? 'text-red-700' : 'text-gray-900'">
                        {{ status.storage_link ? 'Vinculado' : 'Sin symlink' }}
                    </p>
                </div>
            </div>

            <!-- Env badges with tooltips -->
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"
                      :class="status.environment === 'production' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                      :title="status.environment === 'production' ? 'APP_ENV=production — modo producción activo' : 'APP_ENV=local — modo desarrollo. Cambiar a production antes del deploy'">
                    <Server class="w-3 h-3" />{{ status.environment }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"
                      :class="status.debug_mode ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'"
                      :title="status.debug_mode ? 'APP_DEBUG=true — PELIGROSO en producción. Los usuarios verán errores internos.' : 'APP_DEBUG=false — errores ocultos al usuario, correcto para producción'">
                    <AlertCircle class="w-3 h-3" />DEBUG {{ status.debug_mode ? 'ON' : 'OFF' }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700"
                      title="Driver de colas. 'database' funciona para empezar. 'redis' es más rápido para alto volumen.">
                    <Layers class="w-3 h-3" />Queue: {{ status.queue_connection }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700"
                      title="Driver de caché. 'database' funciona bien. 'redis' es más rápido si lo tienes instalado.">
                    <Database class="w-3 h-3" />Cache: {{ status.cache_driver }}
                </span>
                <span v-if="status.failed_jobs > 0"
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700"
                      title="Jobs que fallaron al ejecutarse. Pueden ser mensajes de WhatsApp que no se enviaron. Revisa los logs para ver el error.">
                    <XCircle class="w-3 h-3" />{{ status.failed_jobs }} jobs fallidos
                </span>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- ACCIONES DE MANTENIMIENTO                                      -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

                <!-- Database -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                            <Database class="w-4 h-4 text-blue-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Base de datos</h3>
                            <p class="text-xs text-gray-400">Crea/actualiza las tablas necesarias</p>
                        </div>
                    </div>
                    <button
                        @click="runAction('migrate', '/system/migrate')"
                        :disabled="actions.migrate.loading"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
                        :class="status.pending_migrations > 0 ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700'"
                    >
                        <Loader2 v-if="actions.migrate.loading" class="w-4 h-4 animate-spin" />
                        <Database v-else class="w-4 h-4" />
                        {{ actions.migrate.loading ? 'Migrando...' : 'Ejecutar migrate' }}
                    </button>
                    <div v-if="actions.migrate.result" class="p-2.5 rounded-lg text-xs font-mono whitespace-pre-wrap break-all"
                         :class="actions.migrate.result.success ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                        {{ actions.migrate.result.output }}
                    </div>
                </div>

                <!-- Cache -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
                            <Layers class="w-4 h-4 text-purple-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Caché</h3>
                            <p class="text-xs text-gray-400">Acelera la app guardando datos en memoria</p>
                        </div>
                    </div>

                    <button
                        @click="runAction('clearCache', '/system/cache/clear')"
                        :disabled="actions.clearCache.loading"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-red-50 hover:bg-red-100 text-red-700 transition-colors disabled:opacity-50"
                    >
                        <Loader2 v-if="actions.clearCache.loading" class="w-4 h-4 animate-spin" />
                        <XCircle v-else class="w-4 h-4" />
                        {{ actions.clearCache.loading ? 'Limpiando...' : 'Limpiar todo' }}
                    </button>
                    <div v-if="actions.clearCache.result" class="p-2.5 rounded-lg text-xs font-mono whitespace-pre-wrap"
                         :class="actions.clearCache.result.success ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                        {{ actions.clearCache.result.output }}
                    </div>

                    <button
                        @click="runAction('rebuildCache', '/system/cache/rebuild')"
                        :disabled="actions.rebuildCache.loading"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-purple-50 hover:bg-purple-100 text-purple-700 transition-colors disabled:opacity-50"
                    >
                        <Loader2 v-if="actions.rebuildCache.loading" class="w-4 h-4 animate-spin" />
                        <RefreshCw v-else class="w-4 h-4" />
                        {{ actions.rebuildCache.loading ? 'Reconstruyendo...' : 'Reconstruir caché' }}
                    </button>
                    <p class="text-xs text-gray-400">Usa después de cambiar el .env en producción</p>
                    <div v-if="actions.rebuildCache.result" class="p-2.5 rounded-lg text-xs font-mono whitespace-pre-wrap"
                         :class="actions.rebuildCache.result.success ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                        {{ actions.rebuildCache.result.output }}
                    </div>
                </div>

                <!-- Queue -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">
                            <RotateCcw class="w-4 h-4 text-emerald-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Queue Workers</h3>
                            <p class="text-xs text-gray-400">Procesan mensajes WhatsApp y notificaciones</p>
                        </div>
                    </div>

                    <button
                        @click="runAction('restartWorkers', '/system/queue/restart')"
                        :disabled="actions.restartWorkers.loading"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-emerald-50 hover:bg-emerald-100 text-emerald-700 transition-colors disabled:opacity-50"
                    >
                        <Loader2 v-if="actions.restartWorkers.loading" class="w-4 h-4 animate-spin" />
                        <RotateCcw v-else class="w-4 h-4" />
                        {{ actions.restartWorkers.loading ? 'Enviando señal...' : 'Reiniciar workers' }}
                    </button>
                    <div v-if="actions.restartWorkers.result" class="p-2.5 rounded-lg text-xs whitespace-pre-wrap"
                         :class="actions.restartWorkers.result.success ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                        {{ actions.restartWorkers.result.output }}
                    </div>

                    <button
                        @click="runAction('flushFailed', '/system/queue/flush')"
                        :disabled="actions.flushFailed.loading || status.failed_jobs === 0"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-40"
                        :class="status.failed_jobs > 0 ? 'bg-red-50 hover:bg-red-100 text-red-700' : 'bg-gray-50 text-gray-400 cursor-not-allowed'"
                    >
                        <Loader2 v-if="actions.flushFailed.loading" class="w-4 h-4 animate-spin" />
                        <XCircle v-else class="w-4 h-4" />
                        {{ actions.flushFailed.loading ? 'Eliminando...' : `Eliminar fallidos${status.failed_jobs > 0 ? ` (${status.failed_jobs})` : ''}` }}
                    </button>
                    <div v-if="actions.flushFailed.result" class="p-2.5 rounded-lg text-xs whitespace-pre-wrap"
                         :class="actions.flushFailed.result.success ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                        {{ actions.flushFailed.result.output }}
                    </div>
                </div>

                <!-- Storage -->
                <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
                            <HardDrive class="w-4 h-4 text-orange-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Storage</h3>
                            <p class="text-xs text-gray-400">Conecta la carpeta de archivos con el sitio web</p>
                        </div>
                    </div>

                    <button
                        @click="runAction('storageLink', '/system/storage/link')"
                        :disabled="actions.storageLink.loading"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
                        :class="!status.storage_link ? 'bg-orange-500 hover:bg-orange-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700'"
                    >
                        <Loader2 v-if="actions.storageLink.loading" class="w-4 h-4 animate-spin" />
                        <HardDrive v-else class="w-4 h-4" />
                        {{ actions.storageLink.loading ? 'Creando...' : 'Crear symlink' }}
                    </button>
                    <div v-if="actions.storageLink.result" class="p-2.5 rounded-lg text-xs whitespace-pre-wrap"
                         :class="actions.storageLink.result.success ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                        {{ actions.storageLink.result.output }}
                    </div>
                </div>

            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- GUÍA DE DEPLOY RÁPIDA                                          -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl border border-gray-200 p-5">
                <div class="flex items-center gap-2 mb-4">
                    <Shield class="w-5 h-5 text-slate-600" />
                    <h3 class="font-semibold text-gray-900">Guía rápida: archivo .env en producción</h3>
                </div>
                <p class="text-sm text-gray-600 mb-3">
                    El archivo <code class="text-xs bg-white px-1.5 py-0.5 rounded border font-mono">.env</code>
                    es el único archivo que necesitas editar en el servidor para configurar la app.
                    <strong>No está en el código</strong> — cada servidor tiene su propia copia.
                    Los valores que debes cambiar para producción:
                </p>
                <div class="bg-gray-950 rounded-lg p-4 font-mono text-xs leading-6 text-emerald-400 overflow-x-auto">
                    <div><span class="text-gray-500"># Cambia estos valores en el .env del servidor:</span></div>
                    <div class="mt-1">APP_ENV=<span class="text-amber-300">production</span></div>
                    <div>APP_DEBUG=<span class="text-amber-300">false</span></div>
                    <div>APP_URL=<span class="text-amber-300">https://tudominio.com</span></div>
                    <div class="mt-2"><span class="text-gray-500"># Base de datos del servidor:</span></div>
                    <div>DB_HOST=<span class="text-amber-300">127.0.0.1</span></div>
                    <div>DB_DATABASE=<span class="text-amber-300">waorder</span></div>
                    <div>DB_USERNAME=<span class="text-amber-300">tu_usuario_mysql</span></div>
                    <div>DB_PASSWORD=<span class="text-amber-300">tu_password_seguro</span></div>
                    <div class="mt-2"><span class="text-gray-500"># WhatsApp (obtener de Meta Business):</span></div>
                    <div>WHATSAPP_VERIFY_TOKEN=<span class="text-amber-300">un-token-secreto-que-tu-inventes</span></div>
                    <div>WHATSAPP_APP_SECRET=<span class="text-amber-300">tu_app_secret_de_meta</span></div>
                    <div class="mt-2"><span class="text-gray-500"># Después de guardar .env, ejecutar en esta pantalla:</span></div>
                    <div class="text-sky-400"><span class="text-gray-500"># 1.</span> Ejecutar migrate &nbsp;&nbsp;&nbsp;<span class="text-gray-500">(botón arriba)</span></div>
                    <div class="text-sky-400"><span class="text-gray-500"># 2.</span> Crear symlink &nbsp;&nbsp;&nbsp;&nbsp;<span class="text-gray-500">(botón arriba)</span></div>
                    <div class="text-sky-400"><span class="text-gray-500"># 3.</span> Reconstruir caché <span class="text-gray-500">(botón arriba)</span></div>
                    <div class="text-sky-400"><span class="text-gray-500"># 4.</span> Reiniciar workers &nbsp;<span class="text-gray-500">(botón arriba)</span></div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- LOG VIEWER                                                     -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <Terminal class="w-4 h-4 text-gray-500" />
                        <h3 class="font-semibold text-gray-900">Laravel Log</h3>
                        <span class="text-xs text-gray-400">últimas 150 líneas — los errores aparecen en rojo</span>
                    </div>
                    <button
                        @click="loadLogs"
                        :disabled="logsLoading"
                        class="flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg bg-gray-900 text-white hover:bg-gray-700 transition-colors disabled:opacity-50"
                    >
                        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': logsLoading }" />
                        {{ logsVisible ? 'Actualizar' : 'Cargar logs' }}
                    </button>
                </div>

                <div v-if="logsVisible">
                    <div v-if="logsLoading" class="flex items-center justify-center py-12 text-gray-400">
                        <Loader2 class="w-5 h-5 animate-spin mr-2" /> Cargando...
                    </div>
                    <div v-else-if="logs.length === 0" class="flex items-center justify-center py-12 text-gray-400 text-sm">
                        No hay entradas en el log.
                    </div>
                    <div v-else class="overflow-auto max-h-96 font-mono text-xs p-4 bg-gray-950 rounded-b-xl">
                        <div v-for="(line, i) in logs" :key="i"
                             :class="logLineClass(line)"
                             class="leading-5 whitespace-pre-wrap break-all">{{ line }}</div>
                    </div>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
