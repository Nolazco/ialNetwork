#!/usr/bin/env bash
#
# Cron de keep-alive para el puente de WhatsApp (scripts/whatsapp-bridge-server.js).
# El hosting compartido no ofrece un gestor de procesos persistente (tipo
# Passenger) para apps Node fuera de las que registra hPanel, así que este
# cron (cada 5 minutos, ver crontab de producción) revisa si el proceso sigue
# vivo y lo reinicia si no.
#
# Instalar en producción con:
#   crontab -e
#   */5 * * * * /home/u461862926/whatsapp-bridge/keepalive.sh >> /home/u461862926/whatsapp-bridge/keepalive.log 2>&1

set -euo pipefail

APP_DIR="/home/u461862926/whatsapp-bridge"
NODE_BIN="/opt/alt/alt-nodejs20/root/usr/bin/node"

# Ojo: el pgrep de abajo busca "$APP_DIR/server.js" literal en el comando, asi
# que el propio nohup tiene que lanzarlo con esa misma ruta absoluta -- si se
# lanzara como "server.js" a secas (tras un cd), este mismo chequeo dejaria de
# reconocerlo como vivo en la siguiente corrida y el cron duplicaria el
# proceso cada 5 minutos (el duplicado truena solo con EADDRINUSE, pero
# ensucia el log sin necesidad).
if ! pgrep -f "$APP_DIR/server.js" > /dev/null; then
    echo "[$(date -Iseconds)] No esta corriendo, reiniciando..."
    cd "$APP_DIR"
    set -a
    source "$APP_DIR/.env"
    set +a
    nohup "$NODE_BIN" "$APP_DIR/server.js" >> "$APP_DIR/server.log" 2>&1 &
    disown
fi
