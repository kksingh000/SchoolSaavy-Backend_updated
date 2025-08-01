#!/bin/bash

# Startup script for SchoolSavvy with RoadRunner
echo "🚀 Starting SchoolSavvy with RoadRunner..."

# Ensure proper permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Wait for Redis to be available
echo "⏳ Waiting for Redis..."
until nc -z redis 6379; do
    echo "Waiting for Redis to be ready..."
    sleep 2
done
echo "✅ Redis is ready!"

# Wait for database to be available
echo "⏳ Waiting for Database..."
until nc -z ${DB_HOST} ${DB_PORT}; do
    echo "Waiting for database to be ready..."
    sleep 2
done
echo "✅ Database is ready!"

# Clear and cache Laravel configurations
echo "🔧 Configuring Laravel..."
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Clear application cache
php artisan cache:clear

# Optimize autoloader
composer dump-autoload --optimize --no-dev

# Start RoadRunner with Octane
echo "🏁 Starting RoadRunner server..."
exec php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8080 --workers=4
