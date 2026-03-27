import React, { useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  RefreshControl,
  ActivityIndicator,
  SafeAreaView,
  StatusBar,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { getApi } from '../services/api';
import { useAuthStore } from '../store/useAuthStore';

interface HistoryOrder {
  id: number;
  order_number: string;
  customer_name: string;
  total: number;
  payment_method: string;
  completed_at: string;
  items_count: number;
}

export default function HistoryScreen() {
  const [orders, setOrders] = useState<HistoryOrder[]>([]);
  const [stats, setStats] = useState({ total_deliveries: 0, today: 0 });
  const [loading, setLoading] = useState(false);
  const currency = useAuthStore((s) => s.tenant?.currency) || 'DOP';
  const sym = currency === 'DOP' ? 'RD$' : '$';

  const fetchHistory = useCallback(async () => {
    setLoading(true);
    try {
      const response = await getApi().get('/orders/history');
      setOrders(response.data.orders.data || []);
      setStats(response.data.stats);
    } catch (error) { console.error(error); }
    finally { setLoading(false); }
  }, []);

  useFocusEffect(useCallback(() => { fetchHistory(); }, [fetchHistory]));

  const formatDate = (s: string) => {
    const d = new Date(s);
    return d.toLocaleDateString('es-DO', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
  };

  return (
    <SafeAreaView style={styles.safe}>
      <StatusBar barStyle="light-content" />

      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Historial</Text>
        <View style={styles.statsRow}>
          <View style={styles.statBox}>
            <Text style={styles.statNum}>{stats.today}</Text>
            <Text style={styles.statLabel}>Hoy</Text>
          </View>
          <View style={styles.statDivider} />
          <View style={styles.statBox}>
            <Text style={styles.statNum}>{stats.total_deliveries}</Text>
            <Text style={styles.statLabel}>Total</Text>
          </View>
        </View>
      </View>

      {/* List */}
      <View style={styles.content}>
        {loading && orders.length === 0 ? (
          <View style={styles.emptyWrap}><ActivityIndicator size="large" color="#ea580c" /></View>
        ) : orders.length === 0 ? (
          <View style={styles.emptyWrap}>
            <View style={styles.emptyCircle}><Text style={{ fontSize: 32 }}>📋</Text></View>
            <Text style={styles.emptyTitle}>Sin historial</Text>
            <Text style={styles.emptySub}>Tus entregas completadas apareceran aqui</Text>
          </View>
        ) : (
          <FlatList
            data={orders}
            keyExtractor={(item) => item.id.toString()}
            renderItem={({ item }) => (
              <View style={styles.card}>
                <View style={styles.cardTop}>
                  <View style={styles.cardLeft}>
                    <Text style={styles.orderNum}>#{item.order_number}</Text>
                    <Text style={styles.custName}>{item.customer_name}</Text>
                  </View>
                  <Text style={styles.total}>{sym}{Number(item.total).toFixed(0)}</Text>
                </View>
                <View style={styles.cardBottom}>
                  <Text style={styles.meta}>{item.items_count} productos</Text>
                  <Text style={styles.meta}>
                    {item.payment_method === 'cash' ? '💵' : '💳'} {item.payment_method === 'cash' ? 'Efectivo' : item.payment_method}
                  </Text>
                  <Text style={styles.metaDate}>{item.completed_at ? formatDate(item.completed_at) : '-'}</Text>
                </View>
              </View>
            )}
            contentContainerStyle={styles.list}
            showsVerticalScrollIndicator={false}
            refreshControl={<RefreshControl refreshing={loading} onRefresh={fetchHistory} tintColor="#ea580c" />}
          />
        )}
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#0f172a' },
  header: { backgroundColor: '#0f172a', paddingHorizontal: 20, paddingBottom: 20 },
  headerTitle: { fontSize: 26, fontWeight: '800', color: '#f8fafc', marginBottom: 16 },
  statsRow: { flexDirection: 'row', backgroundColor: '#1e293b', borderRadius: 16, padding: 16, alignItems: 'center' },
  statBox: { flex: 1, alignItems: 'center' },
  statNum: { fontSize: 28, fontWeight: '900', color: '#fb923c' },
  statLabel: { fontSize: 12, color: '#64748b', fontWeight: '600', marginTop: 2 },
  statDivider: { width: 1, height: 30, backgroundColor: '#334155' },

  content: { flex: 1, backgroundColor: '#f1f5f9', borderTopLeftRadius: 24, borderTopRightRadius: 24 },
  list: { padding: 16, paddingTop: 20 },

  card: { backgroundColor: '#fff', borderRadius: 16, padding: 16, marginBottom: 10, shadowColor: '#0f172a', shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.04, shadowRadius: 6, elevation: 1 },
  cardTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 10 },
  cardLeft: {},
  orderNum: { fontSize: 16, fontWeight: '800', color: '#0f172a' },
  custName: { fontSize: 14, color: '#64748b', marginTop: 2 },
  total: { fontSize: 18, fontWeight: '800', color: '#ea580c' },
  cardBottom: { flexDirection: 'row', gap: 12, borderTopWidth: 1, borderTopColor: '#f1f5f9', paddingTop: 10 },
  meta: { fontSize: 12, color: '#64748b', fontWeight: '500' },
  metaDate: { fontSize: 12, color: '#94a3b8', marginLeft: 'auto' },

  emptyWrap: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40, minHeight: 400 },
  emptyCircle: { width: 80, height: 80, borderRadius: 40, backgroundColor: '#fff', justifyContent: 'center', alignItems: 'center', marginBottom: 20, shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.06, shadowRadius: 12, elevation: 3 },
  emptyTitle: { fontSize: 20, fontWeight: '700', color: '#0f172a', marginBottom: 6 },
  emptySub: { fontSize: 14, color: '#94a3b8', textAlign: 'center' },
});
