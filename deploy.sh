#!/usr/bin/env bash
#
# Deploy de FlotaVehicular en el VPS Contabo (/var/www/flotascx).
# Lo ejecuta el usuario `deploy`, vía GitHub Actions (.github/workflows/deploy.yml)
# o manualmente:  cd /var/www/flotascx && ./deploy.sh
#
# Requisitos en el server (una sola vez):
#   - Primer montaje hecho a mano (clone, .env, key:generate, migrate, seed,
#     storage:link) — ver provision-flotascx.sh / finish-flotascx.sh.
#   - /etc/sudoers.d/deploy-flotascx con NOPASSWD para chown/chmod de storage y
#     bootstrap/cache, supervisorctl restart flotascx-worker:* y (global)
#     systemctl reload php8.4-fpm.
#
set -euo pipefail

cd /var/www/flotascx

echo "→ Activando modo mantenimiento"
php artisan down --retry=15 || true

# Si algo falla, NO levantamos la app: se queda en mantenimiento (mejor que un 500).
trap 'echo "✗ Deploy FALLÓ — la app sigue en mantenimiento. Revisa el log."; exit 1' ERR

echo "→ Trayendo últimos cambios de origin/main"
git fetch --all --prune
git reset --hard origin/main

echo "→ Limpiando cachés ANTES de construir (clave: rutas frescas para Wayfinder)"
php artisan optimize:clear

echo "→ Dependencias PHP (producción)"
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

echo "→ Build de assets (Node) — Wayfinder ya lee rutas frescas"
npm ci
npm run build

echo "→ Migraciones"
php artisan migrate --force

echo "→ Regenerando cachés de producción"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "→ Symlink de storage (fotos)"
php artisan storage:link || true

echo "→ Permisos de storage y bootstrap/cache"
sudo chown -R deploy:www-data /var/www/flotascx/storage /var/www/flotascx/bootstrap/cache
sudo chmod -R 775 /var/www/flotascx/storage /var/www/flotascx/bootstrap/cache

echo "→ Reiniciando queue worker"
sudo supervisorctl restart flotascx-worker:* || true

echo "→ Recargando PHP-FPM (limpia OPcache)"
sudo systemctl reload php8.4-fpm

# Sólo llegamos aquí si TODO fue bien:
php artisan up
echo "✓ Deploy completado"
