# 📹 Media Server Integration Summary

## ✅ What's Been Done

### 1. **Dynamic Configuration System** ✓
- Environment-based configuration via `.env` file
- Separate development and production configs
- All settings externalized (ports, authentication, limits, etc.)

### 2. **Production-Ready Features** ✓
- **Authentication**: Token-based validation with Laravel backend
- **Multi-Tenant Support**: School-isolated stream management
- **Stream Management**: Track active streams, enforce limits
- **Security**: Helmet, CORS, rate limiting, authentication
- **Logging**: Winston-based structured logging
- **Health Monitoring**: Health check endpoint
- **Multiple Protocols**: RTMP input, HTTP-FLV + HLS output

### 3. **Docker Integration** ✓
- Dockerfile with FFmpeg for transcoding
- Integrated into `docker-compose.yml` (production)
- Integrated into `docker-compose-local.yml` (development)
- Persistent volumes for media, logs, and recordings
- Health checks configured
- Resource limits set

### 4. **Laravel Backend Integration** ✓
- `MediaController.php` created with full API
- Routes added to `routes/api.php`
- Configuration added to `config/services.php`
- Environment variables added to `.env.example`

### 5. **Comprehensive Documentation** ✓
- `README.md` - Complete feature documentation
- `QUICKSTART.md` - Quick setup guide
- `DEPLOYMENT.md` - Production deployment guide
- `INTEGRATION.md` - This file

---

## 📁 Files Created/Modified

### New Files
```
media_server/
├── .env                        # Environment configuration
├── .env.example                # Environment template
├── .gitignore                  # Git ignore rules
├── .dockerignore              # Docker ignore rules
├── config.js                  # Dynamic configuration loader
├── Dockerfile                 # Production container
├── README.md                  # Full documentation
├── QUICKSTART.md              # Quick start guide
└── DEPLOYMENT.md              # Deployment guide
```

### Modified Files
```
media_server/
├── package.json               # Updated dependencies
└── rtmp-server.js            # Complete rewrite with features

Root project/
├── docker-compose.yml         # Added media-server service
├── docker-compose-local.yml   # Added media-server service
├── .env.example               # Added media server vars
├── config/services.php        # Added media_server config
└── routes/api.php            # Added media routes

New Controllers/
└── app/Http/Controllers/MediaController.php
```

---

## 🚀 Quick Start

### Development (Without Docker)

```bash
cd media_server
npm install
cp .env.example .env
npm run dev
```

Access:
- RTMP: `rtmp://localhost:1935/live`
- HTTP: `http://localhost:8000`
- Health: `http://localhost:8000/health`

### Development (With Docker)

```bash
docker-compose -f docker-compose-local.yml up -d media-server
docker logs -f schoolsavvy_media_server_local
```

Access:
- RTMP: `rtmp://localhost:1935/live`
- HTTP: `http://localhost:8002`

### Production Deployment

```bash
# 1. Update .env with production values
nano media_server/.env

# 2. Update Docker Compose environment
nano docker-compose.yml

# 3. Deploy
docker-compose up -d media-server

# 4. Verify
curl http://localhost:8002/health
docker logs schoolsavvy_media_server
```

---

## 🔌 API Endpoints

### Laravel Backend APIs

```
POST   /api/media/validate-stream     # Validate stream token (media server)
GET    /api/media/streams              # Get active streams (auth required)
GET    /api/media/stream/{streamKey}   # Get stream info
DELETE /api/media/stream/{streamKey}   # End stream
```

### Media Server APIs

```
GET    /health                        # Health check
GET    /api/streams                   # List all streams
GET    /api/streams/school/:schoolId  # Get school streams
GET    /api/stream/:streamKey         # Get stream info
GET    /live/:key.flv                 # HTTP-FLV playback
GET    /live/:key/index.m3u8          # HLS playback
```

---

## 📱 How to Stream

### Mobile App (Larix Broadcaster)

1. **Install Larix** from App Store/Play Store

2. **Configure**:
   - URL: `rtmp://your-server:1935/live`
   - Stream Key: `unique_key` (or `unique_key?token=USER_TOKEN` if auth enabled)

3. **Start Broadcasting**

### OBS Studio

1. **Settings → Stream**:
   - Service: Custom
   - Server: `rtmp://your-server:1935/live`
   - Stream Key: `your_key?token=USER_TOKEN`

2. **Start Streaming**

---

## 📺 How to Playback

### Web (Recommended - HTTP-FLV)

```html
<script src="https://cdn.jsdelivr.net/npm/flv.js/dist/flv.min.js"></script>
<video id="player" controls></video>

<script>
  const player = flvjs.createPlayer({
    type: 'flv',
    url: 'http://your-server:8002/live/stream_key.flv',
    isLive: true
  });
  player.attachMediaElement(document.getElementById('player'));
  player.load();
  player.play();
</script>
```

### Mobile App (HLS)

```javascript
// React Native
<Video 
  source={{ uri: 'http://your-server:8002/live/stream_key/index.m3u8' }}
  controls={true}
/>

// Flutter
VideoPlayerController.network(
  'http://your-server:8002/live/stream_key/index.m3u8'
)
```

---

## ⚙️ Configuration

### Key Environment Variables

```env
# Server
NODE_ENV=production
PUBLIC_HOST=media.yourdomain.com

# Authentication
AUTH_ENABLED=true
AUTH_SECRET=your_secret_here
BACKEND_API_URL=http://app:8080/api
BACKEND_API_TOKEN=your_token

# Performance
MAX_STREAMS_PER_SCHOOL=10
HLS_ENABLED=true
LOG_LEVEL=info
```

### Docker Compose Configuration

```yaml
media-server:
  environment:
    - PUBLIC_HOST=media.yourdomain.com
    - AUTH_ENABLED=true
    - BACKEND_API_URL=http://app:8080/api
  ports:
    - "1935:1935"  # RTMP
    - "8002:8000"  # HTTP
```

---

## 🔐 Authentication Flow

### When `AUTH_ENABLED=true`:

1. **User streams** with token:
   ```
   rtmp://server:1935/live/camera1?token=USER_SANCTUM_TOKEN
   ```

2. **Media server** calls Laravel:
   ```
   POST http://app:8080/api/media/validate-stream
   Authorization: Bearer USER_SANCTUM_TOKEN
   Body: {"stream_key": "camera1"}
   ```

3. **Laravel validates** and returns:
   ```json
   {
     "status": "success",
     "data": {
       "school_id": "123",
       "user_id": "456",
       "metadata": {}
     }
   }
   ```

4. **Stream accepted** if valid, rejected otherwise

### When `AUTH_ENABLED=false` (Development):

- No authentication required
- Any stream key works
- Good for testing

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────┐
│              Mobile Camera App                   │
│         (Larix / OBS / Custom App)              │
└────────────────┬────────────────────────────────┘
                 │ RTMP Stream (1935)
                 │ rtmp://server:1935/live/key?token=xxx
                 ▼
┌─────────────────────────────────────────────────┐
│           Media Server (Node.js)                │
│  ┌──────────────────────────────────────────┐  │
│  │  1. Receive RTMP Stream                  │  │
│  │  2. Validate Token (call Laravel API)    │  │
│  │  3. Check School Limits                  │  │
│  │  4. Manage Active Streams                │  │
│  │  5. Transcode to HLS (FFmpeg)            │  │
│  └──────────────────────────────────────────┘  │
└────────┬──────────────────────┬─────────────────┘
         │                      │
         │ HTTP-FLV             │ HLS
         │ (Low Latency)        │ (iOS Compatible)
         ▼                      ▼
┌──────────────────┐    ┌───────────────────┐
│   Web Browser    │    │   Mobile App      │
│   (flv.js)       │    │   (Native HLS)    │
└──────────────────┘    └───────────────────┘
```

---

## 🐛 Troubleshooting

### Issue: Can't connect to stream

**Check:**
```bash
# 1. Container running?
docker ps | grep media_server

# 2. Check logs
docker logs schoolsavvy_media_server

# 3. Test health
curl http://localhost:8002/health

# 4. Check firewall
sudo ufw status | grep 1935
```

### Issue: Authentication failing

**Check:**
```bash
# 1. Is AUTH_ENABLED=true?
docker exec schoolsavvy_media_server cat /app/.env | grep AUTH

# 2. Can media server reach Laravel?
docker exec schoolsavvy_media_server nc -zv app 8080

# 3. Check Laravel logs
docker logs schoolsavvy_app | grep media
```

### Issue: Playback not working

**Try:**
1. HTTP-FLV first (lowest latency): `http://server:8002/live/key.flv`
2. Then HLS (iOS compatible): `http://server:8002/live/key/index.m3u8`
3. Check CORS in browser console
4. Verify stream is active: `curl http://server:8002/api/stream/key`

---

## 📊 Monitoring

### Check Active Streams

```bash
# Via API
curl http://localhost:8002/api/streams

# Via Laravel
curl -H "Authorization: Bearer TOKEN" \
  http://localhost:8080/api/media/streams
```

### Monitor Resources

```bash
# Docker stats
docker stats schoolsavvy_media_server

# Logs
docker logs -f schoolsavvy_media_server

# Health
watch -n 5 'curl -s http://localhost:8002/health | jq'
```

---

## 🎯 Use Cases

### 1. School Event Live Streaming
- Teacher starts stream from mobile
- Parents watch in web app
- Automatic cleanup after event

### 2. Security Camera Monitoring
- IP cameras stream to media server
- Admin views multiple feeds
- Recording optional

### 3. Classroom Live Sessions
- Teacher streams lesson
- Remote students watch
- Low latency with HTTP-FLV

### 4. Sports Day Broadcasting
- Multiple camera angles
- Different schools on same server
- School-isolated streams

---

## ✅ Pre-Deployment Checklist

### Development
- [ ] Install dependencies: `npm install`
- [ ] Copy `.env.example` to `.env`
- [ ] Start server: `npm run dev`
- [ ] Test streaming with OBS/Larix
- [ ] Verify playback in browser

### Production
- [ ] Set `NODE_ENV=production`
- [ ] Configure `PUBLIC_HOST` with domain
- [ ] Enable authentication: `AUTH_ENABLED=true`
- [ ] Set secure secrets and tokens
- [ ] Configure CORS origins (not `*`)
- [ ] Set up SSL/TLS (nginx reverse proxy)
- [ ] Open firewall port 1935
- [ ] Configure monitoring/alerts
- [ ] Test end-to-end flow
- [ ] Document for users

---

## 📚 Additional Resources

### Documentation
- **README.md** - Complete feature documentation
- **QUICKSTART.md** - Quick setup guide  
- **DEPLOYMENT.md** - Production deployment guide

### External Resources
- [Node Media Server](https://github.com/illuspas/Node-Media-Server)
- [flv.js Player](https://github.com/bilibili/flv.js)
- [Larix Broadcaster](https://softvelum.com/larix/)
- [OBS Studio](https://obsproject.com/)

---

## 🎓 Next Steps

1. **Test locally** first without Docker
2. **Enable Docker** for full stack testing
3. **Configure authentication** and test token flow
4. **Deploy to production** with SSL
5. **Monitor and optimize** based on usage
6. **Document stream keys** for users
7. **Train staff** on streaming

---

## 💡 Tips

1. **Use HTTP-FLV for web** - Lowest latency (~2-3 seconds)
2. **Use HLS for mobile apps** - Better iOS compatibility
3. **Enable authentication** in production always
4. **Monitor bandwidth** - Streaming is bandwidth-intensive
5. **Set stream limits** per school to prevent abuse
6. **Use HTTPS** for API endpoints (via nginx)
7. **Rotate logs** to prevent disk full
8. **Test with mobile** before going live

---

## 📞 Support

If you need help:
1. Check the documentation files
2. Review logs: `docker logs schoolsavvy_media_server`
3. Test health: `curl http://localhost:8002/health`
4. Verify configuration: Check `.env` file
5. Test network: `nc -zv server 1935`

---

**Your media server is ready to stream! 🎥📹**

Built with ❤️ for SchoolSavvy Platform
