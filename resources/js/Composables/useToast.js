import { ref } from 'vue';

const toasts = ref([]);
let nextId = 0;

export function useToast() {
    function addToast(message, type = 'success', duration = 4000) {
        const id = nextId++;
        toasts.value.push({ id, message, type });
        setTimeout(() => {
            removeToast(id);
        }, duration);
    }

    function removeToast(id) {
        const index = toasts.value.findIndex(t => t.id === id);
        if (index > -1) toasts.value.splice(index, 1);
    }

    return {
        toasts,
        removeToast,
        success: (msg) => addToast(msg, 'success'),
        error: (msg) => addToast(msg, 'error'),
        warning: (msg) => addToast(msg, 'warning'),
        info: (msg) => addToast(msg, 'info'),
    };
}
