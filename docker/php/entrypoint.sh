#!/bin/sh
set -e

cd /app

# --- Dependencias de PHP ---------------------------------------------------
if [ ! -f vendor/autoload_runtime.php ]; then
    echo "==> vendor/ ausente: ejecutando composer install (esto tarda la primera vez)"
    composer install --no-interaction --prefer-dist
fi

# --- Dependencias de JS ----------------------------------------------------
if [ ! -d node_modules/.bin ]; then
    echo "==> node_modules/ ausente: ejecutando npm install"
    npm install --no-audit --no-fund
fi

# --- Assets compilados -----------------------------------------------------
if [ ! -f public/build/manifest.json ]; then
    echo "==> public/build ausente: compilando assets con Encore"
    npm run dev
fi

# --- Directorios de escritura ---------------------------------------------
mkdir -p var/cache var/log public/uploads/empresas
chown -R www-data:www-data var public/uploads public/build 2>/dev/null || true

# --- Cron (consulta automatica al SOIA) -------------------------------------
# Apache queda como proceso principal (exec "$@" abajo); cron corre aparte en
# segundo plano, igual de vivo mientras el contenedor lo esté.
#
# cron no hereda las variables de entorno de este proceso (DATABASE_URL,
# MAILER_DSN... las inyecta compose.yaml aqui, no al demonio cron), asi que
# sin esto el job cae al DATABASE_URL de .env (127.0.0.1) y nunca logra
# conectarse a la base de datos: se vuelca el entorno actual a un archivo que
# el job carga antes de correr el comando.
printenv | sed "s/^\([^=]*\)=\(.*\)\$/export \1='\2'/" > /etc/container_env.sh
chmod 644 /etc/container_env.sh

touch var/log/soia_poll.log && chown www-data:www-data var/log/soia_poll.log
service cron start

echo "==> Listo. Aplicacion en http://localhost:8000"

exec "$@"
