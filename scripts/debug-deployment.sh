#!/bin/bash

# 🔍 SchoolSavvy Deployment Debugging Script
# Run this on the production server to diagnose deployment issues

echo "=================================="
echo "🔍 SchoolSavvy Deployment Debug"
echo "=================================="
echo ""

cd /var/www/schoolsaavy

echo "1️⃣ Checking Docker installation..."
docker --version
docker compose version
echo ""

echo "2️⃣ Checking critical files..."
echo "Checking .env file:"
if [ -f .env ]; then
    echo "✅ .env exists"
    ls -la .env
    echo "   Lines in .env: $(wc -l < .env)"
else
    echo "❌ .env MISSING - This is the problem!"
    echo "   Create it with: cp .env.example .env"
    echo "   Then edit with production values"
fi
echo ""

echo "Checking docker-compose.yml:"
if [ -f docker-compose.yml ]; then
    echo "✅ docker-compose.yml exists"
    ls -la docker-compose.yml
else
    echo "❌ docker-compose.yml MISSING"
fi
echo ""

echo "3️⃣ Checking Docker Compose configuration..."
echo "Services defined in docker-compose.yml:"
docker compose config --services 2>&1
echo ""

echo "4️⃣ Checking Docker images..."
echo "Available images:"
docker images | grep -E "schoolsaavy|ghcr.io"
echo ""

echo "5️⃣ Checking Docker containers..."
echo "All containers:"
docker compose ps -a
echo ""

echo "6️⃣ Checking Docker networks..."
docker network ls | grep schoolsaavy
echo ""

echo "7️⃣ Checking Docker volumes..."
docker volume ls | grep schoolsaavy
echo ""

echo "8️⃣ Checking logs (last 50 lines)..."
echo "--- APP LOGS ---"
docker compose logs --tail=50 app 2>&1 || echo "❌ No app logs available"
echo ""

echo "--- NGINX LOGS ---"
docker compose logs --tail=50 nginx 2>&1 || echo "❌ No nginx logs available"
echo ""

echo "--- MYSQL LOGS ---"
docker compose logs --tail=50 mysql 2>&1 || echo "❌ No mysql logs available"
echo ""

echo "--- REDIS LOGS ---"
docker compose logs --tail=50 redis 2>&1 || echo "❌ No redis logs available"
echo ""

echo "--- MEDIA SERVER LOGS ---"
docker compose logs --tail=50 media-server 2>&1 || echo "❌ No media-server logs available"
echo ""

echo "9️⃣ Checking storage permissions..."
ls -la storage/
echo ""

echo "🔟 Checking config files..."
echo "Checking nginx.conf:"
if [ -f docker/nginx/nginx.conf ]; then
    echo "✅ nginx.conf exists"
    ls -la docker/nginx/nginx.conf
else
    echo "❌ nginx.conf MISSING"
fi

echo "Checking my.cnf:"
if [ -f docker/mysql/my.cnf ]; then
    echo "✅ my.cnf exists"
    ls -la docker/mysql/my.cnf
else
    echo "❌ my.cnf MISSING"
fi

echo "Checking redis.conf:"
if [ -f docker/redis/redis.conf ]; then
    echo "✅ redis.conf exists"
    ls -la docker/redis/redis.conf
else
    echo "❌ redis.conf MISSING"
fi
echo ""

echo "1️⃣1️⃣ Checking media server configuration..."
echo "Checking media_server/.env:"
if [ -f media_server/.env ]; then
    echo "✅ media_server/.env exists"
    ls -la media_server/.env
    echo "   Lines in media_server/.env: $(wc -l < media_server/.env)"
else
    echo "❌ media_server/.env MISSING"
    echo "   Create it with: cp media_server/.env.example media_server/.env"
fi
echo ""

echo "1️⃣2️⃣ Checking disk space..."
df -h /var/www/schoolsaavy
echo ""

echo "1️⃣3️⃣ Checking system resources..."
echo "Memory usage:"
free -h
echo ""

echo "CPU usage:"
top -bn1 | head -20
echo ""

echo "=================================="
echo "🎯 Common Issues & Solutions"
echo "=================================="
echo ""
echo "❌ ISSUE: Containers not running"
echo "   SOLUTION 1: Check if .env file exists and has correct values"
echo "   SOLUTION 2: Run: docker compose down && docker compose up -d"
echo "   SOLUTION 3: Check logs: docker compose logs --tail=100"
echo ""
echo "❌ ISSUE: .env file missing"
echo "   SOLUTION: Create .env file manually:"
echo "   1. cp .env.example .env"
echo "   2. nano .env  (edit with production values)"
echo "   3. docker compose up -d"
echo ""
echo "❌ ISSUE: media_server/.env missing"
echo "   SOLUTION: Create media server .env:"
echo "   1. cp media_server/.env.example media_server/.env"
echo "   2. nano media_server/.env  (edit with production values)"
echo "   3. docker compose restart media-server"
echo ""
echo "❌ ISSUE: Docker image not found"
echo "   SOLUTION: Pull images manually:"
echo "   1. docker login ghcr.io"
echo "   2. docker compose pull"
echo "   3. docker compose up -d"
echo ""
echo "❌ ISSUE: Port conflicts"
echo "   SOLUTION: Check what's using ports:"
echo "   sudo netstat -tulpn | grep -E ':(80|443|3306|6379|1935|8000)'"
echo ""

echo "=================================="
echo "🚀 Quick Recovery Commands"
echo "=================================="
echo ""
echo "# Restart all containers"
echo "docker compose restart"
echo ""
echo "# Rebuild and restart"
echo "docker compose down && docker compose up -d --build"
echo ""
echo "# View live logs"
echo "docker compose logs -f"
echo ""
echo "# Check specific container"
echo "docker compose logs app --tail=100"
echo ""
echo "# Enter a container"
echo "docker compose exec app bash"
echo ""
echo "# Clean up and restart"
echo "docker compose down -v && docker compose up -d"
echo ""

echo "=================================="
echo "Debug script completed!"
echo "=================================="
