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

RUN chmod +x docker/scripts/render-start.sh

EXPOSE 8000

# Runtime boot is handled by the entrypoint script rather than an && chain, so
# that a database outage no longer exits the container and crash-loops it.
# Migrations still run on boot but are non-fatal; seeders are opt-in via
# RUN_SEEDERS=true. See docker/scripts/render-start.sh.
CMD ["./docker/scripts/render-start.sh"]