# Stage 1: Build frontend assets
FROM node:20-alpine AS frontend

WORKDIR /build
COPY package*.json ./
RUN npm ci
COPY resources/ resources/
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

# Stage 2: PHP runtime
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    yt-dlp \
    python3 \
    py3-pip \
    sqlite \
    libzip-dev \
    && docker-php-ext-install zip pdo_sqlite

# Copy PHP config
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy app
COPY . .
COPY --from=frontend /build/public/build /var/www/public/build

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Storage permissions
RUN mkdir -p storage/app/transcripts storage/framework/cache/data storage/framework/sessions storage/framework/views database \
    && chmod -R 775 storage bootstrap/cache database \
    && chown -R www-data:www-data storage bootstrap/cache database

# Config
COPY nginx.conf /etc/nginx/http.d/default.conf
COPY supervisord.conf /etc/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
