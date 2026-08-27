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
touch var/log/soia_poll.log && chown www-data:www-data var/log/soia_poll.log
service cron start

echo "==> Listo. Aplicacion en http://localhost:8000"

exec "$@"
