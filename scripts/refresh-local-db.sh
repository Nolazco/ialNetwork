#!/usr/bin/env bash
#
# Clona la base de datos de producción (Hostinger) a la base de datos local de
# Docker, reemplazándola por completo.
#
# Uso:
#   ./scripts/refresh-local-db.sh
#
# Requiere:
#   - La llave SSH de despliegue en ~/.ssh/ialnetwork_hostinger_ed25519
#   - El stack de Docker levantado (docker compose up -d)
#
# ADVERTENCIA: trae una copia COMPLETA de producción, con datos reales de
# clientes (correos, telefonos, RFCs, etc.). Uso exclusivo en tu maquina de
# desarrollo — nunca subas el dump a un repositorio ni a un servicio externo.

set -euo pipefail

cd "$(dirname "$0")/.."

SSH_KEY="$HOME/.ssh/ialnetwork_hostinger_ed25519"
SSH_PORT="65002"
SSH_TARGET="u461862926@156.67.74.172"

DUMP_FILE="backup.sql.gz"
LOCAL_DB_USER="app"
LOCAL_DB_PASSWORD="!ChangeMe!"
LOCAL_DB_NAME="app"

echo "==> Generando dump de producción (puede tardar segun el tamaño de la base)..."

# Todo el manejo de credenciales ocurre del lado del servidor: se leen de
# .env.local, se exportan solo dentro de esa sesion remota (MYSQL_PWD nunca
# aparece en esta terminal ni en el dump) y el unico dato que viaja de
# regreso es el SQL comprimido de mysqldump.
ssh -i "$SSH_KEY" -p "$SSH_PORT" -o BatchMode=yes "$SSH_TARGET" bash -s <<'REMOTE_EOF' > "$DUMP_FILE"
set -euo pipefail
cd /home/u461862926/ialnetwork-app

eval "$(php -r '
$env = file_get_contents(".env.local");
if (!preg_match("/^DATABASE_URL=\"(.*)\"\s*$/m", $env, $m)) {
    fwrite(STDERR, "No se encontro DATABASE_URL en .env.local\n");
    exit(1);
}
$parts = parse_url($m[1]);
printf(
    "export MYSQL_PWD=%s DB_USER=%s DB_NAME=%s DB_HOST=%s DB_PORT=%s\n",
    escapeshellarg(urldecode($parts["pass"] ?? "")),
    escapeshellarg(urldecode($parts["user"] ?? "")),
    escapeshellarg(ltrim($parts["path"] ?? "", "/")),
    escapeshellarg($parts["host"] ?? "127.0.0.1"),
    escapeshellarg((string) ($parts["port"] ?? 3306))
);
')"

# "localhost" fuerza el socket unix del propio servidor (asi conecta la app
# en produccion); pasarlo como -h intenta TCP y el usuario no tiene permiso
# desde esa direccion.
if [ "$DB_HOST" = "localhost" ]; then
    mysqldump --single-transaction --quick --no-tablespaces --skip-lock-tables \
        -u "$DB_USER" "$DB_NAME" | gzip -9
else
    mysqldump --single-transaction --quick --no-tablespaces --skip-lock-tables \
        -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" | gzip -9
fi
REMOTE_EOF

echo "==> Dump descargado en $DUMP_FILE ($(du -h "$DUMP_FILE" | cut -f1))"

echo "==> Vaciando la base de datos local..."
docker compose exec -T database mariadb -u "$LOCAL_DB_USER" -p"$LOCAL_DB_PASSWORD" \
    -e "DROP DATABASE IF EXISTS \`$LOCAL_DB_NAME\`; CREATE DATABASE \`$LOCAL_DB_NAME\`;"

echo "==> Restaurando el dump de producción en local..."
gunzip -c "$DUMP_FILE" | docker compose exec -T database mariadb -u "$LOCAL_DB_USER" -p"$LOCAL_DB_PASSWORD" "$LOCAL_DB_NAME"

echo "==> Alineando el historial de migraciones..."
# No se usa "migrate": el ledger de produccion tiene huecos historicos (hay
# migraciones viejas nunca marcadas como aplicadas aunque su cambio ya esta
# fisicamente en el esquema), y volver a ejecutarlas fallaria con "ya existe".
# Como el esquema restaurado ya refleja produccion, solo hace falta marcar
# como aplicado cualquier archivo de migracion local que el ledger no tenga.
docker compose exec app php bin/console doctrine:migrations:sync-metadata-storage
docker compose exec app php bin/console doctrine:migrations:version --add --all --no-interaction

echo "==> Listo. La base de datos local ahora es una copia de producción."
echo "    El dump quedo en $DUMP_FILE (ya esta en .gitignore) — bórralo si no lo necesitas."
