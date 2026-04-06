FROM php:8.2-fpm

WORKDIR /var/www

RUN apt-get update && apt-get install -y \
    curl zip unzip git libpng-dev libonig-dev \
    libxml2-dev libzip-dev libsodium-dev nginx

RUN docker-php-ext-install \
    pdo pdo_mysql mbstring zip exif pcntl \
    bcmath gd sockets sodium

RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

COPY . .

RUN cp .env.example .env

RUN composer install --no-dev --optimize-autoloader

RUN php artisan key:generate --ansi

EXPOSE 8000

CMD php artisan serve --host=0.0.0.0 --port=8000 --tries=0
