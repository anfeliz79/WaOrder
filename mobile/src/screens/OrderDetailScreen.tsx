import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Alert,
  Linking,
  Platform,
  SafeAreaView,
  StatusBar,
} from 'react-native';
import { useRoute, useNavigation } from '@react-navigation/native';
import { getApi } from '../services/api';
import { useOrdersStore, Order } from '../store/useOrdersStore';
import { useAuthStore } from '../store/useAuthStore';

export default function OrderDetailScreen() {
  const route = useRoute<any>();
  const navigation = useNavigation();
  const order: Order = route.params.order;
  const currency = useAuthStore((s) => s.tenant?.currency) || 'DOP';
  const sym = currency === 'DOP' ? 'RD$' : '$';
  const removeOrder = useOrdersStore((s) => s.removeOrder);
  const updateDriver = useAuthStore((s) => s.updateDriver);
  const [delivering, setDelivering] = useState(false);

  const openMaps = () => {
    const { delivery_latitude: lat, delivery_longitude: lng, delivery_address: addr } = order;
    let url: string;
    if (lat && lng) {
      url = Platform.select({ ios: `maps:?daddr=${lat},${lng}`, android: `google.navigation:q=${lat},${lng}` })
        || `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
    } else if (addr) {
      const e = encodeURIComponent(addr);
      url = Platform.select({ ios: `maps:?daddr=${e}`, android: `google.navigation:q=${e}` })
        || `https://www.google.com/maps/dir/?api=1&destination=${e}`;
    } else { return Alert.alert('Sin direccion'); }
    Linking.openURL(url).catch(() => {
      Linking.openURL(lat && lng
        ? `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`
        : `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(addr)}`);
    });
  };

  const callCustomer = () => order.customer_phone ? Linking.openURL(`tel:${order.customer_phone}`) : Alert.alert('Sin telefono');
  const whatsappCustomer = () => order.customer_phone && Linking.openURL(`https://wa.me/${order.customer_phone.replace(/[^0-9]/g, '')}`);

  const markDelivered = () => {
    Alert.alert('Confirmar entrega', `Marcar #${order.order_number} como entregado?`, [
      { text: 'Cancelar', style: 'cancel' },
      { text: 'Si, entregado', onPress: async () => {
        setDelivering(true);
        try {
          const res = await getApi().post(`/orders/${order.id}/delivered`);
          removeOrder(order.id);
          if (res.data.cash_reminder) {
            Alert.alert('💵 Cobro en efectivo', `Cobra ${sym}${Number(res.data.cash_amount || 0).toFixed(2)} al cliente.`,
              [{ text: 'Entendido', onPress: () => navigation.goBack() }]);
          } else {
            Alert.alert('Entregado', `#${order.order_number} completado.`, [{ text: 'OK', onPress: () => navigation.goBack() }]);
          }
        } catch (e: any) { Alert.alert('Error', e.response?.data?.message || 'Error'); }
        finally { setDelivering(false); }
      }},
    ]);
  };

  const payLabel = order.payment_method === 'cash' ? 'Efectivo' : order.payment_method === 'transfer' ? 'Transferencia' : order.payment_method;

  return (
    <SafeAreaView style={styles.safe}>
      <StatusBar barStyle="dark-content" />
      <ScrollView contentContainerStyle={styles.scroll} showsVerticalScrollIndicator={false}>

        {/* Hero */}
        <View style={styles.hero}>
          <View style={styles.heroRow}>
            <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
              <Text style={styles.backIcon}>←</Text>
            </TouchableOpacity>
            <Text style={styles.heroOrder}>#{order.order_number}</Text>
            <View style={{ width: 40 }} />
          </View>

          <View style={styles.heroBig}>
            <View style={styles.heroAvatar}>
              <Text style={styles.heroAvatarText}>{order.customer_name?.charAt(0)?.toUpperCase()}</Text>
            </View>
            <Text style={styles.heroName}>{order.customer_name}</Text>
            {order.delivery_address ? <Text style={styles.heroAddr} numberOfLines={2}>{order.delivery_address}</Text> : null}
          </View>

          <View style={styles.heroTotal}>
            <Text style={styles.heroTotalLabel}>Total del pedido</Text>
            <Text style={styles.heroTotalValue}>{sym}{Number(order.total).toFixed(2)}</Text>
          </View>
        </View>

        {/* Action buttons */}
        <View style={styles.actions}>
          {[
            { icon: '📍', label: 'Navegar', sub: 'Abrir mapa', color: '#3b82f6', bg: '#eff6ff', onPress: openMaps },
            { icon: '📞', label: 'Llamar', sub: 'Telefono', color: '#10b981', bg: '#ecfdf5', onPress: callCustomer },
            { icon: '💬', label: 'WhatsApp', sub: 'Mensaje', color: '#22c55e', bg: '#f0fdf4', onPress: whatsappCustomer },
          ].map((a) => (
            <TouchableOpacity key={a.label} style={styles.actionCard} onPress={a.onPress} activeOpacity={0.7}>
              <View style={[styles.actionIcon, { backgroundColor: a.bg }]}>
                <Text style={{ fontSize: 22 }}>{a.icon}</Text>
              </View>
              <Text style={styles.actionLabel}>{a.label}</Text>
              <Text style={styles.actionSub}>{a.sub}</Text>
            </TouchableOpacity>
          ))}
        </View>

        {/* Items */}
        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Productos</Text>
            <View style={styles.countBadge}>
              <Text style={styles.countText}>{order.items.length}</Text>
            </View>
          </View>

          {order.items.map((item, i) => (
            <View key={item.id} style={[styles.itemRow, i > 0 && { borderTopWidth: 1, borderTopColor: '#f1f5f9' }]}>
              <View style={styles.itemQty}><Text style={styles.itemQtyText}>{item.quantity}x</Text></View>
              <View style={{ flex: 1 }}>
                <Text style={styles.itemName}>{item.name}</Text>
                {item.modifiers ? <Text style={styles.itemMod}>{typeof item.modifiers === 'string' ? item.modifiers : ''}</Text> : null}
              </View>
              <Text style={styles.itemPrice}>{sym}{Number(item.subtotal).toFixed(0)}</Text>
            </View>
          ))}

          <View style={styles.totalBar}>
            {Number(order.delivery_fee) > 0 && (
              <View style={styles.feeRow}>
                <Text style={styles.feeLabel}>Delivery</Text>
                <Text style={styles.feeValue}>{sym}{Number(order.delivery_fee).toFixed(0)}</Text>
              </View>
            )}
            <View style={styles.feeRow}>
              <Text style={styles.totalLabel}>Total</Text>
              <Text style={styles.totalValue}>{sym}{Number(order.total).toFixed(2)}</Text>
            </View>
          </View>
        </View>

        {/* Payment info */}
        <View style={styles.section}>
          <View style={styles.payRow}>
            <Text style={{ fontSize: 20 }}>💳</Text>
            <View style={{ flex: 1 }}>
              <Text style={styles.payTitle}>Metodo de pago</Text>
              <Text style={styles.payMethod}>{payLabel}</Text>
            </View>
            {order.payment_method === 'cash' && (
              <View style={styles.cashWarn}>
                <Text style={styles.cashWarnText}>Cobrar al entregar</Text>
              </View>
            )}
          </View>
        </View>

        {order.notes ? (
          <View style={styles.section}>
            <View style={styles.payRow}>
              <Text style={{ fontSize: 20 }}>📝</Text>
              <View style={{ flex: 1 }}>
                <Text style={styles.payTitle}>Nota del cliente</Text>
                <Text style={styles.noteContent}>{order.notes}</Text>
              </View>
            </View>
          </View>
        ) : null}

      </ScrollView>

      {/* Bottom deliver button */}
      <View style={styles.bottom}>
        <TouchableOpacity
          style={[styles.deliverBtn, delivering && { opacity: 0.6 }]}
          onPress={markDelivered}
          disabled={delivering}
          activeOpacity={0.8}
        >
          <Text style={styles.deliverEmoji}>✅</Text>
          <Text style={styles.deliverText}>{delivering ? 'Procesando...' : 'Marcar Entregado'}</Text>
        </TouchableOpacity>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#f1f5f9' },
  scroll: { paddingBottom: 120 },

  hero: { backgroundColor: '#fff', paddingHorizontal: 20, paddingTop: 8, paddingBottom: 24, borderBottomLeftRadius: 28, borderBottomRightRadius: 28, shadowColor: '#0f172a', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.06, shadowRadius: 16, elevation: 4 },
  heroRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 },
  backBtn: { width: 40, height: 40, borderRadius: 14, backgroundColor: '#f1f5f9', justifyContent: 'center', alignItems: 'center' },
  backIcon: { fontSize: 20, color: '#0f172a' },
  heroOrder: { fontSize: 18, fontWeight: '800', color: '#0f172a' },
  heroBig: { alignItems: 'center', marginBottom: 20 },
  heroAvatar: { width: 64, height: 64, borderRadius: 22, backgroundColor: '#0f172a', justifyContent: 'center', alignItems: 'center', marginBottom: 12 },
  heroAvatarText: { fontSize: 26, fontWeight: '800', color: '#fb923c' },
  heroName: { fontSize: 22, fontWeight: '800', color: '#0f172a', textAlign: 'center' },
  heroAddr: { fontSize: 14, color: '#64748b', textAlign: 'center', marginTop: 4, lineHeight: 20, paddingHorizontal: 20 },
  heroTotal: { backgroundColor: '#0f172a', borderRadius: 16, padding: 16, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  heroTotalLabel: { fontSize: 14, color: '#94a3b8', fontWeight: '600' },
  heroTotalValue: { fontSize: 26, fontWeight: '900', color: '#fb923c' },

  actions: { flexDirection: 'row', gap: 10, padding: 16 },
  actionCard: { flex: 1, backgroundColor: '#fff', borderRadius: 18, padding: 14, alignItems: 'center', shadowColor: '#0f172a', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.04, shadowRadius: 8, elevation: 2 },
  actionIcon: { width: 50, height: 50, borderRadius: 18, justifyContent: 'center', alignItems: 'center', marginBottom: 8 },
  actionLabel: { fontSize: 13, fontWeight: '700', color: '#1e293b' },
  actionSub: { fontSize: 11, color: '#94a3b8', marginTop: 1 },

  section: { backgroundColor: '#fff', borderRadius: 20, marginHorizontal: 16, marginBottom: 12, padding: 18 },
  sectionHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 },
  sectionTitle: { fontSize: 16, fontWeight: '800', color: '#0f172a' },
  countBadge: { backgroundColor: '#ea580c', width: 26, height: 26, borderRadius: 13, justifyContent: 'center', alignItems: 'center' },
  countText: { fontSize: 13, fontWeight: '800', color: '#fff' },

  itemRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 12, gap: 10 },
  itemQty: { width: 32, height: 32, borderRadius: 10, backgroundColor: '#fff7ed', justifyContent: 'center', alignItems: 'center' },
  itemQtyText: { fontSize: 13, fontWeight: '900', color: '#ea580c' },
  itemName: { fontSize: 15, fontWeight: '600', color: '#1e293b' },
  itemMod: { fontSize: 12, color: '#94a3b8', marginTop: 1 },
  itemPrice: { fontSize: 15, fontWeight: '700', color: '#64748b' },

  totalBar: { borderTopWidth: 2, borderTopColor: '#f1f5f9', marginTop: 4, paddingTop: 12 },
  feeRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 4 },
  feeLabel: { fontSize: 13, color: '#94a3b8' },
  feeValue: { fontSize: 13, color: '#94a3b8' },
  totalLabel: { fontSize: 17, fontWeight: '800', color: '#0f172a' },
  totalValue: { fontSize: 22, fontWeight: '900', color: '#ea580c' },

  payRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  payTitle: { fontSize: 12, color: '#94a3b8', fontWeight: '600' },
  payMethod: { fontSize: 16, fontWeight: '700', color: '#1e293b', marginTop: 1 },
  cashWarn: { backgroundColor: '#fef3c7', paddingHorizontal: 10, paddingVertical: 5, borderRadius: 8 },
  cashWarnText: { fontSize: 11, fontWeight: '700', color: '#92400e' },
  noteContent: { fontSize: 14, color: '#475569', fontStyle: 'italic', marginTop: 2, lineHeight: 20 },

  bottom: { position: 'absolute', bottom: 0, left: 0, right: 0, backgroundColor: '#fff', borderTopWidth: 1, borderTopColor: '#e2e8f0', padding: 16, paddingBottom: Platform.OS === 'ios' ? 34 : 16 },
  deliverBtn: { backgroundColor: '#16a34a', paddingVertical: 18, borderRadius: 18, flexDirection: 'row', justifyContent: 'center', alignItems: 'center', gap: 8, shadowColor: '#16a34a', shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.35, shadowRadius: 12, elevation: 6 },
  deliverEmoji: { fontSize: 18 },
  deliverText: { fontSize: 17, fontWeight: '800', color: '#fff' },
});
