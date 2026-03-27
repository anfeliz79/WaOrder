#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════════
# WaOrder — Server Setup Script for Ubuntu 24.04 (DigitalOcean Droplet)
#
# Instala: PHP 8.4, MySQL 8, Nginx, Composer, Node 22, Supervisor, Certbot
#
# USO:
#   1. SSH al droplet:  ssh root@TU_IP
#   2. Copiar este archivo al servidor
#   3. Ejecutar:  chmod +x setup-server.sh && ./setup-server.sh
#
# DESPUÉS de ejecutar esto, sigue con deploy.sh
# ═══════════════════════════════════════════════════════════════════════════════

set -e  # Detener si hay error

# ── Colores para output ──────────────────────────────────────────────────────
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

step() { echo -e "\n${GREEN}═══ $1 ═══${NC}\n"; }
warn() { echo -e "${YELLOW}⚠  $1${NC}"; }
err()  { echo -e "${RED}✗  $1${NC}"; }

# ── Verificar que estamos como root ──────────────────────────────────────────
if [ "$EUID" -ne 0 ]; then
    err "Ejecuta como root: sudo ./setup-server.sh"
    exit 1
fi

# ── Preguntar datos ──────────────────────────────────────────────────────────
echo ""
echo "╔══════════════════════════════════════════════╗"
echo "║       WaOrder — Instalación del Servidor     ║"
echo "╚══════════════════════════════════════════════╝"
echo ""

read -p "Dominio (ej: waorder.com, o deja vacío para usar IP): " DOMAIN
read -p "Contraseña para MySQL (usuario 'waorder'): " MYSQL_PASS

if [ -z "$MYSQL_PASS" ]; then
    MYSQL_PASS=$(openssl rand -base64 16)
    warn "Contraseña MySQL generada: $MYSQL_PASS"
    warn "¡GUÁRDALA! No se mostrará de nuevo."
fi

APP_USER="waorder"
APP_DIR="/var/www/waorder"
REPO_URL=""

read -p "URL del repositorio Git (ej: https://github.com/tu-user/WaOrder.git): " REPO_URL

# ═══════════════════════════════════════════════════════════════════════════════
step "1/9 — Actualizando sistema"
# ═══════════════════════════════════════════════════════════════════════════════
apt update && apt upgrade -y

# ═══════════════════════════════════════════════════════════════════════════════
step "2/9 — Instalando PHP 8.4 + extensiones"
# ═══════════════════════════════════════════════════════════════════════════════
apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update

apt install -y \
    php8.4-fpm \
    php8.4-cli \
    php8.4-mysql \
    php8.4-pgsql \
    php8.4-sqlite3 \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-curl \
    php8.4-zip \
    php8.4-bcmath \
    php8.4-gd \
    php8.4-intl \
    php8.4-redis \
    php8.4-tokenizer \
    php8.4-common \
    unzip \
    git \
    curl

# Configurar PHP para producción
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 10M/' /etc/php/8.4/fpm/php.ini
sed -i 's/post_max_size = .*/post_max_size = 12M/' /etc/php/8.4/fpm/php.ini
sed -i 's/memory_limit = .*/memory_limit = 256M/' /etc/php/8.4/fpm/php.ini
sed -i 's/max_execution_time = .*/max_execution_time = 60/' /etc/php/8.4/fpm/php.ini

systemctl restart php8.4-fpm
systemctl enable php8.4-fpm

echo -e "${GREEN}✓ PHP $(php -v | head -1 | cut -d' ' -f2) instalado${NC}"

# ═══════════════════════════════════════════════════════════════════════════════
step "3/9 — Instalando MySQL 8"
# ═══════════════════════════════════════════════════════════════════════════════
apt install -y mysql-server

systemctl start mysql
systemctl enable mysql

# Crear base de datos y usuario
mysql -e "CREATE DATABASE IF NOT EXISTS waorder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'waorder'@'localhost' IDENTIFIED BY '${MYSQL_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON waorder.* TO 'waorder'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo -e "${GREEN}✓ MySQL instalado — DB: waorder, User: waorder${NC}"

# ═══════════════════════════════════════════════════════════════════════════════
step "4/9 — Instalando Nginx"
# ═══════════════════════════════════════════════════════════════════════════════
apt install -y nginx

systemctl start nginx
systemctl enable nginx

echo -e "${GREEN}✓ Nginx instalado${NC}"

# ═══════════════════════════════════════════════════════════════════════════════
step "5/9 — Instalando Composer"
# ═══════════════════════════════════════════════════════════════════════════════
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

echo -e "${GREEN}✓ Composer $(composer --version 2>/dev/null | cut -d' ' -f3) instalado${NC}"

# ═══════════════════════════════════════════════════════════════════════════════
step "6/9 — Instalando Node.js 22 (para Vite build)"
# ═══════════════════════════════════════════════════════════════════════════════
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs

echo -e "${GREEN}✓ Node $(node -v) + npm $(npm -v) instalados${NC}"

# ═══════════════════════════════════════════════════════════════════════════════
step "7/9 — Instalando Supervisor (para queue workers)"
# ═══════════════════════════════════════════════════════════════════════════════
apt install -y supervisor

systemctl start supervisor
systemctl enable supervisor

echo -e "${GREEN}✓ Supervisor instalado${NC}"

# ═══════════════════════════════════════════════════════════════════════════════
step "8/9 — Configurando usuario y proyecto"
# ═══════════════════════════════════════════════════════════════════════════════

# Crear usuario del sistema (sin login, para correr la app)
if ! id "$APP_USER" &>/dev/null; then
    useradd -r -s /bin/bash -d /var/www "$APP_USER"
fi

# Crear directorio de la app
mkdir -p "$APP_DIR"

# Clonar repositorio si se proporcionó URL
if [ -n "$REPO_URL" ]; then
    if [ -d "$APP_DIR/.git" ]; then
        warn "El repositorio ya existe, haciendo git pull..."
        cd "$APP_DIR" && git pull
    else
        git clone "$REPO_URL" "$APP_DIR"
    fi
fi

# Permisos
chown -R "$APP_USER":www-data "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" 2>/dev/null || true
chmod -R 775 "$APP_DIR/bootstrap/cache" 2>/dev/null || true

# Configurar Nginx
if [ -n "$DOMAIN" ]; then
    SERVER_NAME="$DOMAIN"
else
    SERVER_NAME="_"
    warn "Sin dominio — Nginx servirá en la IP directa"
fi

cat > /etc/nginx/sites-available/waorder <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${SERVER_NAME};
    root ${APP_DIR}/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;
    charset utf-8;

    # Archivos estáticos — caché largo
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 12M;
}
NGINX

ln -sf /etc/nginx/sites-available/waorder /etc/nginx/sites-enabled/waorder
rm -f /etc/nginx/sites-enabled/default

nginx -t && systemctl reload nginx

echo -e "${GREEN}✓ Nginx configurado para ${SERVER_NAME}${NC}"

# Configurar Supervisor para queue worker
cat > /etc/supervisor/conf.d/waorder-worker.conf <<SUPER
[program:waorder-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${APP_DIR}/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=${APP_USER}
numprocs=1
redirect_stderr=true
stdout_logfile=${APP_DIR}/storage/logs/worker.log
stdout_logfile_maxbytes=5MB
stdout_logfile_backups=3
stopwaitsecs=3600
SUPER

supervisorctl reread
supervisorctl update

echo -e "${GREEN}✓ Supervisor configurado — worker se iniciará después del deploy${NC}"

# Configurar Cron para Laravel Scheduler
(crontab -u "$APP_USER" -l 2>/dev/null; echo "* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1") | sort -u | crontab -u "$APP_USER" -

echo -e "${GREEN}✓ Cron configurado — scheduler corre cada minuto${NC}"

# ═══════════════════════════════════════════════════════════════════════════════
step "9/9 — Configurando Firewall"
# ═══════════════════════════════════════════════════════════════════════════════
ufw --force enable
ufw allow ssh
ufw allow 'Nginx Full'

echo -e "${GREEN}✓ Firewall activado (SSH + HTTP + HTTPS)${NC}"

# ═══════════════════════════════════════════════════════════════════════════════
# RESUMEN
# ═══════════════════════════════════════════════════════════════════════════════
echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║              ✓ SERVIDOR INSTALADO CORRECTAMENTE             ║"
echo "╠══════════════════════════════════════════════════════════════╣"
echo "║                                                            ║"
echo "║  PHP:        $(php -v | head -1 | cut -d' ' -f2)                                   ║"
echo "║  MySQL:      waorder / waorder / ${MYSQL_PASS}             ║"
echo "║  Proyecto:   ${APP_DIR}                          ║"
echo "║  Nginx:      ${SERVER_NAME}                                ║"
echo "║                                                            ║"
echo "║  PRÓXIMOS PASOS:                                           ║"
echo "║                                                            ║"
echo "║  1. Copia .env.production al servidor:                     ║"
echo "║     scp .env.production root@IP:${APP_DIR}/.env            ║"
echo "║                                                            ║"
echo "║  2. Ejecuta el deploy:                                     ║"
echo "║     cd ${APP_DIR} && bash deploy/deploy.sh                 ║"
echo "║                                                            ║"
if [ -n "$DOMAIN" ]; then
echo "║  3. Instala SSL (gratis):                                  ║"
echo "║     certbot --nginx -d ${DOMAIN}                           ║"
echo "║                                                            ║"
fi
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""

# Guardar credenciales en archivo temporal
cat > /root/.waorder-credentials <<CREDS
# WaOrder Server Credentials — GUARDA ESTO Y BORRA ESTE ARCHIVO
MySQL Database: waorder
MySQL User:     waorder
MySQL Password: ${MYSQL_PASS}
App Directory:  ${APP_DIR}
CREDS
chmod 600 /root/.waorder-credentials

warn "Credenciales guardadas en /root/.waorder-credentials — GUÁRDALAS y borra ese archivo"
