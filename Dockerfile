# ============================================================
# POSMoon - Imagen de produccion
# Multi-stage: dependencias PHP -> assets frontend -> runtime
# ============================================================

# --- Etapa 1: dependencias PHP -------------------------------
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# --- Etapa 2: assets frontend (Vite) -------------------------
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json ./
COPY package-lock.json* ./
RUN npm install --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# --- Etapa 3: runtime (PHP-FPM + nginx en un contenedor) -----
FROM serversideup/php:8.4-fpm-nginx AS app

# Extensiones extra que el POS va a necesitar (imagenes, locales, PDFs)
USER root
RUN install-php-extensions intl gd exif bcmath soap

# PHP timezone (evita timestamps en UTC/GMT+0)
COPY docker/php/zz-timezone.ini /usr/local/etc/php/conf.d/zz-timezone.ini

ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    HEALTHCHECK_PATH=/up \
    TZ=America/Argentina/Buenos_Aires

USER www-data

COPY --chown=www-data:www-data . /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

# AppImage >100MB: va en partes en git y se recompone acá
RUN set -e; \
    DIR=/var/www/html/public/descargas; \
    if [ -f "$DIR/POSMoon-Offline-Linux.AppImage.part0" ]; then \
      cat "$DIR"/POSMoon-Offline-Linux.AppImage.part* > "$DIR/POSMoon-Offline-Linux.AppImage"; \
      chmod +x "$DIR/POSMoon-Offline-Linux.AppImage"; \
      rm -f "$DIR"/POSMoon-Offline-Linux.AppImage.part*; \
    fi

RUN php artisan storage:link || true
