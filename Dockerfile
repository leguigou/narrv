# Stage 1: Build frontend assets
FROM node:20-alpine AS frontend

WORKDIR /build
COPY package*.json ./
RUN npm ci
COPY resources/ resources/
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

# Stage 2: PHP runtime
FROM php:8.4-fpm-alpine

# Install system dependencies (need sqlite-dev for php ext compilation)
RUN apk add --no-cache \
    nginx \
    supervisor \
    python3 \
    py3-pip \
    py3-virtualenv \
    ca-certificates \
    nodejs \
    sqlite \
    sqlite-dev \
    oniguruma-dev \
    libzip-dev \
    && node --version \
    && python3 -m virtualenv /opt/yt-dlp \
    && /opt/yt-dlp/bin/pip install --no-cache-dir --upgrade pip \
    && /opt/yt-dlp/bin/pip install --no-cache-dir "yt-dlp[default]" "curl_cffi" \
    && ln -s /opt/yt-dlp/bin/yt-dlp /usr/local/bin/yt-dlp \
    && docker-php-ext-install -j$(nproc) zip pdo_sqlite mbstring

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
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
