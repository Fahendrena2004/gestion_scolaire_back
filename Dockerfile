# Multi-stage build for Laravel backend
FROM php:8.5-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    build-base \
    curl \
    git \
    mysql-client \
    mariadb-dev \
    oniguruma-dev \
    pkgconfig \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install \
    bcmath \
    mbstring \
    pdo \
    pdo_mysql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy application files
COPY . .

# Development stage
FROM base AS development

# Set permissions
RUN chmod -R 755 /app/storage /app/bootstrap/cache

# Expose port
EXPOSE 8000

CMD ["php-fpm"]

# Production stage (optional)
FROM base AS production

# Install production-only dependencies
RUN composer install --optimize-autoloader --no-dev

# Set permissions
RUN chmod -R 755 /app/storage /app/bootstrap/cache

EXPOSE 8000

CMD ["php-fpm"]
