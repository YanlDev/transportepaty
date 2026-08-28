#!/usr/bin/env bash
#
# Deploy de Transpaty en el VPS de Contabo (/var/www/transpaty).
# Se ejecuta como `deploy` (sin root), vía GitHub Actions
# (.github/workflows/deploy.yml) o manualmente:
#   cd /var/www/transpaty && ./deploy.sh
#
set -euo pipefail

APP_DIR=/var/www/transpaty

cd "$APP_DIR"

echo "→ Activando modo mantenimiento"
php artisan down --retry=15 --refresh=30 || true

trap 'echo "✗ Deploy FALLÓ — la app sigue en mantenimiento. Revisa el log."; exit 1' ERR

echo "→ Trayendo últimos cambios de origin/main"
git fetch --all --prune
git reset --hard origin/main

# El build de Vite corre el plugin de Wayfinder, que genera los actions/routes
# de TypeScript a partir de las rutas que Laravel tiene cargadas en ESE
# momento. Si queda el caché de rutas del deploy anterior (route:cache, más
# abajo), un commit que agregue una ruta nueva genera un actions/*.ts sin esa
# ruta —aunque el código fuente sí la importe— y el build revienta dejando la
# app en mantenimiento. Por eso se limpia acá, antes del build, no solo al
# final.
echo "→ Limpiando cachés antes de compilar (Wayfinder necesita ver las rutas del commit actual)"
php artisan optimize:clear

echo "→ Dependencias PHP (producción)"
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

echo "→ Dependencias Node y build de assets"
npm ci
npm run build

echo "→ Migraciones"
php artisan migrate --force

echo "→ Limpiando y regenerando cachés"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "→ Symlink de storage (idempotente)"
if [ ! -L "$APP_DIR/public/storage" ]; then
    php artisan storage:link
fi

echo "→ Permisos (storage y bootstrap/cache escribibles por el grupo www-data)"
sudo chown -R deploy:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
sudo chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

echo "→ Recargando PHP-FPM (limpia OPcache)"
sudo systemctl reload php8.4-fpm

php artisan up
echo "✓ Deploy completado: $(git log -1 --pretty=format:'%h - %s')"
