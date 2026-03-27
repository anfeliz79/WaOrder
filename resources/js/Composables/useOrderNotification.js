import { ref, onMounted, onUnmounted, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useToast } from '@/Composables/useToast';

const DEFAULT_POLLING_INTERVAL = 10; // seconds

function playDefaultSound() {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    if (ctx.state === 'suspended') {
        ctx.resume();
    }

    const now = ctx.currentTime;

    // First tone
    const osc1 = ctx.createOscillator();
    const gain1 = ctx.createGain();
    osc1.connect(gain1);
    gain1.connect(ctx.destination);
    osc1.frequency.setValueAtTime(587, now); // D5
    gain1.gain.setValueAtTime(0.3, now);
    gain1.gain.exponentialRampToValueAtTime(0.01, now + 0.2);
    osc1.start(now);
    osc1.stop(now + 0.2);

    // Second tone (higher)
    const osc2 = ctx.createOscillator();
    const gain2 = ctx.createGain();
    osc2.connect(gain2);
    gain2.connect(ctx.destination);
    osc2.frequency.setValueAtTime(784, now + 0.15); // G5
    gain2.gain.setValueAtTime(0, now);
    gain2.gain.setValueAtTime(0.3, now + 0.15);
    gain2.gain.exponentialRampToValueAtTime(0.01, now + 0.45);
    osc2.start(now + 0.15);
    osc2.stop(now + 0.45);

    // Third tone (highest)
    const osc3 = ctx.createOscillator();
    const gain3 = ctx.createGain();
    osc3.connect(gain3);
    gain3.connect(ctx.destination);
    osc3.frequency.setValueAtTime(988, now + 0.3); // B5
    gain3.gain.setValueAtTime(0, now);
    gain3.gain.setValueAtTime(0.3, now + 0.3);
    gain3.gain.exponentialRampToValueAtTime(0.01, now + 0.6);
    osc3.start(now + 0.3);
    osc3.stop(now + 0.6);
}

function playCustomSound(url) {
    const audio = new Audio(url);
    audio.volume = 0.7;
    audio.play().catch(() => {
        // Fallback to default if custom sound fails
        playDefaultSound();
    });
}

function playNotificationSound(customSoundUrl) {
    if (customSoundUrl) {
        playCustomSound(customSoundUrl);
    } else {
        playDefaultSound();
    }
}

export function useOrderNotification() {
    const page = usePage();
    const toast = useToast();
    const enabled = ref(page.props.notification_settings?.sound_enabled ?? false);
    const pollingInterval = ref(page.props.notification_settings?.polling_interval ?? DEFAULT_POLLING_INTERVAL);
    const customSoundUrl = ref(page.props.notification_settings?.custom_sound_url ?? null);
    const lastSeenId = ref(0);
    let intervalHandle = null;

    async function poll() {
        try {
            const res = await fetch('/orders/latest-id', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!res.ok) return;
            const data = await res.json();
            const latestId = data.latest_id ?? 0;

            if (lastSeenId.value > 0 && latestId > lastSeenId.value) {
                const count = latestId - lastSeenId.value;
                if (enabled.value) {
                    playNotificationSound(customSoundUrl.value);
                }
                toast.info(count === 1 ? '🛒 Nueva orden recibida' : `🛒 ${count} nuevas órdenes recibidas`);

                // Auto-reload if on the orders or dashboard page
                if (page.url.startsWith('/orders') || page.url.startsWith('/dashboard')) {
                    router.reload({ preserveScroll: true });
                }
            }
            lastSeenId.value = latestId;
        } catch {
            // Silently ignore network errors
        }
    }

    function start() {
        if (intervalHandle) return;
        poll(); // Baseline fetch (no sound on first call)
        intervalHandle = setInterval(poll, pollingInterval.value * 1000);
    }

    function stop() {
        if (intervalHandle) {
            clearInterval(intervalHandle);
            intervalHandle = null;
        }
    }

    function restart() {
        stop();
        start();
    }

    // React to Inertia prop changes (e.g. after saving settings)
    watch(() => page.props.notification_settings, (val) => {
        enabled.value = !!val?.sound_enabled;
        customSoundUrl.value = val?.custom_sound_url ?? null;
        const newInterval = val?.polling_interval ?? DEFAULT_POLLING_INTERVAL;
        if (newInterval !== pollingInterval.value) {
            pollingInterval.value = newInterval;
            restart();
        }
    }, { deep: true });

    // Polling always runs; `enabled` only controls whether sound plays
    onMounted(() => start());

    onUnmounted(() => stop());

    return { enabled, lastSeenId };
}

export { playNotificationSound };
