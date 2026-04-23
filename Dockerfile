FROM php:8.2-fpm

WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl zip unzip git libpng-dev libonig-dev \
    libxml2-dev libzip-dev libsodium-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (NO redis extension on purpose)
RUN docker-php-ext-install \
    pdo pdo_mysql mbstring zip exif pcntl \
    bcmath gd sockets sodium

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer files first (better caching)
COPY composer.json composer.lock ./

RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy full project
COPY . .

# 🔥 REMOVE any accidental .env (prevents override of Render ENV)
RUN rm -f .env

# 🔥 FORCE reinstall to ensure predis is installed
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 🔥 Clear Laravel caches so ENV is always read fresh
RUN php artisan config:clear && \
    php artisan cache:clear && \
    php artisan route:clear && \
    php artisan view:clear

EXPOSE 8000

# 🔥 Keep runtime simple (no migrations here)
CMD php artisan config:clear && \
    php artisan cache:clear && \
    php artisan serve --host=0.0.0.0 --port=8000