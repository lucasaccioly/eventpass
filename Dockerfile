# syntax=docker/dockerfile:1

# 1. PHP dependencies (production only). Extensions are checked in the final stage.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --ignore-platform-reqs

# 2. Front-end build. PHP is needed because the Wayfinder Vite plugin runs artisan.
FROM dunglas/frankenphp:php8.4 AS assets
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor vendor
RUN composer dump-autoload --no-dev --optimize \
    && npm run build

# 3. Runtime: FrankenPHP (web) + Horizon (queue) + scheduler, under supervisord.
FROM dunglas/frankenphp:php8.4
# The image ships no php.ini; without one, display_errors is on in production.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && install-php-extensions pdo_pgsql redis gd zip pcntl \
    && apt-get update \
    && apt-get install -y --no-install-recommends supervisor \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor vendor
COPY --from=assets /app/public/build public/build
RUN composer dump-autoload --no-dev --optimize \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
ENV PORT=8000
EXPOSE 8000
CMD ["sh", "/app/docker/start.sh"]
