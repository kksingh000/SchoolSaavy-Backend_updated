#!/bin/bash

# SchoolSavvy Docker Deployment Script

set -e

echo "🚀 Starting SchoolSavvy deployment with external MySQL..."

# Check if .env.production exists
if [ ! -f .env.production ]; then
    echo "❌ .env.production file not found!"
    echo "📝 Please copy .env.production.example to .env.production and update with your MySQL credentials"
    exit 1
fi

# Load environment variables
export $(cat .env.production | grep -v '^#' | xargs)

# Validate required environment variables
if [ -z "$DB_HOST" ] || [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ]; then
    echo "❌ Missing required database configuration in .env.production"
    echo "📝 Please ensure DB_HOST, DB_USERNAME, and DB_PASSWORD are set"
    exit 1
fi

echo "✅ Environment configuration loaded"

# Test MySQL connection
echo "🔍 Testing MySQL connection..."
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" -e "SELECT 1;" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ MySQL connection successful"
else
    echo "❌ Failed to connect to MySQL server"
    echo "📝 Please check your database credentials in .env.production"
    exit 1
fi

# Build and start containers
echo "🐳 Building Docker containers..."
docker-compose down
docker-compose build --no-cache

echo "🚀 Starting services..."
docker-compose up -d redis nginx

echo "⏳ Waiting for Redis to be ready..."
sleep 10

echo "🚀 Starting main application..."
docker-compose up -d app queue scheduler

echo "⏳ Waiting for application to be ready..."
sleep 20

# Run migrations
echo "📊 Running database migrations..."
docker-compose exec app php artisan migrate --force

# Clear caches
echo "🧹 Clearing application caches..."
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache

# Check application health
echo "🔍 Checking application health..."
if curl -f http://localhost:8080/up > /dev/null 2>&1; then
    echo "✅ Application is healthy and running!"
    echo "🌐 Access your application at: http://localhost:8080"
    echo "🌐 Nginx proxy available at: http://localhost"
else
    echo "⚠️  Application might be starting up. Check logs with: docker-compose logs app"
fi

echo "📋 Useful commands:"
echo "  📊 View logs: docker-compose logs -f app"
echo "  🔧 Access shell: docker-compose exec app sh"
echo "  ⏹️  Stop services: docker-compose down"
echo "  🔄 Restart: docker-compose restart app"

echo "🎉 Deployment complete!"
