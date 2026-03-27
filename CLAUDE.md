# WaOrder — CLAUDE.md

Plataforma SaaS multi-tenant para pedidos de restaurantes vía WhatsApp. Los clientes piden por WhatsApp mediante un chatbot con máquina de estados. El dueño gestiona todo desde un panel admin web.

## Stack

| Capa | Tecnología |
|------|-----------|
| Backend | Laravel 13 (PHP 8.3+) |
| Frontend | Vue 3 + Inertia.js 3.0 + Tailwind CSS 4.2 |
| Build | Vite 8 |
| Auth web | Laravel Sanctum (sesiones) |
| Auth driver | Laravel Sanctum (tokens) |
| Queue | Laravel Queue |
| Cache | Redis/Database |
| WhatsApp | Meta Cloud API (Graph API v19.0) |

## Arquitectura del Proyecto

```
app/
├── Http/Controllers/
│   ├── Api/           — Controllers para admin API (Orders, Menu, Drivers, etc.)
│   └── Webhook/       — WhatsAppWebhookController (entrada de mensajes)
├── Jobs/
│   ├── ProcessWhatsAppMessage.php   — Procesa mensaje entrante (async)
│   ├── SendWhatsAppNotification.php — Envía mensaje saliente (async, 3 reintentos)
│   └── CleanExpiredSessions.php     — Limpia sesiones expiradas (scheduled)
├── Models/            — Eloquent models con BelongsToTenant trait
├── Services/
│   ├── Conversation/  — ConversationEngine + 10 Handlers (state machine)
│   ├── Menu/          — MenuService, InternalMenuSource, ExternalMenuSource, MenuTokenService
│   ├── Order/         — OrderFactory, OrderStateMachine, OrderOrchestrator
│   ├── Session/       — SessionManager
│   ├── Notification/  — NotificationService, DriverNotifier, PushNotificationService
│   └── WhatsApp/      — WhatsAppClient, WebhookPayloadParser, MessageFactory
resources/js/
├── Layouts/AdminLayout.vue
├── Pages/             — Páginas Inertia (Dashboard, Orders, Menu, Drivers, etc.)
└── Components/        — UI components compartidos
routes/
├── api.php            — Driver app + Admin API
├── web.php            — Admin panel (Inertia)
└── webhook.php        — WhatsApp webhook
```

## Multi-Tenancy

Base de datos compartida con aislamiento por `tenant_id` en cada tabla.

- **`BelongsToTenant` trait** — Global scope automático en todos los modelos, auto-popula `tenant_id` en creación.
- **`IdentifyTenant` middleware** — Resuelve tenant por: usuario auth → subdominio → Bearer token → primer tenant activo.
- Todos los modelos tenant-aware usan este trait. **Nunca** hacer queries sin tenant resuelto.

## Conversation State Machine (Chatbot WhatsApp)

10 estados procesados por handlers individuales:

```
greeting → menu_browsing → item_selection → [modifier_selection] → cart_review
  → collecting_info (nombre→entrega→dirección→pago→notas)
  → confirmation → order_active → order_closed → survey → greeting
```

- **ConversationEngine** — Router principal, despacha al handler correcto según `ChatSession.conversation_state`
- **Reset global:** "cancelar todo" / "salir" / "empezar de nuevo" → regresa a `greeting` desde cualquier estado
- **Horario de negocio:** Rechaza mensajes fuera del horario configurado del tenant
- **Deduplicación:** Cache por `meta_message_id` (1h TTL)
- **Session TTL:** 30 min, limpieza via `CleanExpiredSessions` job

## Order State Machine

```
confirmed → in_preparation → ready → out_for_delivery → delivered (terminal)
                                                       ↘ cancelled (terminal, desde cualquier estado)
```

- Siempre usar `OrderOrchestrator` para cambiar estados — nunca modificar `Order.status` directamente.
- Cada transición se loguea en `OrderStatusHistory`.
- Cada transición dispara notificación WhatsApp al cliente via `NotificationService`.

## Modelos Clave

| Modelo | Propósito |
|--------|-----------|
| `Tenant` | Cuenta del restaurante — tiene WhatsApp credentials (encriptadas) y settings JSON |
| `Branch` | Sucursal física — zona de entrega (Haversine), settings con delivery_fee |
| `User` | Admin/Gestor — roles: `admin` (todo) o `gestor` (solo su sucursal) |
| `MenuItem` | Producto — precio, image_url, `modifiers` (JSON con variant_groups y optional_groups) |
| `Order` | Pedido — estado, precios calculados, driver asignado |
| `ChatSession` | Estado de conversación — `cart_data`, `collected_info`, `context_data` (todos JSON) |
| `Driver` | Repartidor — QR linking token, push_token FCM/APNs, Sanctum auth |
| `Customer` | Contacto WhatsApp — stats CRM (total_orders, total_spent) |
| `MessageLog` | Audit trail completo de todos los mensajes WhatsApp + métricas AI |

## Comandos Frecuentes

```bash
# Desarrollo
php artisan serve
npm run dev
php artisan queue:work

# Base de datos
php artisan migrate
php artisan db:seed

# Cache
php artisan cache:clear
php artisan config:clear
```

## Notas Importantes

- `Tenant.whatsapp_access_token` está **encriptado** en DB — usar `tenant->decryptedToken()` para acceder.
- El webhook WhatsApp requiere firma HMAC válida (`X-Hub-Signature-256`) — configurar `WHATSAPP_APP_SECRET`.
- Los tokens de menú público tienen TTL corto (15-30 min) y son de un solo uso temporal.
- Los drivers se autentican via QR code → Sanctum token. El `linking_token` es single-use y expira en 15 min.
- Gestors solo ven pedidos de su sucursal asignada (filtro via `ResolveBranch` middleware + `session('branch_id')`).
- Menu interno se cachea 10 minutos. Para forzar refresh: `php artisan cache:clear`.
