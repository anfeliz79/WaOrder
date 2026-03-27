import {
    Clock,
    ChefHat,
    CheckCircle,
    Truck,
    PackageCheck,
    XCircle,
} from 'lucide-vue-next';

export const statusConfig = {
    confirmed: {
        label: 'Confirmado',
        color: 'blue',
        bgClass: 'bg-blue-50 text-blue-700 ring-blue-600/20',
        icon: Clock,
    },
    in_preparation: {
        label: 'En preparacion',
        color: 'amber',
        bgClass: 'bg-amber-50 text-amber-700 ring-amber-600/20',
        icon: ChefHat,
    },
    ready: {
        label: 'Listo',
        color: 'emerald',
        bgClass: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        icon: CheckCircle,
    },
    out_for_delivery: {
        label: 'En camino',
        color: 'violet',
        bgClass: 'bg-violet-50 text-violet-700 ring-violet-600/20',
        icon: Truck,
    },
    delivered: {
        label: 'Entregado',
        color: 'gray',
        bgClass: 'bg-gray-50 text-gray-700 ring-gray-600/20',
        icon: PackageCheck,
    },
    cancelled: {
        label: 'Cancelado',
        color: 'red',
        bgClass: 'bg-red-50 text-red-700 ring-red-600/20',
        icon: XCircle,
    },
};

export const getStatusLabel = (status) => statusConfig[status]?.label ?? status;
export const getStatusClass = (status) => statusConfig[status]?.bgClass ?? 'bg-gray-50 text-gray-700';
export const getStatusIcon = (status) => statusConfig[status]?.icon ?? Clock;
