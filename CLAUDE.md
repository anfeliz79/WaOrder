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
│   ├── SuperAdmin/    — DashboardController, TenantController (gestión global)
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
├── Layouts/
│   ├── AdminLayout.vue       — Layout para admin/gestor de tenant
│   └── SuperAdminLayout.vue  — Layout para super admin (sidebar dark + amber)
├── Pages/
│   ├── SuperAdmin/    — Dashboard, Tenants (Index, Create, Edit)
│   └── ...            — Dashboard, Orders, Menu, Drivers, etc.
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
- **SuperAdmin bypass**: Si el usuario autenticado es `superadmin`, el global scope se desactiva para ver datos de todos los tenants. Usa `$guard->hasUser()` (no `auth()->user()`) para evitar recursión infinita.

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
| `User` | SuperAdmin/Admin/Gestor — roles: `superadmin`, `admin`, `gestor` |
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

# Super Admin
php artisan waorder:create-admin --super --email=tu@email.com --password=clave
php artisan waorder:create-admin --email=admin@rest.com --password=clave  # tenant admin

# Deploy
cd /var/www/waorder && bash deploy/deploy.sh
```

## AI / NLP (Opcional)

Capa de IA opcional que mejora el matching de ítems del menú cuando el regex + Levenshtein no encuentran coincidencia.

- **`AiService`** — Wrapper OpenAI-compatible (Groq recomendado por ser gratis). Fallback silencioso si no hay key.
- **Configuración por tenant**: Settings → IA/NLP → provider, modelo, API key (encriptada en DB).
- **Fallback global**: Si el tenant no tiene key, usa `GROQ_API_KEY` o `OPENAI_API_KEY` del `.env`.
- **Cache**: Respuestas de IA se cachean 24h para reducir costos.
- **Sin IA**: El chatbot funciona perfectamente con regex + fuzzy matching. La IA solo mejora el reconocimiento de texto libre.

## Super Admin (`/superadmin`)

Panel de gestión global de la plataforma. Permite administrar todos los restaurantes (tenants) desde una sola interfaz.

### Roles del Sistema

| Rol | Scope | Acceso | tenant_id |
|-----|-------|--------|-----------|
| `superadmin` | Plataforma completa | `/superadmin/*` — ve todos los tenants | `NULL` |
| `admin` | Su tenant | `/dashboard/*` — gestiona su restaurante | FK a tenants |
| `gestor` | Su(s) sucursal(es) | Dashboard + pedidos de sus sucursales asignadas | FK a tenants |

### Arquitectura SuperAdmin

```
app/Http/Controllers/SuperAdmin/
├── DashboardController.php    — Stats globales (tenants, users, orders, customers)
└── TenantController.php       — CRUD completo de tenants con admin + sucursal

app/Http/Middleware/
└── EnsureSuperAdmin.php       — Protege rutas /superadmin/*

resources/js/
├── Layouts/SuperAdminLayout.vue        — Layout dedicado (sidebar dark + amber)
└── Pages/SuperAdmin/
    ├── Dashboard.vue                   — Stat cards + tablas recientes
    └── Tenants/
        ├── Index.vue                   — Lista paginada con búsqueda y filtros
        ├── Create.vue                  — Crear tenant + admin + sucursal
        └── Edit.vue                    — Editar tenant, ver stats/usuarios/sucursales
```

### Middleware Chain para SuperAdmin

1. `auth` — Requiere sesión activa
2. `EnsureSuperAdmin` — Verifica `role === 'superadmin'`
3. `IdentifyTenant` — Salta (SuperAdmin no necesita tenant)
4. `ResolveBranch` — Salta (SuperAdmin no tiene sucursal)
5. `EnsureSetupComplete` — Salta (SuperAdmin no pasa por setup wizard)
6. `BelongsToTenant` scope — Se desactiva via `$guard->hasUser()` check

### Login SuperAdmin

El login estándar (`/login`) maneja los 3 roles:
- Si `Auth::attempt` falla (scope de tenant filtra usuarios), busca SuperAdmin sin scope (`User::withoutGlobalScope('tenant')`)
- SuperAdmin → redirige a `/superadmin`
- Admin/Gestor → redirige a `/dashboard` o `/select-branch`

### Crear SuperAdmin

```bash
php artisan waorder:create-admin --super --email=tu@email.com --password=TuClave --name="Tu Nombre"
```

### Crear Nuevo Restaurante (Tenant)

Desde el panel SuperAdmin → Restaurantes → Nuevo Restaurante:
1. Datos del restaurante (nombre, slug, timezone, moneda, plan)
2. Admin del restaurante (nombre, email, contraseña)
3. Sucursal inicial (opcional)

O via Artisan para el primer tenant:
```bash
php artisan waorder:create-admin --email=admin@restaurante.com --password=clave123
```

### Hallazgos Técnicos del SuperAdmin

| Problema | Causa | Solución |
|----------|-------|----------|
| Login SuperAdmin fallaba | `BelongsToTenant` scope filtraba `tenant_id=NULL` | Fallback query `User::withoutGlobalScope('tenant')` en AuthController |
| OOM (256MB) al acceder `/superadmin` | `auth()->user()` dentro del scope `BelongsToTenant` → recursión infinita al cargar User | Usar `$guard->hasUser()` que verifica si el user ya está en memoria sin triggerar query |
| SuperAdmin veía dashboard de tenant | Sesión redirigía a `/dashboard` en vez de `/superadmin` | AuthController verifica `isSuperAdmin()` y redirige correctamente |
| Toast/flash no visible al guardar settings | `AdminLayout` renderizaba `<AppToast>` pero no conectaba `page.props.flash` al composable `useToast()` | Agregar `watch` sobre `page.props.flash` que dispara `toast.success()`/`toast.error()` |

## Panel de Sistema (`/system`)

Dashboard de administración del servidor accesible desde el admin panel:

- **Checklist de producción**: 7 verificaciones automáticas (env, debug, DB, migrations, storage, queue, WhatsApp)
- **Acciones**: Ejecutar migraciones, limpiar/reconstruir caché, reiniciar workers, crear storage link
- **Logs**: Visor de `laravel.log` y `worker.log` en tiempo real
- **Auto-refresh**: Estado se actualiza cada 30 segundos

## Deploy a Producción

### Requisitos del Servidor

| Componente | Versión |
|-----------|---------|
| Ubuntu | 24.04 LTS |
| PHP | 8.4 + FPM |
| MySQL | 8.x |
| Nginx | latest |
| Node.js | 22.x (para Vite build) |
| Composer | latest |
| Supervisor | latest (mantiene queue:work) |
| Certbot | latest (SSL Let's Encrypt) |

**RAM mínima**: 1GB + 2GB swap (sin swap, Vite build se cuelga en OOM).

### Scripts de Deploy

```
deploy/
├── setup-server.sh      — Provisiona servidor desde cero (solo 1 vez)
├── deploy.sh            — Actualiza la app (cada deploy)
└── .env.production      — Template de .env para producción
```

### Primer Deploy (paso a paso)

```bash
# 1. SSH al droplet
ssh root@TU_IP

# 2. Descargar y ejecutar el setup
curl -sO https://raw.githubusercontent.com/anfeliz79/WaOrder/main/deploy/setup-server.sh
chmod +x setup-server.sh && ./setup-server.sh

# 3. Configurar .env
cp /var/www/waorder/deploy/.env.production /var/www/waorder/.env
nano /var/www/waorder/.env
#   → Cambiar DB_PASSWORD (la que generó setup-server.sh)
#   → Cambiar APP_URL (https://tudominio.com o http://TU_IP)

# 4. Generar key y deploy
cd /var/www/waorder
php artisan key:generate
bash deploy/deploy.sh

# 5. Crear admin inicial
php artisan waorder:create-admin

# 6. SSL (si tienes dominio)
certbot --nginx -d tudominio.com --non-interactive --agree-tos -m tu@email.com
```

### Deploys Subsiguientes

```bash
cd /var/www/waorder && bash deploy/deploy.sh
```

### Hallazgos de Producción (DigitalOcean)

| Problema | Causa | Solución |
|----------|-------|----------|
| Vite build se cuelga | OOM en droplets 1-2GB | Crear swap 2GB (`fallocate -l 2G /swapfile`) + `NODE_OPTIONS="--max-old-space-size=512"` |
| Git "dubious ownership" | Root clona, otro user opera | `git config --global --add safe.directory /var/www/waorder` |
| Composer warning como root | Composer detecta root | `export COMPOSER_ALLOW_SUPERUSER=1` |
| `php artisan key:generate` falla | No hay vendor/ | Ejecutar `composer install` primero |
| Login sin credenciales | No se creó admin | `php artisan waorder:create-admin` |
| `waorder:create-admin` falla con error 1364 | Campos WhatsApp NOT NULL sin valor | Migración ya los hace nullable; si persiste: `ALTER TABLE tenants MODIFY whatsapp_phone_number_id VARCHAR(50) NULL` |
| Login SuperAdmin falla "credenciales no coinciden" | `BelongsToTenant` scope filtra `tenant_id=NULL` | AuthController hace fallback: `User::withoutGlobalScope('tenant')` para buscar superadmins |
| OOM 256MB al cargar cualquier página | `auth()->user()` en BelongsToTenant scope → recursión infinita | Usar `$guard->hasUser()` en vez de `auth()->check()` dentro del global scope |
| Guardar settings no muestra feedback | `AdminLayout` no conectaba `page.props.flash` con `useToast()` | Agregar `watch` en AdminLayout que bridge flash → toast |

### SSL con Let's Encrypt

```bash
# Instalar certificado (Certbot ya viene con setup-server.sh)
certbot --nginx -d tudominio.com --non-interactive --agree-tos -m tu@email.com

# Verificar auto-renovación
certbot renew --dry-run

# La renovación automática corre via systemd timer (certbot.timer)
```

### Subdominio desde SiteGround u otro hosting

Si el dominio principal está en SiteGround y quieres usar un subdominio (ej: `waorder.tudominio.com`):

1. En SiteGround → Zone Editor → crear **registro A** apuntando a la IP del droplet
2. Esperar propagación DNS (5-30 min): `dig waorder.tudominio.com +short`
3. En el droplet ejecutar `certbot --nginx -d waorder.tudominio.com`

### Estructura de Servicios en Producción

- **Nginx**: Reverse proxy → PHP-FPM. Config en `/etc/nginx/sites-available/waorder`
- **PHP-FPM**: Pool por defecto en socket unix `/var/run/php/php8.4-fpm.sock`
- **Supervisor**: Mantiene `queue:work` corriendo 24/7. Config en `/etc/supervisor/conf.d/waorder-worker.conf`
- **Cron**: Laravel scheduler cada minuto via `crontab -u waorder`
- **Credenciales**: Guardadas en `/root/.waorder-credentials` (borrar después de anotar)

### Multi-Tenancy en Producción

Un solo deploy sirve múltiples restaurantes. Para agregar un nuevo restaurante:
1. Login como SuperAdmin → `/superadmin/tenants/create`
2. Llenar datos del restaurante + admin + sucursal inicial
3. El admin del restaurante configura WhatsApp desde su panel → Configuración
4. Los tenants comparten infraestructura — no se necesita otro droplet

## Registro de Bugs Resueltos

Historial cronológico de todos los bugs encontrados y resueltos durante el desarrollo y deploy de WaOrder.

### Bug #1: Login SuperAdmin — "Las credenciales no coinciden" (commit `d591bcc`)

**Contexto**: Al intentar logear con un usuario SuperAdmin (`tenant_id = NULL`), el login siempre fallaba con "Las credenciales no coinciden".

**Causa raíz**: El trait `BelongsToTenant` añade un global scope al modelo `User` que filtra por `tenant_id`. Cuando `Auth::attempt()` busca el usuario por email, el scope automáticamente añade `WHERE tenant_id = ?` pero como el SuperAdmin tiene `tenant_id = NULL`, nunca lo encuentra.

**Solución**: Modificar `AuthController@login` para hacer un fallback cuando `Auth::attempt()` falla — buscar explícitamente un SuperAdmin sin el scope:

```php
// En app/Http/Controllers/Api/AuthController.php
if (!Auth::attempt($credentials, $request->boolean('remember'))) {
    $superAdmin = User::withoutGlobalScope('tenant')
        ->where('email', $credentials['email'])
        ->where('role', 'superadmin')
        ->first();

    if ($superAdmin && Hash::check($credentials['password'], $superAdmin->password)) {
        Auth::login($superAdmin, $request->boolean('remember'));
    } else {
        return back()->withErrors(['email' => 'Las credenciales no coinciden.']);
    }
}
```

**Archivos modificados**: `app/Http/Controllers/Api/AuthController.php`

---

### Bug #2: OOM 256MB — Recursión infinita en BelongsToTenant (commit `5c31923`)

**Contexto**: Después de hacer login como SuperAdmin (o cualquier usuario), al acceder a cualquier página el servidor devolvía 500 con `Allowed memory size of 268435456 bytes exhausted`.

**Causa raíz**: Dentro del global scope de `BelongsToTenant`, se usaba `auth()->user()` para verificar si el usuario era SuperAdmin. Pero `auth()->user()` ejecuta una query SQL para cargar el modelo `User`. Como `User` también usa el trait `BelongsToTenant`, el scope se dispara de nuevo → llama `auth()->user()` de nuevo → scope de nuevo → **recursión infinita** hasta OOM.

```
BelongsToTenant scope → auth()->user() → SELECT * FROM users WHERE...
  → BelongsToTenant scope → auth()->user() → SELECT * FROM users WHERE...
    → BelongsToTenant scope → auth()->user() → ... → OOM
```

**Solución**: Reemplazar `auth()->user()` con `$guard->hasUser()` que solo verifica si el usuario ya está resuelto **en memoria** sin disparar una nueva query:

```php
// En app/Models/Concerns/BelongsToTenant.php
static::addGlobalScope('tenant', function (Builder $builder) {
    $guard = auth()->guard();
    // hasUser() NO hace query — solo verifica si el user ya está en memoria
    if ($guard->hasUser() && $guard->user()->isSuperAdmin()) {
        return; // SuperAdmin bypassa el scope
    }
    if (app()->bound('tenant') && app('tenant')) {
        $builder->where($builder->getModel()->getTable() . '.tenant_id', app('tenant')->id);
    }
});
```

**Regla crítica**: **NUNCA** usar `auth()->user()`, `auth()->check()`, o `Auth::user()` dentro de un global scope en un modelo que tenga ese mismo scope. Siempre usar `auth()->guard()->hasUser()`.

**Archivos modificados**: `app/Models/Concerns/BelongsToTenant.php`

---

### Bug #3: Flash messages invisibles al guardar Settings (commit `c49ecc3`)

**Contexto**: Al guardar configuraciones en Configuración → Menú (o cualquier otra pestaña), el botón "Guardar" no mostraba ningún feedback visual. Los datos sí se guardaban correctamente en la base de datos, pero el usuario no recibía confirmación.

**Causa raíz**: El `AdminLayout.vue` renderizaba el componente `<AppToast />` para mostrar notificaciones toast, pero **nunca conectaba** los flash messages de Inertia (`page.props.flash.success/error`) al sistema de toasts (`useToast()` composable).

La cadena estaba rota:
```
Backend: return back()->with('success', 'Configuración actualizada')
    ↓
Inertia: page.props.flash.success = 'Configuración actualizada'
    ↓ ← AQUÍ SE ROMPÍA — nadie leía este valor
AppToast: useToast().toasts = [] ← siempre vacío
```

**Solución**: Agregar un `watch` en `AdminLayout.vue` que observe los flash props de Inertia y los envíe al sistema de toasts:

```javascript
// En resources/js/Layouts/AdminLayout.vue
import { ref, computed, watch } from 'vue';
import { useToast } from '@/Composables/useToast';

const page = usePage();
const toast = useToast();

watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        toast.success(flash.success);
    }
    if (flash?.error) {
        toast.error(flash.error);
    }
}, { deep: true, immediate: true });
```

**Nota**: El `SuperAdminLayout.vue` ya tenía su propio manejo de flash messages (con divs inline en vez de toasts), por lo que no tenía este problema.

**Archivos modificados**: `resources/js/Layouts/AdminLayout.vue`

---

### Bug #4: Roles incorrectos — Admin y SuperAdmin confundidos

**Contexto**: Después de crear el SuperAdmin con `waorder:create-admin --super`, el usuario admin del tenant (user ID 1) también quedó con `role = superadmin`, causando que viera el dashboard de SuperAdmin en vez del de su restaurante.

**Causa raíz**: El comando `waorder:create-admin --super` modificaba el usuario existente en vez de crear uno nuevo cuando el email coincidía. El user ID 1 (admin del tenant) fue convertido a SuperAdmin accidentalmente.

**Solución**: Corregir manualmente via tinker:

```php
php artisan tinker
$user = User::withoutGlobalScope('tenant')->find(1);
$user->update(['role' => 'admin', 'tenant_id' => 1]);
```

**Lección**: Siempre verificar el estado de los roles después de ejecutar comandos de creación de usuarios. Los 3 roles del sistema y sus `tenant_id` esperados:

| Rol | tenant_id |
|-----|-----------|
| `superadmin` | `NULL` |
| `admin` | FK a tenants (nunca NULL) |
| `gestor` | FK a tenants (nunca NULL) |

---

### Bug #5: Método isSuperAdmin() duplicado en User model

**Contexto**: Error de PHP `Cannot redeclare App\Models\User::isSuperAdmin()` al intentar cargar la aplicación.

**Causa raíz**: Un agente añadió el método `isSuperAdmin()` al modelo `User` sin verificar que ya existía. PHP no permite métodos duplicados.

**Solución**: Eliminar la declaración duplicada del método.

**Lección**: Siempre buscar si un método ya existe antes de añadirlo a un modelo.

**Archivos modificados**: `app/Models/User.php`

---

### Bug #6: Credenciales API externa rechazadas (401 Unauthorized)

**Contexto**: Al configurar la API externa de Yokomo Sushi en Settings → Menú, las primeras credenciales (api_key + api_secret) devolvían 401.

**Causa raíz**: Las credenciales iniciales proporcionadas por SelfOrder eran inválidas o habían expirado.

**Solución**: Regenerar las credenciales desde el panel de SelfOrder. Las nuevas credenciales funcionaron correctamente (200 OK, 68 productos).

**Verificación**:
```bash
curl -s -o /dev/null -w "%{http_code}" \
  -H "X-Api-Key: cat_jze8w6rbfqpbbhfwcfby0nobmcgvpebuiwl4" \
  -H "X-Api-Secret: sec_5qPdLWiUCo7vuQT0PV7reaPvZM8wRJlJeWmNzugKxe5q6CsNrziWlDDMPkcDcZiT" \
  "https://selforder.yokomosushi.com/integrations/catalog/products"
# Respuesta: 200
```

---

## Notas Importantes

- `Tenant.whatsapp_access_token` está **encriptado** en DB — usar `tenant->decryptedToken()` para acceder.
- `Tenant.ai_api_key` también está **encriptado** — usar cast automático de Laravel.
- El webhook WhatsApp requiere firma HMAC válida (`X-Hub-Signature-256`) — configurar `WHATSAPP_APP_SECRET`.
- Los tokens de menú público tienen TTL corto (15-30 min) y son de un solo uso temporal.
- Los drivers se autentican via QR code → Sanctum token. El `linking_token` es single-use y expira en 15 min.
- Gestors solo ven pedidos de su sucursal asignada (filtro via `ResolveBranch` middleware + `session('branch_id')`).
- Menu interno se cachea 10 minutos. Para forzar refresh: `php artisan cache:clear`.
- **CRÍTICO — BelongsToTenant scope**: **Nunca** usar `auth()->user()` o `auth()->check()` dentro del global scope de `BelongsToTenant`. Causa recursión infinita (el User model tiene el mismo trait → query infinita → OOM). Usar `auth()->guard()->hasUser()` que solo verifica si el user ya está cargado en memoria.
- **SuperAdmin tiene `tenant_id = NULL`**: El login estándar no lo encuentra porque el scope filtra por tenant. El `AuthController` hace un fallback explícito buscando sin scope.
