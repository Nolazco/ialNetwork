# Imagen de desarrollo: Apache + PHP 8.3 + Composer + Node 20
FROM php:8.3-apache

# --- Dependencias del sistema ---------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        cron \
        curl \
        git \
        unzip \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
    && rm -rf /var/lib/apt/lists/*

# --- CAs adicionales -------------------------------------------------------
# Los antivirus y proxies que inspeccionan TLS (Norton, ESET, Zscaler...)
# reemplazan los certificados con los suyos, firmados por una raiz propia que
# el contenedor no conoce: composer y npm fallan con
# "unable to get local issuer certificate". Cualquier .crt que se deje en
# docker/certs/ se agrega aqui al almacen de confianza.
COPY docker/certs/ /usr/local/share/ca-certificates/extra/
RUN update-ca-certificates

# Node no usa el almacen del sistema, hay que apuntarlo al bundle combinado.
ENV NODE_EXTRA_CA_CERTS=/etc/ssl/certs/ca-certificates.crt

# --- Extensiones de PHP ----------------------------------------------------
# intl  -> symfony/intl        pdo_pgsql -> Doctrine + PostgreSQL
# zip   -> Composer            opcache   -> rendimiento
# gd    -> reescalar fotos antes de incrustarlas en el PDF del reporte de previo
RUN docker-php-ext-configure intl \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" intl pdo pdo_pgsql zip opcache gd

# --- Node 20 (Webpack Encore) ---------------------------------------------
# Se copia desde la imagen oficial en vez de bajarlo de deb.nodesource.com:
# en redes con inspeccion TLS (antivirus / proxy corporativo) curl falla al
# validar el certificado. node:20-bookworm-slim comparte base con php:8.3-apache.
COPY --from=node:20-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:20-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && node --version && npm --version

# --- Composer --------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Apache ----------------------------------------------------------------
RUN a2enmod rewrite headers
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

# --- Cron (consulta automatica al SOIA, ver app:soia:poll) -----------------
COPY docker/php/crontab /etc/cron.d/soia-poll
RUN chmod 0644 /etc/cron.d/soia-poll

# --- Arranque --------------------------------------------------------------
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /app

ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
