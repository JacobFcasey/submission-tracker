# ── Stage 1: Build frontend assets ──────────────────────────────────────────
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ── Stage 2: Install PHP dependencies ──────────────────────────────────────
FROM composer:2 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
    --ignore-platform-req=ext-gd --ignore-platform-req=ext-bcmath --ignore-platform-req=ext-mailparse
COPY . .
RUN composer dump-autoload --optimize

# ── Stage 3: Production image ──────────────────────────────────────────────
FROM php:8.4-cli-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libonig-dev \
    libc-client-dev \
    libkrb5-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pdo_mysql \
        pgsql \
        zip \
        gd \
        mbstring \
        xml \
        bcmath \
        imap \
        pcntl

# Install mailparse via PECL
RUN pecl install mailparse && docker-php-ext-enable mailparse

WORKDIR /var/www/html

# Copy application code
COPY --from=composer /app /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build

# Set permissions
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Expose Render's port
EXPOSE 10000

# Start Laravel using the built-in server (Render injects PORT)
CMD php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan migrate --force && \
    php artisan serve --host=0.0.0.0 --port=${PORT:-10000}
