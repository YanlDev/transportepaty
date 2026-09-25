#!/usr/bin/env bash
#
# Alta única del servicio de WhatsApp en el VPS: token compartido con Laravel,
# proceso en supervisor y permiso para que deploy.sh lo reinicie sin clave.
#
#   sudo bash /var/www/transpaty/whatsapp/instalar-servidor.sh
#
# Se puede correr de nuevo: reusa el token que ya esté en el .env.
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "Correr con sudo"; exit 1; }

APP=/var/www/transpaty
ENV_APP="$APP/.env"
CONF=/etc/supervisor/conf.d/transpaty-whatsapp.conf
SUDOERS=/etc/sudoers.d/transpaty-whatsapp
PUERTO=3100

# El .env solo se amplía con >> (nunca sed -i), así conserva su dueño.
token=$(grep -E '^WHATSAPP_SERVICIO_TOKEN=.+' "$ENV_APP" | cut -d= -f2- || true)

if [ -z "$token" ]; then
    token=$(openssl rand -hex 32)
    cp -a "$ENV_APP" "$ENV_APP.respaldo-$(date +%Y%m%d-%H%M%S)"
    grep -q '^WHATSAPP_SERVICIO_URL=' "$ENV_APP" \
        || printf '\nWHATSAPP_SERVICIO_URL=http://127.0.0.1:%s\n' "$PUERTO" >> "$ENV_APP"
    printf 'WHATSAPP_SERVICIO_TOKEN=%s\n' "$token" >> "$ENV_APP"
    echo "→ Token nuevo agregado al .env (respaldo al lado)"
fi

if [ ! -d "$APP/whatsapp/node_modules" ]; then
    echo "→ Instalando dependencias del servicio"
    sudo -u deploy bash -c "cd $APP/whatsapp && npm ci --omit=dev"
fi

install -d -o deploy -g www-data -m 775 "$APP/storage/app/private/whatsapp"

echo "→ Supervisor: $CONF"
cat > "$CONF" <<CONF
[program:transpaty-whatsapp]
command=/usr/bin/node $APP/whatsapp/servidor.js
directory=$APP/whatsapp
user=transpaty
environment=NODE_ENV="production",WHATSAPP_PUERTO="$PUERTO",WHATSAPP_SERVICIO_TOKEN="$token"
autostart=true
autorestart=true
startsecs=5
stopwaitsecs=10
redirect_stderr=true
stdout_logfile=/var/log/supervisor/transpaty-whatsapp.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
CONF
# Lleva el token: que no lo lea cualquier usuario del servidor.
chmod 600 "$CONF"

echo "→ Permiso para que deploy.sh reinicie el servicio"
echo 'deploy ALL=(root) NOPASSWD: /usr/bin/supervisorctl restart transpaty-whatsapp' > "$SUDOERS.tmp"
visudo -cf "$SUDOERS.tmp"
install -m 440 "$SUDOERS.tmp" "$SUDOERS"
rm -f "$SUDOERS.tmp"

supervisorctl reread
supervisorctl update transpaty-whatsapp
sleep 3
supervisorctl status transpaty-whatsapp

sudo -u deploy php "$APP/artisan" config:cache > /dev/null

echo "→ Respuesta del servicio:"
curl -s -H "Authorization: Bearer $token" "http://127.0.0.1:$PUERTO/estado"
echo
echo "✓ Listo. Vincula el número desde la app, en WhatsApp (menú de admin)."
