import { create } from 'zustand';

export interface OrderItem {
  id: number;
  name: string;
  quantity: number;
  unit_price: number;
  subtotal: number;
  modifiers: any;
}

export interface Order {
  id: number;
  order_number: string;
  status: string;
  customer_name: string;
  customer_phone: string;
  delivery_address: string;
  delivery_latitude: number | null;
  delivery_longitude: number | null;
  delivery_type: string;
  total: number;
  subtotal: number;
  delivery_fee: number;
  payment_method: string;
  notes: string | null;
  created_at: string;
  items: OrderItem[];
}

interface OrdersState {
  orders: Order[];
  loading: boolean;
  setOrders: (orders: Order[]) => void;
  setLoading: (loading: boolean) => void;
  removeOrder: (orderId: number) => void;
}

export const useOrdersStore = create<OrdersState>((set) => ({
  orders: [],
  loading: false,
  setOrders: (orders) => set({ orders }),
  setLoading: (loading) => set({ loading }),
  removeOrder: (orderId) =>
    set((state) => ({ orders: state.orders.filter((o) => o.id !== orderId) })),
}));
