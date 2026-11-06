#!/usr/bin/env bash
#
# Deploy del ambiente de pruebas (/var/www/transpaty-test, rama `test`,
# https://trpatytest.aveja.cloud). Se ejecuta como `deploy`, vía GitHub
# Actions (.github/workflows/deploy-test.yml) o manualmente:
#   cd /var/www/transpaty-test && ./deploy-test.sh
#
# A diferencia de deploy.sh, acá no hay servicio de WhatsApp ni worker de la
# cola: el .env de test apunta el WhatsApp a un puerto muerto y deja la SOL
# vacía, así que nada de lo que se haga en pruebas sale hacia conductores,
# áreas ni SUNAT.
#
set -euo pipefail

APP_DIR=/var/www/transpaty-test

cd "$APP_DIR"

echo "→ Activando modo mantenimiento"
php artisan down --retry=15 --refresh=30 || true

trap 'echo "✗ Deploy de test FALLÓ — la app sigue en mantenimiento. Revisa el log."; exit 1' ERR

echo "→ Trayendo últimos cambios de origin/test"
git fetch --all --prune
git reset --hard origin/test

# Mismo motivo que en deploy.sh: Wayfinder tiene que ver las rutas del commit
# actual, no las del caché del deploy anterior.
echo "→ Limpiando cachés antes de compilar"
php artisan optimize:clear

echo "→ Dependencias PHP"
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

echo "→ Dependencias Node y build de assets"
npm ci
npm run build

echo "→ Migraciones"
php artisan migrate --force
php artisan permisos:sincronizar

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
echo "✓ Deploy de test completado: $(git log -1 --pretty=format:'%h - %s')"
