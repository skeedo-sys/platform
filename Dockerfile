# Multi-stage build for Skeedo platform

# Stage 1: Build stage with PHP, Node.js and dependencies
FROM php:8.2-fpm as builder

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    curl \
    git \
    zip \
    unzip \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    zlib1g-dev \
    libpng-dev \
    ligjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libz-dev \
    libmemcached-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required by the application
RUN docker-php-ext-configure gd \
      --with-freetype \
      --with-jpeg \
      --with-webp \
    && docker-php-ext-install -j$(nproc) \
        intl \
        gd \
        bcmath \
        opcache \
        pdo_mysql

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js and npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node dependencies and build assets
RUN npm install \
    && npm run build

# Stage 2: Runtime stage
FROM php:8.2-fpm-alpine

# Install runtime dependencies
RUN apk add --no-cache \
    ca-certificates \
    curl \
    libintl \
    libpng \
    freetype \
    jpeg-dev \
    oniguruma \
    mysql-client \
    redis \
    && apk add --no-cache --virtual .build-deps \
    icu-dev \
    oniguruma-dev \
    libxml2-dev \
    zlib-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    && docker-php-ext-configure gd \
      --with-freetype \
      --with-jpeg \
      --with-webp \
    && docker-php-ext-install -j$(nproc) \
        intl \
        gd \
        bcmath \
        opcache \
        pdo_mysql \
    && apk del .build-deps

# Copy PHP configuration
COPY docker/php.ini /usr/local/etc/php/conf.d/99-skeedo.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/99-skeedo.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Create app directory
WORKDIR /app

# Copy built application from builder stage
COPY --from=builder /app /app

# Create necessary directories and set permissions
RUN mkdir -p \
    /app/var \
    /app/storage \
    /app/public \
    && chown -R www-data:www-data /app \
    && chmod -R 755 /app/var \
    && chmod -R 755 /app/storage

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:9000 || exit 1

# Expose FPM port (use reverse proxy/nginx for HTTP)
EXPOSE 9000

# Run entrypoint script
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
