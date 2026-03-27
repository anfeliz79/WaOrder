import * as SecureStore from 'expo-secure-store';

const KEYS = {
  TOKEN: 'auth_token',
  API_URL: 'api_url',
  DRIVER: 'driver_data',
  TENANT: 'tenant_data',
};

export const storeAuth = async (
  token: string,
  apiUrl: string,
  driver: any,
  tenant: any
) => {
  await SecureStore.setItemAsync(KEYS.TOKEN, token);
  await SecureStore.setItemAsync(KEYS.API_URL, apiUrl);
  await SecureStore.setItemAsync(KEYS.DRIVER, JSON.stringify(driver));
  await SecureStore.setItemAsync(KEYS.TENANT, JSON.stringify(tenant));
};

export const getStoredAuth = async () => {
  const token = await SecureStore.getItemAsync(KEYS.TOKEN);
  const apiUrl = await SecureStore.getItemAsync(KEYS.API_URL);
  const driverStr = await SecureStore.getItemAsync(KEYS.DRIVER);
  const tenantStr = await SecureStore.getItemAsync(KEYS.TENANT);

  if (!token || !apiUrl) return null;

  return {
    token,
    apiUrl,
    driver: driverStr ? JSON.parse(driverStr) : null,
    tenant: tenantStr ? JSON.parse(tenantStr) : null,
  };
};

export const clearStoredAuth = async () => {
  await SecureStore.deleteItemAsync(KEYS.TOKEN);
  await SecureStore.deleteItemAsync(KEYS.API_URL);
  await SecureStore.deleteItemAsync(KEYS.DRIVER);
  await SecureStore.deleteItemAsync(KEYS.TENANT);
};
