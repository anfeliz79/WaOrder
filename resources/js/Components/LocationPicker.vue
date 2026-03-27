<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { MapPin, Search, Crosshair, X } from 'lucide-vue-next';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    latitude: [Number, String],
    longitude: [Number, String],
    address: String,
});

const emit = defineEmits(['update:latitude', 'update:longitude', 'update:address']);

const mapContainer = ref(null);
const searchInput = ref(null);
const searchQuery = ref('');
const searchResults = ref([]);
const searching = ref(false);
const showResults = ref(false);
const mapExpanded = ref(false);
let map = null;
let marker = null;
let searchTimeout = null;

// Default center: Santo Domingo, DR
const defaultLat = 18.4861;
const defaultLng = -69.9312;

// Fix Leaflet default icon issue with bundlers
const defaultIcon = L.icon({
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
});

function initMap() {
    if (!mapContainer.value || map) return;

    const lat = parseFloat(props.latitude) || defaultLat;
    const lng = parseFloat(props.longitude) || defaultLng;

    map = L.map(mapContainer.value, {
        center: [lat, lng],
        zoom: props.latitude ? 16 : 12,
        zoomControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(map);

    if (props.latitude && props.longitude) {
        placeMarker(lat, lng);
    }

    map.on('click', (e) => {
        placeMarker(e.latlng.lat, e.latlng.lng);
        emitCoordinates(e.latlng.lat, e.latlng.lng);
        reverseGeocode(e.latlng.lat, e.latlng.lng);
    });
}

function placeMarker(lat, lng) {
    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng], { draggable: true, icon: defaultIcon }).addTo(map);
        marker.on('dragend', () => {
            const pos = marker.getLatLng();
            emitCoordinates(pos.lat, pos.lng);
            reverseGeocode(pos.lat, pos.lng);
        });
    }
}

function emitCoordinates(lat, lng) {
    emit('update:latitude', parseFloat(lat.toFixed(7)));
    emit('update:longitude', parseFloat(lng.toFixed(7)));
}

async function reverseGeocode(lat, lng) {
    try {
        const res = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`,
            { headers: { 'Accept-Language': 'es' } }
        );
        const data = await res.json();
        if (data.display_name) {
            emit('update:address', data.display_name);
        }
    } catch (e) {
        // Silently fail — address is optional
    }
}

async function searchAddress() {
    const q = searchQuery.value.trim();
    if (q.length < 3) {
        searchResults.value = [];
        return;
    }

    searching.value = true;
    try {
        const res = await fetch(
            `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=5&countrycodes=do&addressdetails=1`,
            { headers: { 'Accept-Language': 'es' } }
        );
        searchResults.value = await res.json();
        showResults.value = searchResults.value.length > 0;
    } catch (e) {
        searchResults.value = [];
    } finally {
        searching.value = false;
    }
}

function onSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(searchAddress, 400);
}

function selectResult(result) {
    const lat = parseFloat(result.lat);
    const lng = parseFloat(result.lon);

    placeMarker(lat, lng);
    map.setView([lat, lng], 17);
    emitCoordinates(lat, lng);
    emit('update:address', result.display_name);

    searchQuery.value = result.display_name;
    showResults.value = false;
}

function clearSearch() {
    searchQuery.value = '';
    searchResults.value = [];
    showResults.value = false;
}

function locateMe() {
    if (!navigator.geolocation) return;

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            placeMarker(lat, lng);
            map.setView([lat, lng], 17);
            emitCoordinates(lat, lng);
            reverseGeocode(lat, lng);
        },
        () => {
            // User denied or unavailable
        },
        { enableHighAccuracy: true }
    );
}

function toggleMap() {
    mapExpanded.value = !mapExpanded.value;
    if (mapExpanded.value) {
        nextTick(() => {
            if (!map) {
                initMap();
            } else {
                map.invalidateSize();
            }
        });
    }
}

// Watch for external lat/lng changes (e.g. edit form pre-fill)
watch([() => props.latitude, () => props.longitude], ([newLat, newLng]) => {
    if (map && newLat && newLng) {
        const lat = parseFloat(newLat);
        const lng = parseFloat(newLng);
        if (!isNaN(lat) && !isNaN(lng)) {
            placeMarker(lat, lng);
            map.setView([lat, lng], 16);
        }
    }
});

// Close search results on outside click
function onClickOutside(e) {
    if (searchInput.value && !searchInput.value.contains(e.target)) {
        showResults.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', onClickOutside);
    if (map) {
        map.remove();
        map = null;
        marker = null;
    }
});
</script>

<template>
    <div class="space-y-3">
        <!-- Lat/Lng Manual Inputs -->
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Latitud</label>
                <input
                    :value="latitude"
                    @input="$emit('update:latitude', $event.target.value ? parseFloat($event.target.value) : '')"
                    type="number"
                    step="any"
                    placeholder="18.4861"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Longitud</label>
                <input
                    :value="longitude"
                    @input="$emit('update:longitude', $event.target.value ? parseFloat($event.target.value) : '')"
                    type="number"
                    step="any"
                    placeholder="-69.9312"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                />
            </div>
        </div>

        <!-- Map Toggle Button -->
        <button
            type="button"
            @click="toggleMap"
            class="inline-flex items-center gap-2 text-sm text-primary-600 hover:text-primary-700 font-medium transition-colors"
        >
            <MapPin class="w-4 h-4" />
            {{ mapExpanded ? 'Ocultar mapa' : 'Seleccionar en mapa' }}
        </button>

        <!-- Map Section -->
        <div v-show="mapExpanded" class="space-y-2 rounded-xl border border-gray-200 bg-gray-50 p-3">
            <!-- Search Bar -->
            <div ref="searchInput" class="relative">
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                            v-model="searchQuery"
                            @input="onSearchInput"
                            @focus="showResults = searchResults.length > 0"
                            type="text"
                            placeholder="Buscar direccion..."
                            class="w-full pl-9 pr-8 py-2 rounded-lg border border-gray-300 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            @click="clearSearch"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                    <button
                        type="button"
                        @click="locateMe"
                        class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 transition-colors"
                        title="Usar mi ubicacion"
                    >
                        <Crosshair class="w-4 h-4" />
                    </button>
                </div>

                <!-- Search Results Dropdown -->
                <div
                    v-if="showResults"
                    class="absolute z-[1000] mt-1 w-full bg-white rounded-lg border border-gray-200 shadow-lg max-h-48 overflow-auto"
                >
                    <button
                        v-for="result in searchResults"
                        :key="result.place_id"
                        type="button"
                        @click="selectResult(result)"
                        class="w-full text-left px-3 py-2 text-sm hover:bg-primary-50 transition-colors border-b border-gray-100 last:border-0"
                    >
                        <div class="flex items-start gap-2">
                            <MapPin class="w-4 h-4 text-gray-400 shrink-0 mt-0.5" />
                            <span class="text-gray-700 line-clamp-2">{{ result.display_name }}</span>
                        </div>
                    </button>
                </div>

                <!-- Loading indicator -->
                <div v-if="searching" class="absolute z-[1000] mt-1 w-full bg-white rounded-lg border border-gray-200 shadow-lg p-3 text-center">
                    <span class="text-sm text-gray-500">Buscando...</span>
                </div>
            </div>

            <!-- Map Container -->
            <div
                ref="mapContainer"
                class="w-full rounded-lg overflow-hidden border border-gray-200"
                style="height: 280px;"
            ></div>

            <p class="text-xs text-gray-400 text-center">
                Haz clic en el mapa o arrastra el marcador para seleccionar la ubicacion
            </p>
        </div>
    </div>
</template>
