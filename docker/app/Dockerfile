FROM php:8.3-fpm-alpine

# Install system dependencies (postgresql-client needed for test entrypoint pg_isready/psql)
RUN apk add --no-cache \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    oniguruma-dev \
    autoconf \
    g++ \
    make \
    linux-headers \
    postgresql-client

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    pcntl \
    bcmath \
    zip \
    mbstring \
    opcache

# Install Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

# Install ALL deps (including --dev) so tests can run inside Docker
RUN composer install --optimize-autoloader --no-interaction

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
