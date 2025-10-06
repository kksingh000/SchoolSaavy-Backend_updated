# Media Server Deployment Guide

## 🚀 Production Deployment Steps

### 1. Server Requirements

- **CPU**: 4+ cores recommended
- **RAM**: 4GB minimum, 8GB recommended
- **Storage**: 50GB+ for logs and recordings
- **Network**: High bandwidth (10Mbps+ upload/download)
- **OS**: Ubuntu 20.04+ or similar Linux distribution

### 2. Domain & SSL Setup

#### Configure DNS

```
A Record: media.yourdomain.com → YOUR_SERVER_IP
```

#### Nginx Configuration with SSL

Create `/etc/nginx/sites-available/media-server`:

```nginx
# HTTP to HTTPS redirect
server {
    listen 80;
    server_name media.yourdomain.com;
    
    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }
    
    location / {
        return 301 https://$host$request_uri;
    }
}

# HTTPS - API and Playback
server {
    listen 443 ssl http2;
    server_name media.yourdomain.com;
    
    ssl_certificate /etc/letsencrypt/live/media.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/media.yourdomain.com/privkey.pem;
    
    # SSL Configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Large client body for uploads
    client_max_body_size 100M;
    
    # CORS headers
    add_header Access-Control-Allow-Origin * always;
    add_header Access-Control-Allow-Methods 'GET, POST, OPTIONS' always;
    add_header Access-Control-Allow-Headers 'Origin, Content-Type, Accept, Authorization' always;
    
    # Proxy to media server
    location / {
        proxy_pass http://localhost:8002;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Buffering settings for streaming
        proxy_buffering off;
        proxy_request_buffering off;
    }
    
    # Health check
    location /health {
        proxy_pass http://localhost:8002/health;
        access_log off;
    }
}

# RTMP port (TCP proxy) - Optional
# For RTMP over SSL, use stunnel or similar
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/media-server /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

#### Get SSL Certificate

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d media.yourdomain.com
```

### 3. Environment Configuration

Create production `.env` file:

```bash
cd /path/to/SchoolSaavy_PHP
nano media_server/.env
```

```env
NODE_ENV=production
HTTP_PORT=8000
RTMP_PORT=1935
INTERNAL_HTTP_PORT=8001
SERVER_HOST=0.0.0.0
PUBLIC_HOST=media.yourdomain.com

# Authentication
AUTH_ENABLED=true
AUTH_SECRET=GENERATE_STRONG_SECRET_HERE
BACKEND_API_URL=http://app:8080/api
BACKEND_API_TOKEN=YOUR_BACKEND_TOKEN

# Stream Settings
MAX_STREAMS_PER_SCHOOL=10
STREAM_TIMEOUT=300000

# HLS
HLS_ENABLED=true
HLS_SEGMENT_TIME=2
HLS_LIST_SIZE=3

# Logging
LOG_LEVEL=info
LOG_FILE=./logs/media-server.log

# CORS - Restrict in production
CORS_ORIGINS=https://yourdomain.com,https://app.yourdomain.com
CORS_CREDENTIALS=true

# Storage
MEDIA_ROOT=./media
RECORDINGS_ENABLED=false
RECORDINGS_PATH=./recordings

# Performance
MAX_CONNECTIONS=1000
WORKER_THREADS=4
```

### 4. Docker Compose Production

Update `docker-compose.yml`:

```yaml
media-server:
  build:
    context: ./media_server
    dockerfile: Dockerfile
  container_name: schoolsavvy_media_server
  restart: unless-stopped
  environment:
    - NODE_ENV=production
    - HTTP_PORT=8000
    - RTMP_PORT=1935
    - INTERNAL_HTTP_PORT=8001
    - SERVER_HOST=0.0.0.0
    - PUBLIC_HOST=media.yourdomain.com
    - AUTH_ENABLED=true
    - AUTH_SECRET=${MEDIA_SERVER_SECRET}
    - BACKEND_API_URL=http://app:8080/api
    - BACKEND_API_TOKEN=${MEDIA_SERVER_API_TOKEN}
    - MAX_STREAMS_PER_SCHOOL=10
    - HLS_ENABLED=true
    - LOG_LEVEL=info
    - CORS_ORIGINS=https://yourdomain.com,https://app.yourdomain.com
  ports:
    - "1935:1935"  # RTMP
    - "8002:8000"  # HTTP/API
  volumes:
    - media_data:/app/media
    - media_logs:/app/logs
    - media_recordings:/app/recordings
  depends_on:
    - app
    - redis
  networks:
    - schoolsavvy_network
  deploy:
    resources:
      limits:
        cpus: '2.0'
        memory: 4G
      reservations:
        cpus: '1.0'
        memory: 2G
```

### 5. Firewall Configuration

```bash
# Allow RTMP
sudo ufw allow 1935/tcp

# Allow HTTP/HTTPS (if not already allowed)
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Reload firewall
sudo ufw reload
```

### 6. Laravel Backend Integration

Add to `config/services.php`:

```php
'media_server' => [
    'url' => env('MEDIA_SERVER_URL', 'https://media.yourdomain.com'),
    'api_token' => env('MEDIA_SERVER_API_TOKEN'),
],
```

Add to `.env`:

```env
MEDIA_SERVER_URL=https://media.yourdomain.com
MEDIA_SERVER_API_TOKEN=your_secure_token_here
```

Add routes to `routes/api.php`:

```php
// Media Server Routes
Route::middleware(['auth:sanctum', 'inject.school'])->prefix('media')->group(function () {
    Route::post('validate-stream', [MediaController::class, 'validateStream']);
    Route::get('streams', [MediaController::class, 'getActiveStreams']);
    Route::get('stream/{streamKey}', [MediaController::class, 'getStreamInfo']);
    Route::delete('stream/{streamKey}', [MediaController::class, 'endStream']);
});
```

### 7. Deploy

```bash
# Navigate to project root
cd /path/to/SchoolSaavy_PHP

# Pull latest changes
git pull origin main

# Build and start media server
docker-compose build media-server
docker-compose up -d media-server

# Check logs
docker logs -f schoolsavvy_media_server

# Verify health
curl http://localhost:8002/health
curl https://media.yourdomain.com/health
```

### 8. Testing

#### Test RTMP Connection

```bash
# Using ffmpeg
ffmpeg -re -i test-video.mp4 -c:v libx264 -c:a aac -f flv \
  "rtmp://media.yourdomain.com:1935/live/test?token=YOUR_TOKEN"
```

#### Test Playback

```bash
# FLV
curl -I https://media.yourdomain.com/live/test.flv

# HLS
curl -I https://media.yourdomain.com/live/test/index.m3u8
```

### 9. Monitoring Setup

#### Prometheus Monitoring (Optional)

Create `media_server/prometheus.js`:

```javascript
// Add to rtmp-server.js for metrics export
const express = require('express');
const prometheus = require('prom-client');

const register = new prometheus.Registry();
prometheus.collectDefaultMetrics({ register });

const activeStreamsGauge = new prometheus.Gauge({
  name: 'media_server_active_streams',
  help: 'Number of active streams',
  registers: [register]
});

// Endpoint for Prometheus
app.get('/metrics', (req, res) => {
  activeStreamsGauge.set(streamManager.getAllStreams().length);
  res.set('Content-Type', register.contentType);
  res.end(register.metrics());
});
```

#### Log Rotation

Create `/etc/logrotate.d/media-server`:

```
/path/to/SchoolSaavy_PHP/media_server/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0644 root root
    sharedscripts
    postrotate
        docker exec schoolsavvy_media_server kill -USR1 1
    endscript
}
```

### 10. Backup Strategy

```bash
# Backup script
#!/bin/bash
BACKUP_DIR="/backup/media-server"
DATE=$(date +%Y%m%d)

# Backup volumes
docker run --rm \
  -v media_data:/data \
  -v $BACKUP_DIR:/backup \
  alpine tar czf /backup/media-data-$DATE.tar.gz -C /data .

# Backup logs
docker run --rm \
  -v media_logs:/logs \
  -v $BACKUP_DIR:/backup \
  alpine tar czf /backup/media-logs-$DATE.tar.gz -C /logs .

# Keep only last 7 days
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete
```

### 11. Troubleshooting

#### Check Container Status

```bash
docker ps | grep media_server
docker logs schoolsavvy_media_server
docker exec -it schoolsavvy_media_server sh
```

#### Check Resource Usage

```bash
docker stats schoolsavvy_media_server
```

#### Test Network Connectivity

```bash
# From inside container
docker exec schoolsavvy_media_server nc -zv app 8080

# From host
nc -zv localhost 1935
nc -zv localhost 8002
```

### 12. Performance Tuning

#### Nginx Optimization

```nginx
# Add to nginx.conf http block
client_body_buffer_size 128k;
client_max_body_size 100m;
proxy_buffer_size 4k;
proxy_buffers 24 4k;
proxy_busy_buffers_size 8k;
proxy_temp_file_write_size 8k;
```

#### Docker Resource Limits

```yaml
deploy:
  resources:
    limits:
      cpus: '4.0'
      memory: 8G
    reservations:
      cpus: '2.0'
      memory: 4G
```

## 📊 Monitoring Checklist

- [ ] Set up log aggregation (ELK/Loki)
- [ ] Configure alerts for high CPU/memory
- [ ] Monitor active stream count
- [ ] Track bandwidth usage
- [ ] Set up uptime monitoring
- [ ] Configure error rate alerts
- [ ] Monitor disk space for recordings

## 🔒 Security Checklist

- [ ] Enable authentication in production
- [ ] Use strong secrets and tokens
- [ ] Configure proper CORS origins
- [ ] Set up rate limiting
- [ ] Enable HTTPS for all endpoints
- [ ] Restrict firewall rules
- [ ] Regular security updates
- [ ] Monitor access logs
- [ ] Implement IP whitelisting (if needed)

## 🎯 Post-Deployment

1. **Test streaming from mobile**
2. **Test playback on web**
3. **Verify authentication**
4. **Check logs for errors**
5. **Monitor performance**
6. **Document stream keys**
7. **Train users**

---

**Your media server is now production-ready! 🎥**
