import axios, { AxiosInstance } from 'axios';
import * as SecureStore from 'expo-secure-store';

let apiInstance: AxiosInstance | null = null;

export const initApi = (baseURL: string, token: string): AxiosInstance => {
  apiInstance = axios.create({
    baseURL: `${baseURL}/api/driver-app`,
    timeout: 15000,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  });

  apiInstance.interceptors.response.use(
    (response) => response,
    async (error) => {
      if (error.response?.status === 401) {
        await SecureStore.deleteItemAsync('auth_token');
        await SecureStore.deleteItemAsync('api_url');
        await SecureStore.deleteItemAsync('driver_data');
        await SecureStore.deleteItemAsync('tenant_data');
      }
      return Promise.reject(error);
    }
  );

  return apiInstance;
};

export const getApi = (): AxiosInstance => {
  if (!apiInstance) {
    throw new Error('API not initialized. Call initApi first.');
  }
  return apiInstance;
};

// Link endpoint (unauthenticated)
export const linkDevice = async (
  apiUrl: string,
  tenantSlug: string,
  driverId: number,
  token: string
) => {
  const response = await axios.post(`${apiUrl}/api/driver-app/link`, {
    tenant_slug: tenantSlug,
    driver_id: driverId,
    token,
  });
  return response.data;
};
