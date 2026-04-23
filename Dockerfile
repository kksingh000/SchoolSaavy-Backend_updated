FROM php:8.2-fpm

WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl zip unzip git libpng-dev libonig-dev \
    libxml2-dev libzip-dev libsodium-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo pdo_mysql mbstring zip exif pcntl \
    bcmath gd sockets sodium

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 🔥 COPY FULL PROJECT FIRST (IMPORTANT)
COPY . .

# 🔥 REMOVE any .env (so Render ENV is used)
RUN rm -f .env

# 🔥 NOW install dependencies (artisan exists now)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 🔥 Clear Laravel caches
RUN php artisan config:clear && \
    php artisan cache:clear && \
    php artisan route:clear && \
    php artisan view:clear

EXPOSE 8000

CMD php artisan config:clear && \
    php artisan cache:clear && \
    php artisan serve --host=0.0.0.0 --port=8000