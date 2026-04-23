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

# Copy full project
COPY . .

# Remove .env (use Render ENV)
RUN rm -f .env

# Install dependencies (artisan exists now)
RUN composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 8000

# 🔥 ONLY run artisan commands at runtime (NOT build)
CMD php artisan config:clear && \
    php artisan migrate --force && \
    php artisan db:seed --class=SuperAdminSeeder --force && \
    php artisan db:seed --class=SchoolSeeder --force && \
    php artisan serve --host=0.0.0.0 --port=8000