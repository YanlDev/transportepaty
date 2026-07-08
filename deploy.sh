#!/usr/bin/env bash
#
# Deploy de Flota (flotav3) en el server de la EMPRESA (elastika, /var/www/flotaselcosi).
# Se ejecuta como `root`, vía GitHub Actions (.github/workflows/deploy.yml)
# o manualmente:  cd /var/www/flotaselcosi && ./deploy.sh
#
# Requisitos en el server (una sola vez, ya hechos):
#   - Primer montaje a mano (clone con deploy key, .env, key:generate, migrate,
#     storage:link, import de datos).
#   - PHP 8.4 (php8.4-fpm) — Laravel 13 + Symfony 8 exige >= 8.4.1.
#   - Supervisor con el programa `flota-worker` (queue:work).
#
set -euo pipefail

APP_DIR=/var/www/flotaselcosi
PHP=php8.4
COMPOSER="php8.4 /usr/local/bin/composer"
export COMPOSER_ALLOW_SUPERUSER=1

cd "$APP_DIR"
# El repo es propiedad de www-data; permitir que root use git aquí.
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true

echo "→ Activando modo mantenimiento"
$PHP artisan down --retry=15 || true

# Si algo falla, NO levantamos la app: se queda en mantenimiento (mejor que un 500).
trap 'echo "✗ Deploy FALLÓ — la app sigue en mantenimiento. Revisa el log."; exit 1' ERR

echo "→ Trayendo últimos cambios de origin/main"
git fetch --all --prune
git reset --hard origin/main

echo "→ Limpiando cachés ANTES de construir (clave: rutas frescas para Wayfinder)"
$PHP artisan optimize:clear

echo "→ Dependencias PHP (producción)"
$COMPOSER install --no-interaction --prefer-dist --optimize-autoloader --no-dev

echo "→ Build de assets (Node) — Wayfinder ya lee rutas frescas"
npm ci
npm run build

echo "→ Migraciones"
$PHP artisan migrate --force

echo "→ Regenerando cachés de producción"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

echo "→ Symlink de storage (fotos)"
$PHP artisan storage:link --force || true

echo "→ Permisos (todo el árbol a www-data; storage y cache escribibles)"
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

echo "→ Reiniciando queue worker"
supervisorctl restart flota-worker:* || true

echo "→ Recargando PHP-FPM (limpia OPcache)"
systemctl reload php8.4-fpm

# Sólo llegamos aquí si TODO fue bien:
$PHP artisan up
echo "✓ Deploy completado"
