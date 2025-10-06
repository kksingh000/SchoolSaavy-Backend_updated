#!/bin/bash

# 🌐 Quick Setup Script for stream.schoolsaavy.com
# Run this on your production server after DNS is configured

set -e

echo "=================================="
echo "🌐 Media Server Domain Setup"
echo "=================================="
echo ""

# Configuration
DOMAIN="stream.schoolsaavy.com"
EMAIL="your-email@example.com"  # CHANGE THIS
PROJECT_DIR="/var/www/schoolsaavy"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Helper functions
error() {
    echo -e "${RED}❌ ERROR: $1${NC}"
    exit 1
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then 
    warning "This script may need sudo for some commands"
fi

# Step 1: Check DNS
echo "1️⃣ Checking DNS configuration..."
if nslookup $DOMAIN > /dev/null 2>&1; then
    IP=$(nslookup $DOMAIN | grep "Address:" | tail -1 | awk '{print $2}')
    success "DNS is configured: $DOMAIN → $IP"
else
    error "DNS is not configured for $DOMAIN. Please add an A record first."
fi

# Step 2: Navigate to project directory
echo ""
echo "2️⃣ Checking project directory..."
if [ ! -d "$PROJECT_DIR" ]; then
    error "Project directory not found: $PROJECT_DIR"
fi
cd $PROJECT_DIR
success "Project directory found"

# Step 3: Check if media-server.conf exists
echo ""
echo "3️⃣ Checking Nginx configuration..."
if [ ! -f "docker/nginx/media-server.conf" ]; then
    error "media-server.conf not found. Please ensure it's deployed."
fi
success "media-server.conf found"

# Step 4: Check if media server is running
echo ""
echo "4️⃣ Checking media server status..."
if docker compose ps | grep -q "media-server.*Up"; then
    success "Media server is running"
else
    warning "Media server is not running. Starting it..."
    docker compose up -d media-server
    sleep 5
fi

# Step 5: Test Nginx configuration
echo ""
echo "5️⃣ Testing Nginx configuration..."
docker compose exec nginx nginx -t || error "Nginx configuration test failed"
success "Nginx configuration is valid"

# Step 6: Stop Nginx temporarily for SSL setup
echo ""
echo "6️⃣ Stopping Nginx temporarily for SSL certificate..."
docker compose stop nginx
sleep 2

# Step 7: Obtain SSL certificate
echo ""
echo "7️⃣ Obtaining SSL certificate from Let's Encrypt..."
echo "   Domain: $DOMAIN"
echo "   Email: $EMAIL"
echo ""

if [ "$EMAIL" == "your-email@example.com" ]; then
    warning "Please edit this script and set your email address!"
    read -p "Enter your email address: " EMAIL
fi

docker run -it --rm \
  -v $(pwd)/docker/certbot/conf:/etc/letsencrypt \
  -v $(pwd)/docker/certbot:/var/www/certbot \
  -p 80:80 \
  certbot/certbot certonly \
  --standalone \
  --email $EMAIL \
  --agree-tos \
  --no-eff-email \
  -d $DOMAIN

if [ $? -eq 0 ]; then
    success "SSL certificate obtained successfully"
else
    error "Failed to obtain SSL certificate"
fi

# Step 8: Start Nginx
echo ""
echo "8️⃣ Starting Nginx..."
docker compose start nginx
sleep 3

# Step 9: Reload Nginx
echo ""
echo "9️⃣ Reloading Nginx configuration..."
docker compose exec nginx nginx -s reload
success "Nginx reloaded"

# Step 10: Update media_server/.env
echo ""
echo "🔟 Updating media server configuration..."
if [ -f "media_server/.env" ]; then
    if grep -q "PUBLIC_HOST=" media_server/.env; then
        sed -i "s|PUBLIC_HOST=.*|PUBLIC_HOST=$DOMAIN|g" media_server/.env
        success "PUBLIC_HOST updated to $DOMAIN"
    else
        echo "PUBLIC_HOST=$DOMAIN" >> media_server/.env
        success "PUBLIC_HOST added to .env"
    fi
    
    # Restart media server
    docker compose restart media-server
    sleep 5
else
    warning "media_server/.env not found. Please create it manually."
fi

# Step 11: Open firewall ports
echo ""
echo "1️⃣1️⃣ Checking firewall..."
if command -v ufw &> /dev/null; then
    echo "Opening ports with UFW..."
    sudo ufw allow 80/tcp
    sudo ufw allow 443/tcp
    sudo ufw allow 1935/tcp
    sudo ufw reload
    success "Firewall rules updated"
else
    warning "UFW not found. Please manually open ports 80, 443, 1935"
fi

# Step 12: Verification
echo ""
echo "=================================="
echo "🔍 Verification"
echo "=================================="
echo ""

# Test HTTPS health endpoint
echo "Testing HTTPS health endpoint..."
if curl -k -s https://$DOMAIN/health > /dev/null 2>&1; then
    success "HTTPS health endpoint is working"
    echo ""
    curl -k -s https://$DOMAIN/health | python3 -m json.tool || curl -k -s https://$DOMAIN/health
else
    error "HTTPS health endpoint is not responding"
fi

echo ""
echo "=================================="
echo "✅ Setup Complete!"
echo "=================================="
echo ""
echo "📋 Summary:"
echo "   Domain: https://$DOMAIN"
echo "   Health Check: https://$DOMAIN/health"
echo "   RTMP URL: rtmp://$DOMAIN/live/"
echo "   HTTP-FLV: https://$DOMAIN/live/{streamKey}.flv"
echo ""
echo "🔗 Test URLs:"
echo "   curl https://$DOMAIN/health"
echo "   curl https://$DOMAIN/api/streams"
echo ""
echo "📱 Mobile App Configuration:"
echo "   RTMP Server: rtmp://$DOMAIN/live/"
echo "   Stream Key: school{id}_stream{id}_teacher{id}?token=YOUR_TOKEN"
echo ""
echo "🎯 Next Steps:"
echo "   1. Update your Laravel .env: MEDIA_SERVER_URL=https://$DOMAIN"
echo "   2. Update mobile app configuration"
echo "   3. Test streaming from mobile device"
echo ""
echo "=================================="
