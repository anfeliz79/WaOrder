import { create } from 'zustand';

interface Driver {
  id: number;
  name: string;
  phone: string;
  vehicle_type: string | null;
  vehicle_plate: string | null;
  is_available: boolean;
  completed_deliveries: number;
}

interface Tenant {
  name: string;
  slug: string;
  currency: string;
}

interface AuthState {
  token: string | null;
  apiUrl: string | null;
  driver: Driver | null;
  tenant: Tenant | null;
  isAuthenticated: boolean;
  setAuth: (token: string, apiUrl: string, driver: Driver, tenant: Tenant) => void;
  updateDriver: (updates: Partial<Driver>) => void;
  clearAuth: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  token: null,
  apiUrl: null,
  driver: null,
  tenant: null,
  isAuthenticated: false,
  setAuth: (token, apiUrl, driver, tenant) =>
    set({ token, apiUrl, driver, tenant, isAuthenticated: true }),
  updateDriver: (updates) =>
    set((state) => ({
      driver: state.driver ? { ...state.driver, ...updates } : null,
    })),
  clearAuth: () =>
    set({ token: null, apiUrl: null, driver: null, tenant: null, isAuthenticated: false }),
}));
