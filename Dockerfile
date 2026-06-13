# =============================================================
# RoboLeague — imagen de producción (multi-stage)
#
#  stage "build" : PHP 8.4 + Node 22  -> compila vendor (composer)
#                  y los assets de Vite (npm run build).
#                  Node es necesario para Vite, y PHP para Wayfinder,
#                  que ejecuta `php artisan` durante el build.
#  stage "app"   : php-fpm con el código + vendor + public/build.
#  stage "web"   : nginx con SOLO public/ (assets estáticos horneados,
#                  sin volúmenes compartidos) y proxy de PHP a "app".
#
# docker-compose construye "app" y "web" con `target:`; comparten el
# stage "build" (se compila una sola vez).
# =============================================================

# ---------- helper para extensiones PHP ----------
FROM mlocati/php-extension-installer:latest AS php-ext

# =============================================================
# Stage 1 — BUILD (PHP + Node)
# =============================================================
FROM php:8.4-cli-bookworm AS build

COPY --from=php-ext /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pdo_sqlite intl zip bcmath \
 && apt-get update && apt-get install -y --no-install-recommends git unzip ca-certificates \
 && rm -rf /var/lib/apt/lists/*

# Node 22 (copiado de la imagen oficial -> mismo Debian bookworm)
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# --- deps PHP (cacheable) ---
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# --- deps Node (cacheable) ---
COPY package.json package-lock.json ./
RUN npm ci

# --- código fuente completo ---
COPY . .

# Entorno mínimo para que `artisan` arranque durante el build
# (Wayfinder lo invoca). Sin tocar la BD real: usamos sqlite efímero.
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_KEY=base64:0000000000000000000000000000000000000000000= \
    INERTIA_SSR_ENABLED=false \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/app/database/build.sqlite

RUN touch /app/database/build.sqlite \
 && composer install --no-dev --optimize-autoloader --no-interaction \
 && npm run build \
 && rm -rf node_modules /app/database/build.sqlite

# =============================================================
# Stage 2 — APP (php-fpm)
# =============================================================
FROM php:8.4-fpm-bookworm AS app

COPY --from=php-ext /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl zip bcmath pcntl opcache

WORKDIR /var/www/html

# Código ya compilado (vendor + public/build incluidos)
COPY --from=build --chown=www-data:www-data /app /var/www/html

# Permisos de Laravel
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# Config PHP de producción
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

USER www-data
EXPOSE 9000
CMD ["php-fpm"]

# =============================================================
# Stage 3 — WEB (nginx con assets estáticos horneados)
# =============================================================
FROM nginx:1.27-alpine AS web

# nginx sirve los estáticos directamente y delega .php a "app:9000".
# Mismo path /var/www/html/public en ambos contenedores -> fpm encuentra el script.
COPY --from=build /app/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
