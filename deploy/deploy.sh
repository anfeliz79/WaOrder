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
# ═══════════════════════════════════════════════════════════════════════════════

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

step() { echo -e "\n${GREEN}▸ $1${NC}"; }
warn() { echo -e "${YELLOW}⚠ $1${NC}"; }

APP_DIR="/var/www/waorder"
cd "$APP_DIR"

echo ""
echo "╔══════════════════════════════════════╗"
echo "║      WaOrder — Deploy en curso       ║"
echo "╚══════════════════════════════════════╝"
echo ""

# ── Verificar que existe .env ─────────────────────────────────────────────────
if [ ! -f .env ]; then
    echo -e "${RED}✗ No existe el archivo .env${NC}"
    echo "  Copia el template: cp deploy/.env.production .env"
    echo "  Y edita los valores: nano .env"
    exit 1
fi

# ── Modo mantenimiento (la app muestra 'Volvemos pronto' mientras se actualiza)
step "Activando modo mantenimiento..."
php artisan down --retry=30 2>/dev/null || true

# ── Git pull ──────────────────────────────────────────────────────────────────
step "Bajando últimos cambios de Git..."
git pull origin main 2>/dev/null || git pull origin master 2>/dev/null || warn "No se pudo hacer git pull (¿rama correcta?)"

# ── Composer (dependencias PHP) ───────────────────────────────────────────────
step "Instalando dependencias PHP..."
composer install --no-dev --optimize-autoloader --no-interaction

# ── npm (dependencias JS + compilar frontend) ─────────────────────────────────
step "Instalando dependencias JS y compilando frontend..."
npm ci --production=false
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

# ── Reiniciar workers ─────────────────────────────────────────────────────────
step "Reiniciando workers de cola..."
php artisan queue:restart

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
