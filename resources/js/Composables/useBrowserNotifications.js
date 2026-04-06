import { ref } from 'vue';

const STORAGE_KEY = 'waorder_browser_notifications_dismissed';

const isSupported = ref(typeof window !== 'undefined' && 'Notification' in window);
const permissionGranted = ref(false);
const permissionDenied = ref(false);
const bannerDismissed = ref(false);

// Initialize state from current permission and localStorage
if (isSupported.value) {
    permissionGranted.value = Notification.permission === 'granted';
    permissionDenied.value = Notification.permission === 'denied';
    bannerDismissed.value = localStorage.getItem(STORAGE_KEY) === '1';
}

export function useBrowserNotifications() {
    /**
     * Whether the permission request banner should be shown.
     * Only show if: supported, not yet granted, not denied, and not dismissed by user.
     */
    const shouldShowBanner = () => {
        return isSupported.value
            && !permissionGranted.value
            && !permissionDenied.value
            && !bannerDismissed.value;
    };

    /**
     * Request notification permission from the browser.
     * Returns true if granted.
     */
    const requestPermission = async () => {
        if (!isSupported.value) return false;

        if (Notification.permission === 'granted') {
            permissionGranted.value = true;
            return true;
        }

        if (Notification.permission !== 'denied') {
            const result = await Notification.requestPermission();
            permissionGranted.value = result === 'granted';
            permissionDenied.value = result === 'denied';
            return permissionGranted.value;
        }

        permissionDenied.value = true;
        return false;
    };

    /**
     * Dismiss the banner permanently (stores in localStorage).
     */
    const dismissBanner = () => {
        bannerDismissed.value = true;
        localStorage.setItem(STORAGE_KEY, '1');
    };

    /**
     * Send a browser notification (only when tab is not focused).
     * @param {string} title - Notification title
     * @param {object} options - Notification options (body, tag, url, etc.)
     */
    const notify = (title, options = {}) => {
        if (!permissionGranted.value || document.hasFocus()) return null;

        const notification = new Notification(title, {
            icon: '/images/logo-icon.png',
            badge: '/images/logo-icon.png',
            tag: options.tag || 'waorder-order',
            renotify: true,
            ...options,
        });

        notification.onclick = () => {
            window.focus();
            notification.close();
            if (options.url) {
                window.location.href = options.url;
            }
        };

        // Auto-close after 10 seconds
        setTimeout(() => notification.close(), 10000);

        return notification;
    };

    return {
        isSupported,
        permissionGranted,
        permissionDenied,
        bannerDismissed,
        shouldShowBanner,
        requestPermission,
        dismissBanner,
        notify,
    };
}
