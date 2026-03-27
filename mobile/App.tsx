import React, { useEffect, useState } from 'react';
import { StatusBar } from 'expo-status-bar';
import { View, ActivityIndicator, StyleSheet } from 'react-native';
import * as Notifications from 'expo-notifications';
import AppNavigator from './src/navigation/AppNavigator';
import { useAuthStore } from './src/store/useAuthStore';
import { getStoredAuth } from './src/services/auth';
import { initApi } from './src/services/api';
import { registerForPushNotifications } from './src/services/notifications';

export default function App() {
  const [loading, setLoading] = useState(true);
  const setAuth = useAuthStore((s) => s.setAuth);

  useEffect(() => {
    const restoreAuth = async () => {
      try {
        const stored = await getStoredAuth();
        if (stored?.token && stored?.apiUrl) {
          initApi(stored.apiUrl, stored.token);
          setAuth(stored.token, stored.apiUrl, stored.driver, stored.tenant);

          // Re-register push token on app start (non-blocking)
          registerForPushNotifications().catch(() => {});
        }
      } catch (error) {
        console.error('Failed to restore auth:', error);
      } finally {
        setLoading(false);
      }
    };

    restoreAuth();
  }, []);

  // Handle notification taps
  useEffect(() => {
    const subscription = Notifications.addNotificationResponseReceivedListener(
      (response) => {
        const data = response.notification.request.content.data;
        if (data?.type === 'new_assignment' && data?.order_id) {
          // Navigation will be handled by the orders screen refresh
          console.log('Notification tapped for order:', data.order_id);
        }
      }
    );

    return () => subscription.remove();
  }, []);

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#ea580c" />
      </View>
    );
  }

  return (
    <>
      <StatusBar style="auto" />
      <AppNavigator />
    </>
  );
}

const styles = StyleSheet.create({
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#fff',
  },
});
