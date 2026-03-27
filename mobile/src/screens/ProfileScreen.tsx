import React, { useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Alert,
  Switch,
  SafeAreaView,
  StatusBar,
  ScrollView,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { useAuthStore } from '../store/useAuthStore';
import { getApi } from '../services/api';
import { clearStoredAuth } from '../services/auth';

export default function ProfileScreen() {
  const { driver, tenant, updateDriver, clearAuth } = useAuthStore();
  const [toggling, setToggling] = useState(false);

  // Refresh profile from server when screen focused
  useFocusEffect(
    useCallback(() => {
      const refresh = async () => {
        try {
          const res = await getApi().get('/profile');
          updateDriver(res.data.driver);
        } catch {}
      };
      refresh();
    }, [])
  );

  const toggleAvailability = async () => {
    setToggling(true);
    try {
      const response = await getApi().post('/availability');
      updateDriver({ is_available: response.data.is_available });
    } catch (error) {
      console.error('Failed to toggle availability:', error);
    } finally {
      setToggling(false);
    }
  };

  const handleUnlink = () => {
    Alert.alert('Desvincular App', 'Tendras que escanear el QR nuevamente para volver a usar la app.', [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Desvincular', style: 'destructive', onPress: async () => {
        try { await getApi().delete('/unlink'); } catch {}
        await clearStoredAuth();
        clearAuth();
      }},
    ]);
  };

  const vehicles: Record<string, string> = { moto: '🏍️ Moto', carro: '🚗 Carro', bicicleta: '🚲 Bicicleta' };

  return (
    <SafeAreaView style={styles.safe}>
      <StatusBar barStyle="light-content" />
      <ScrollView showsVerticalScrollIndicator={false}>
        {/* Header */}
        <View style={styles.header}>
          <View style={styles.avatarLg}>
            <Text style={styles.avatarText}>{driver?.name?.charAt(0)?.toUpperCase() || '?'}</Text>
          </View>
          <Text style={styles.name}>{driver?.name}</Text>
          <Text style={styles.phone}>{driver?.phone}</Text>
          <View style={styles.tenantPill}>
            <Text style={styles.tenantText}>{tenant?.name}</Text>
          </View>
        </View>

        <View style={styles.content}>
          {/* Stats */}
          <View style={styles.statsRow}>
            <View style={styles.statCard}>
              <Text style={styles.statNum}>{driver?.completed_deliveries || 0}</Text>
              <Text style={styles.statLabel}>Entregas</Text>
            </View>
            <View style={styles.statCard}>
              <Text style={styles.statNum}>{vehicles[driver?.vehicle_type || ''] ? '1' : '0'}</Text>
              <Text style={styles.statLabel}>Vehiculo</Text>
            </View>
          </View>

          {/* Info */}
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Informacion</Text>
            {[
              { icon: '🚗', label: 'Vehiculo', value: vehicles[driver?.vehicle_type || ''] || 'No asignado' },
              ...(driver?.vehicle_plate ? [{ icon: '🔢', label: 'Placa', value: driver.vehicle_plate }] : []),
            ].map((item) => (
              <View key={item.label} style={styles.infoRow}>
                <Text style={styles.infoIcon}>{item.icon}</Text>
                <Text style={styles.infoLabel}>{item.label}</Text>
                <Text style={styles.infoValue}>{item.value}</Text>
              </View>
            ))}
          </View>

          {/* Availability */}
          <View style={styles.section}>
            <View style={styles.switchRow}>
              <View style={{ flex: 1 }}>
                <Text style={styles.switchTitle}>Disponible para entregas</Text>
                <Text style={styles.switchSub}>
                  {driver?.is_available ? '🟢 Recibiras nuevas asignaciones' : '🔴 No recibiras asignaciones'}
                </Text>
              </View>
              <Switch
                value={driver?.is_available || false}
                onValueChange={toggleAvailability}
                disabled={toggling}
                trackColor={{ false: '#e2e8f0', true: '#bbf7d0' }}
                thumbColor={driver?.is_available ? '#16a34a' : '#94a3b8'}
              />
            </View>
          </View>

          {/* Unlink */}
          <TouchableOpacity style={styles.unlinkBtn} onPress={handleUnlink}>
            <Text style={styles.unlinkText}>Desvincular App</Text>
          </TouchableOpacity>

          <Text style={styles.version}>WaOrder Delivery v1.0.0</Text>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#0f172a' },
  header: { backgroundColor: '#0f172a', alignItems: 'center', paddingTop: 20, paddingBottom: 32 },
  avatarLg: { width: 80, height: 80, borderRadius: 28, backgroundColor: '#1e293b', justifyContent: 'center', alignItems: 'center', marginBottom: 14, borderWidth: 3, borderColor: '#fb923c' },
  avatarText: { fontSize: 32, fontWeight: '800', color: '#fb923c' },
  name: { fontSize: 24, fontWeight: '800', color: '#f8fafc' },
  phone: { fontSize: 14, color: '#64748b', marginTop: 4 },
  tenantPill: { backgroundColor: '#1e293b', paddingHorizontal: 14, paddingVertical: 6, borderRadius: 10, marginTop: 10 },
  tenantText: { fontSize: 13, fontWeight: '700', color: '#fb923c' },

  content: { backgroundColor: '#f1f5f9', borderTopLeftRadius: 28, borderTopRightRadius: 28, padding: 16, paddingTop: 20, minHeight: 500 },

  statsRow: { flexDirection: 'row', gap: 12, marginBottom: 16 },
  statCard: { flex: 1, backgroundColor: '#fff', borderRadius: 18, padding: 20, alignItems: 'center', shadowColor: '#0f172a', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.04, shadowRadius: 8, elevation: 2 },
  statNum: { fontSize: 30, fontWeight: '900', color: '#ea580c' },
  statLabel: { fontSize: 13, color: '#64748b', fontWeight: '600', marginTop: 4 },

  section: { backgroundColor: '#fff', borderRadius: 20, padding: 18, marginBottom: 12 },
  sectionTitle: { fontSize: 14, fontWeight: '800', color: '#64748b', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 14 },
  infoRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 10, gap: 10 },
  infoIcon: { fontSize: 18 },
  infoLabel: { fontSize: 14, color: '#64748b', flex: 1 },
  infoValue: { fontSize: 14, fontWeight: '600', color: '#1e293b' },

  switchRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  switchTitle: { fontSize: 16, fontWeight: '700', color: '#0f172a' },
  switchSub: { fontSize: 13, color: '#64748b', marginTop: 2 },

  unlinkBtn: { backgroundColor: '#fff', borderRadius: 16, padding: 18, alignItems: 'center', borderWidth: 2, borderColor: '#fecaca', marginTop: 4 },
  unlinkText: { fontSize: 15, fontWeight: '700', color: '#dc2626' },

  version: { textAlign: 'center', fontSize: 12, color: '#cbd5e1', marginTop: 24, marginBottom: 20 },
});
