import { onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Periodically reloads the current Inertia page to keep data fresh.
 *
 * @param {number} intervalSeconds - How often to refresh (default 30s)
 * @param {() => boolean} canRefresh - Optional guard; refresh is skipped when it returns false
 */
export function usePageAutoRefresh(intervalSeconds = 30, canRefresh = () => true) {
    let handle = null;

    onMounted(() => {
        handle = setInterval(() => {
            if (canRefresh()) {
                router.reload({ preserveScroll: true });
            }
        }, intervalSeconds * 1000);
    });

    onUnmounted(() => {
        if (handle) {
            clearInterval(handle);
            handle = null;
        }
    });
}
