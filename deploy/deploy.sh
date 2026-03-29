#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════════
# WaOrder — Deploy Script
#
# Ejecuta esto cada vez que quieras actualizar la app en producción.
# Desde el servidor:  cd /var/www/waorder && bash deploy/deploy.sh
#
# Lo que hace:
#   1. Baja los últimos cambios de Git
#   2. Instala dependencias PHP (Composer) y JS (npm)
#   3. Compila el frontend (Vite build)
#   4. Corre las migraciones de base de datos
#   5. Reconstruye el caché (más rápido en producción)
#   6. Reinicia los workers de cola
#
# HALLAZGOS DE PRODUCCIÓN (resueltos aquí):
#   - Git "dubious ownership" cuando root opera un repo clonado por otro user
#   - Composer advierte cuando se ejecuta como root
#   - Vite build se cuelga en droplets de 1-2GB sin swap/límite de RAM
# ═══════════════════════════════════════════════════════════════════════════════

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

step() { echo -e "\n${GREEN}▸ $1${NC}"; }
warn() { echo -e "${YELLOW}⚠ $1${NC}"; }
err()  { echo -e "${RED}✗ $1${NC}"; }

APP_DIR="/var/www/waorder"
cd "$APP_DIR"

echo ""
echo "╔══════════════════════════════════════╗"
echo "║      WaOrder — Deploy en curso       ║"
echo "╚══════════════════════════════════════╝"
echo ""

# ── Verificar que existe .env ─────────────────────────────────────────────────
if [ ! -f .env ]; then
    err "No existe el archivo .env"
    echo "  Copia el template: cp deploy/.env.production .env"
    echo "  Y edita los valores: nano .env"
    exit 1
fi

# ── HALLAZGO: Git se queja de ownership cuando root opera un dir de otro user ─
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true

# ── HALLAZGO: Composer advierte si corre como root ───────────────────────────
export COMPOSER_ALLOW_SUPERUSER=1

# ── HALLAZGO: Vite build se cuelga en droplets con poca RAM ─────────────────
# Limitar Node a 512MB evita que el OOM killer mate el proceso
export NODE_OPTIONS="--max-old-space-size=512"

# ── Modo mantenimiento (la app muestra 'Volvemos pronto' mientras se actualiza)
step "Activando modo mantenimiento..."
php artisan down --retry=30 2>/dev/null || true

# ── Git pull ──────────────────────────────────────────────────────────────────
step "Bajando últimos cambios de Git..."
git fetch origin
git reset --hard origin/main 2>/dev/null || git reset --hard origin/master 2>/dev/null || { err "No se pudo actualizar el código desde Git"; exit 1; }

# ── Composer (dependencias PHP) ───────────────────────────────────────────────
step "Instalando dependencias PHP..."
composer install --no-dev --optimize-autoloader --no-interaction

# ── npm (dependencias JS + compilar frontend) ─────────────────────────────────
step "Instalando dependencias JS..."
npm ci --production=false

step "Compilando frontend (Vite build)..."
echo -e "${YELLOW}  ℹ En droplets de 1-2GB esto puede tomar 2-5 minutos. Si se cuelga,"
echo -e "  verifica que el swap esté activo: swapon --show${NC}"
npm run build

# ── Migraciones (actualizar tablas de la base de datos) ───────────────────────
step "Ejecutando migraciones..."
php artisan migrate --force

# ── Storage link ──────────────────────────────────────────────────────────────
if [ ! -L public/storage ]; then
    step "Creando symlink de storage..."
    php artisan storage:link
fi

# ── Reconstruir caché (producción más rápida) ─────────────────────────────────
step "Reconstruyendo caché..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ── Reiniciar PHP-FPM (limpia OPcache — CRÍTICO para que el nuevo código aplique)
step "Reiniciando PHP-FPM..."
systemctl restart php8.4-fpm || systemctl restart php8.3-fpm || warn "No se pudo reiniciar PHP-FPM (verifica el servicio)"

# ── Reiniciar workers ─────────────────────────────────────────────────────────
# CRÍTICO: Los workers corren como proceso de larga vida con el código en memoria.
# Sin este reinicio, los jobs seguirán ejecutando el código VIEJO aunque PHP-FPM
# ya tenga el código nuevo. Usamos supervisorctl para reinicio inmediato.
step "Reiniciando workers de cola..."
php artisan queue:restart
supervisorctl restart all 2>/dev/null || warn "supervisorctl no disponible — workers se recargarán en el próximo job"

# ── Permisos ──────────────────────────────────────────────────────────────────
step "Ajustando permisos..."
chown -R waorder:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# ── Salir de mantenimiento ────────────────────────────────────────────────────
step "Desactivando modo mantenimiento..."
php artisan up

echo ""
echo "╔══════════════════════════════════════╗"
echo "║      ✓ DEPLOY COMPLETADO             ║"
echo "╚══════════════════════════════════════╝"
echo ""
echo -e "${GREEN}La app está en línea.${NC}"
echo ""
