FROM php:8.2-fpm

WORKDIR /var/www

RUN apt-get update && apt-get install -y \
    curl zip unzip git libpng-dev libonig-dev \
    libxml2-dev libzip-dev libsodium-dev

RUN docker-php-ext-install \
    pdo pdo_mysql mbstring zip exif pcntl \
    bcmath gd sockets sodium

RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

COPY . .

RUN cp .env.example .env && php artisan key:generate --ansi

RUN composer install --no-dev --optimize-autoloader

EXPOSE 8000

CMD cp .env.example .env && \
    echo "DB_CONNECTION=mysql" >> .env && \
    echo "DB_HOST=${DB_HOST}" >> .env && \
    echo "DB_PORT=${DB_PORT}" >> .env && \
    echo "DB_DATABASE=${DB_DATABASE}" >> .env && \
    echo "DB_USERNAME=${DB_USERNAME}" >> .env && \
    echo "DB_PASSWORD=${DB_PASSWORD}" >> .env && \
    echo "SESSION_DRIVER=${SESSION_DRIVER}" >> .env && \
    echo "APP_ENV=${APP_ENV}" >> .env && \
    echo "APP_DEBUG=false" >> .env && \
    php artisan key:generate --ansi && \
    php artisan migrate --force && \
    php artisan serve --host=0.0.0.0 --port=8000 --tries=0
