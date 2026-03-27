import React, { useState, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Alert,
  ActivityIndicator,
  TouchableOpacity,
} from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { useAuthStore } from '../store/useAuthStore';
import { linkDevice, initApi } from '../services/api';
import { storeAuth } from '../services/auth';
import { registerForPushNotifications } from '../services/notifications';

export default function LinkScreen() {
  const [permission, requestPermission] = useCameraPermissions();
  const [scanning, setScanning] = useState(false);
  const setAuth = useAuthStore((s) => s.setAuth);
  const processedRef = useRef(false);

  const handleBarCodeScanned = async ({ data }: { data: string }) => {
    if (processedRef.current) return;
    processedRef.current = true;
    setScanning(true);

    try {
      const payload = JSON.parse(data);

      if (!payload.tenant_slug || !payload.driver_id || !payload.token || !payload.api_url) {
        Alert.alert('QR Invalido', 'Este QR no es valido para la app de delivery.');
        processedRef.current = false;
        setScanning(false);
        return;
      }

      const result = await linkDevice(
        payload.api_url,
        payload.tenant_slug,
        payload.driver_id,
        payload.token
      );

      // Store auth data
      await storeAuth(result.access_token, payload.api_url, result.driver, result.tenant);

      // Initialize API
      initApi(payload.api_url, result.access_token);

      // Set auth state
      setAuth(result.access_token, payload.api_url, result.driver, result.tenant);

      // Register push notifications (non-blocking)
      registerForPushNotifications().catch(() => {});
    } catch (error: any) {
      const message =
        error.response?.data?.message || 'No se pudo vincular. Verifica el QR e intenta de nuevo.';
      Alert.alert('Error', message);
      processedRef.current = false;
    } finally {
      setScanning(false);
    }
  };

  if (!permission) {
    return (
      <View style={styles.container}>
        <ActivityIndicator size="large" color="#ea580c" />
      </View>
    );
  }

  if (!permission.granted) {
    return (
      <View style={styles.container}>
        <View style={styles.permissionBox}>
          <Text style={styles.title}>WaOrder Delivery</Text>
          <Text style={styles.subtitle}>
            Necesitamos acceso a la camara para escanear el QR de vinculacion.
          </Text>
          <TouchableOpacity style={styles.button} onPress={requestPermission}>
            <Text style={styles.buttonText}>Permitir Camara</Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <CameraView
        style={StyleSheet.absoluteFillObject}
        barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
        onBarcodeScanned={scanning ? undefined : handleBarCodeScanned}
      />

      {/* Overlay */}
      <View style={styles.overlay}>
        <View style={styles.topOverlay}>
          <Text style={styles.title}>WaOrder Delivery</Text>
          <Text style={styles.instruction}>Escanea el QR desde el panel del restaurante</Text>
        </View>

        {/* Scan frame */}
        <View style={styles.scanFrame}>
          <View style={[styles.corner, styles.topLeft]} />
          <View style={[styles.corner, styles.topRight]} />
          <View style={[styles.corner, styles.bottomLeft]} />
          <View style={[styles.corner, styles.bottomRight]} />
        </View>

        {scanning && (
          <View style={styles.loadingOverlay}>
            <ActivityIndicator size="large" color="#fff" />
            <Text style={styles.loadingText}>Vinculando...</Text>
          </View>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#000',
    justifyContent: 'center',
    alignItems: 'center',
  },
  permissionBox: {
    padding: 32,
    alignItems: 'center',
  },
  title: {
    fontSize: 28,
    fontWeight: '700',
    color: '#fff',
    textAlign: 'center',
  },
  subtitle: {
    fontSize: 16,
    color: '#ccc',
    textAlign: 'center',
    marginTop: 12,
    marginBottom: 24,
    lineHeight: 22,
  },
  button: {
    backgroundColor: '#ea580c',
    paddingHorizontal: 32,
    paddingVertical: 14,
    borderRadius: 12,
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
  },
  topOverlay: {
    position: 'absolute',
    top: 80,
    alignItems: 'center',
  },
  instruction: {
    fontSize: 14,
    color: '#ccc',
    marginTop: 8,
  },
  scanFrame: {
    width: 250,
    height: 250,
    position: 'relative',
  },
  corner: {
    position: 'absolute',
    width: 30,
    height: 30,
    borderColor: '#ea580c',
  },
  topLeft: {
    top: 0,
    left: 0,
    borderTopWidth: 3,
    borderLeftWidth: 3,
    borderTopLeftRadius: 8,
  },
  topRight: {
    top: 0,
    right: 0,
    borderTopWidth: 3,
    borderRightWidth: 3,
    borderTopRightRadius: 8,
  },
  bottomLeft: {
    bottom: 0,
    left: 0,
    borderBottomWidth: 3,
    borderLeftWidth: 3,
    borderBottomLeftRadius: 8,
  },
  bottomRight: {
    bottom: 0,
    right: 0,
    borderBottomWidth: 3,
    borderRightWidth: 3,
    borderBottomRightRadius: 8,
  },
  loadingOverlay: {
    position: 'absolute',
    bottom: 120,
    alignItems: 'center',
  },
  loadingText: {
    color: '#fff',
    marginTop: 8,
    fontSize: 16,
  },
});
