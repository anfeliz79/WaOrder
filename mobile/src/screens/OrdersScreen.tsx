import React, { useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  RefreshControl,
  ActivityIndicator,
  SafeAreaView,
  StatusBar,
  Platform,
} from 'react-native';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { useOrdersStore, Order } from '../store/useOrdersStore';
import { getApi } from '../services/api';
import { useAuthStore } from '../store/useAuthStore';

const statusLabels: Record<string, string> = {
  confirmed: 'Confirmado',
  in_preparation: 'Preparando',
  ready: 'Listo para recoger',
  out_for_delivery: 'En camino',
};

const statusConfig: Record<string, { bg: string; text: string; icon: string }> = {
  confirmed: { bg: '#eff6ff', text: '#1d4ed8', icon: '🔵' },
  in_preparation: { bg: '#fffbeb', text: '#b45309', icon: '🟡' },
  ready: { bg: '#ecfdf5', text: '#047857', icon: '🟢' },
  out_for_delivery: { bg: '#fff7ed', text: '#c2410c', icon: '🟠' },
};

function OrderCard({ order, onPress }: { order: Order; onPress: () => void }) {
  const currency = useAuthStore((s) => s.tenant?.currency) || 'DOP';
  const currencySymbol = currency === 'DOP' ? 'RD$' : '$';
  const minutesAgo = Math.floor((Date.now() - new Date(order.created_at).getTime()) / 60000);
  const config = statusConfig[order.status] || { bg: '#f9fafb', text: '#6b7280', icon: '⚪' };
  const itemCount = order.items?.length || 0;
  const timeLabel = minutesAgo < 60 ? `hace ${minutesAgo}m` : `hace ${Math.floor(minutesAgo / 60)}h`;

  return (
    <TouchableOpacity style={styles.card} onPress={onPress} activeOpacity={0.6}>
      {/* Order header */}
      <View style={styles.cardHeader}>
        <View style={styles.orderNumRow}>
          <Text style={styles.orderNum}>#{order.order_number}</Text>
          <Text style={styles.timeLabel}>{timeLabel}</Text>
        </View>
        <View style={[styles.statusPill, { backgroundColor: config.bg }]}>
          <Text style={styles.statusIcon}>{config.icon}</Text>
          <Text style={[styles.statusLabel, { color: config.text }]}>
            {statusLabels[order.status] || order.status}
          </Text>
        </View>
      </View>

      {/* Divider */}
      <View style={styles.cardDivider} />

      {/* Customer */}
      <View style={styles.customerBlock}>
        <View style={styles.avatar}>
          <Text style={styles.avatarLetter}>{order.customer_name?.charAt(0)?.toUpperCase() || '?'}</Text>
        </View>
        <View style={{ flex: 1 }}>
          <Text style={styles.custName}>{order.customer_name}</Text>
          {order.delivery_address ? (
            <Text style={styles.custAddress} numberOfLines={1}>{order.delivery_address}</Text>
          ) : null}
        </View>
      </View>

      {/* Footer */}
      <View style={styles.cardFooter}>
        <View style={styles.chipRow}>
          <View style={styles.chip}>
            <Text style={styles.chipText}>{itemCount} {itemCount === 1 ? 'producto' : 'productos'}</Text>
          </View>
          <View style={[styles.chip, order.payment_method === 'cash' && styles.chipCash]}>
            <Text style={[styles.chipText, order.payment_method === 'cash' && styles.chipCashText]}>
              {order.payment_method === 'cash' ? '💵 Efectivo' : order.payment_method === 'transfer' ? '🏦 Transfer' : order.payment_method}
            </Text>
          </View>
        </View>
        <Text style={styles.totalPrice}>{currencySymbol}{Number(order.total).toFixed(0)}</Text>
      </View>
    </TouchableOpacity>
  );
}

export default function OrdersScreen() {
  const navigation = useNavigation<any>();
  const { orders, loading, setOrders, setLoading } = useOrdersStore();
  const driver = useAuthStore((s) => s.driver);
  const tenant = useAuthStore((s) => s.tenant);
  const firstName = driver?.name?.split(' ')[0] || '';

  const updateDriver = useAuthStore((s) => s.updateDriver);

  const fetchOrders = useCallback(async () => {
    setLoading(true);
    try {
      const [ordersRes, profileRes] = await Promise.all([
        getApi().get('/orders'),
        getApi().get('/profile'),
      ]);
      setOrders(ordersRes.data.orders);
      updateDriver(profileRes.data.driver);
    } catch (error) {
      console.error('Failed to fetch orders:', error);
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(useCallback(() => { fetchOrders(); }, [fetchOrders]));

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="light-content" backgroundColor="#0f172a" />

      {/* Branded header */}
      <View style={styles.headerWrap}>
        <View style={styles.headerTop}>
          <View>
            <Text style={styles.greetSmall}>{tenant?.name || 'WaOrder'}</Text>
            <Text style={styles.greetName}>Hola, {firstName} 👋</Text>
          </View>
          <View style={[styles.onlinePill, driver?.is_available ? styles.onlineActive : styles.onlineInactive]}>
            <View style={[styles.onlineDot, { backgroundColor: driver?.is_available ? '#4ade80' : '#94a3b8' }]} />
            <Text style={[styles.onlineText, { color: driver?.is_available ? '#4ade80' : '#94a3b8' }]}>
              {driver?.is_available ? 'Disponible' : 'Offline'}
            </Text>
          </View>
        </View>

        {/* Summary bar */}
        <View style={styles.summaryBar}>
          <View style={styles.summaryItem}>
            <Text style={styles.summaryNum}>{orders.length}</Text>
            <Text style={styles.summaryLabel}>Pendientes</Text>
          </View>
          <View style={styles.summaryDivider} />
          <View style={styles.summaryItem}>
            <Text style={styles.summaryNum}>{driver?.completed_deliveries || 0}</Text>
            <Text style={styles.summaryLabel}>Completadas</Text>
          </View>
          <View style={styles.summaryDivider} />
          <View style={styles.summaryItem}>
            <Text style={styles.summaryNum}>{orders.filter(o => o.payment_method === 'cash').length}</Text>
            <Text style={styles.summaryLabel}>En efectivo</Text>
          </View>
        </View>
      </View>

      {/* Content */}
      {loading && orders.length === 0 ? (
        <View style={styles.emptyWrap}>
          <ActivityIndicator size="large" color="#ea580c" />
        </View>
      ) : orders.length === 0 ? (
        <View style={styles.emptyWrap}>
          <View style={styles.emptyCircle}>
            <Text style={{ fontSize: 40 }}>🛵</Text>
          </View>
          <Text style={styles.emptyTitle}>Sin entregas activas</Text>
          <Text style={styles.emptySub}>
            Cuando el restaurante te asigne{'\n'}una entrega, aparecera aqui
          </Text>
          <TouchableOpacity style={styles.refreshBtn} onPress={fetchOrders}>
            <Text style={styles.refreshBtnText}>Actualizar</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <FlatList
          data={orders}
          keyExtractor={(item) => item.id.toString()}
          renderItem={({ item }) => (
            <OrderCard order={item} onPress={() => navigation.navigate('OrderDetail', { order: item })} />
          )}
          contentContainerStyle={styles.list}
          showsVerticalScrollIndicator={false}
          refreshControl={
            <RefreshControl refreshing={loading} onRefresh={fetchOrders} tintColor="#ea580c" colors={['#ea580c']} />
          }
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: '#0f172a' },
  headerWrap: { backgroundColor: '#0f172a', paddingHorizontal: 20, paddingBottom: 20 },
  headerTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 },
  greetSmall: { fontSize: 12, color: '#64748b', fontWeight: '600', textTransform: 'uppercase', letterSpacing: 1 },
  greetName: { fontSize: 26, fontWeight: '800', color: '#fff', marginTop: 2 },
  onlinePill: { flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 12, paddingVertical: 6, borderRadius: 20, borderWidth: 1 },
  onlineActive: { borderColor: '#4ade8033', backgroundColor: '#4ade8010' },
  onlineInactive: { borderColor: '#94a3b833', backgroundColor: '#94a3b810' },
  onlineDot: { width: 8, height: 8, borderRadius: 4 },
  onlineText: { fontSize: 12, fontWeight: '700' },
  summaryBar: { flexDirection: 'row', backgroundColor: '#1e293b', borderRadius: 16, padding: 16, alignItems: 'center' },
  summaryItem: { flex: 1, alignItems: 'center' },
  summaryNum: { fontSize: 22, fontWeight: '800', color: '#f8fafc' },
  summaryLabel: { fontSize: 11, color: '#64748b', fontWeight: '600', marginTop: 2 },
  summaryDivider: { width: 1, height: 30, backgroundColor: '#334155' },

  list: { padding: 16, paddingTop: 20, backgroundColor: '#f1f5f9', borderTopLeftRadius: 24, borderTopRightRadius: 24, minHeight: '100%' },

  card: {
    backgroundColor: '#fff',
    borderRadius: 20,
    padding: 18,
    marginBottom: 14,
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.06,
    shadowRadius: 12,
    elevation: 3,
  },
  cardHeader: { marginBottom: 14 },
  orderNumRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 },
  orderNum: { fontSize: 22, fontWeight: '900', color: '#0f172a', letterSpacing: -0.5 },
  timeLabel: { fontSize: 12, color: '#94a3b8', fontWeight: '500' },
  statusPill: { flexDirection: 'row', alignItems: 'center', alignSelf: 'flex-start', gap: 6, paddingHorizontal: 12, paddingVertical: 6, borderRadius: 10 },
  statusIcon: { fontSize: 10 },
  statusLabel: { fontSize: 13, fontWeight: '700' },

  cardDivider: { height: 1, backgroundColor: '#f1f5f9', marginBottom: 14 },

  customerBlock: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 14 },
  avatar: { width: 42, height: 42, borderRadius: 14, backgroundColor: '#0f172a', justifyContent: 'center', alignItems: 'center' },
  avatarLetter: { fontSize: 17, fontWeight: '800', color: '#fb923c' },
  custName: { fontSize: 16, fontWeight: '700', color: '#1e293b' },
  custAddress: { fontSize: 13, color: '#94a3b8', marginTop: 2 },

  cardFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingTop: 14, borderTopWidth: 1, borderTopColor: '#f1f5f9' },
  chipRow: { flexDirection: 'row', gap: 6 },
  chip: { backgroundColor: '#f1f5f9', paddingHorizontal: 10, paddingVertical: 5, borderRadius: 8 },
  chipText: { fontSize: 12, fontWeight: '600', color: '#64748b' },
  chipCash: { backgroundColor: '#fef9c3' },
  chipCashText: { color: '#854d0e' },
  totalPrice: { fontSize: 20, fontWeight: '900', color: '#ea580c' },

  emptyWrap: { flex: 1, backgroundColor: '#f1f5f9', borderTopLeftRadius: 24, borderTopRightRadius: 24, justifyContent: 'center', alignItems: 'center', padding: 40 },
  emptyCircle: { width: 100, height: 100, borderRadius: 50, backgroundColor: '#fff', justifyContent: 'center', alignItems: 'center', marginBottom: 24, shadowColor: '#0f172a', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.08, shadowRadius: 16, elevation: 4 },
  emptyTitle: { fontSize: 22, fontWeight: '800', color: '#0f172a', marginBottom: 8 },
  emptySub: { fontSize: 15, color: '#94a3b8', textAlign: 'center', lineHeight: 22, marginBottom: 24 },
  refreshBtn: { backgroundColor: '#ea580c', paddingHorizontal: 28, paddingVertical: 14, borderRadius: 14 },
  refreshBtnText: { color: '#fff', fontSize: 15, fontWeight: '700' },
});
