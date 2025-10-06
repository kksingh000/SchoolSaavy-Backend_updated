# 🎥 Media Server - Quick Reference Card

## 🚀 Start Server

```bash
# Standalone
cd media_server
npm run dev

# Docker (Development)
docker-compose -f docker-compose-local.yml up -d media-server

# Docker (Production)
docker-compose up -d media-server
```

## 📡 Server URLs

| Service | URL | Description |
|---------|-----|-------------|
| RTMP Input | `rtmp://localhost:1935/live` | Stream here from mobile/OBS |
| HTTP Server | `http://localhost:8000` | Main HTTP endpoint (standalone) |
| HTTP Server | `http://localhost:8002` | Main HTTP endpoint (Docker) |
| Health Check | `http://localhost:8002/health` | Server health status |
| API Endpoint | `http://localhost:8002/api/streams` | Active streams list |

## 📱 Streaming

### From Larix Broadcaster (Mobile)
```
URL: rtmp://YOUR_IP:1935/live
Stream Key: camera1
(or with auth: camera1?token=YOUR_TOKEN)
```

### From OBS Studio (Desktop)
```
Server: rtmp://localhost:1935/live
Stream Key: test
```

## 📺 Playback URLs

Replace `{stream_key}` with your actual stream key:

| Protocol | URL | Best For |
|----------|-----|----------|
| HTTP-FLV | `http://localhost:8002/live/{stream_key}.flv` | Web (Low latency ~2-3s) |
| HLS | `http://localhost:8002/live/{stream_key}/index.m3u8` | iOS/Safari |
| RTMP | `rtmp://localhost:1935/live/{stream_key}` | Legacy players |

## 🔌 API Endpoints

### Media Server APIs
```bash
# Health check
curl http://localhost:8002/health

# List all streams
curl http://localhost:8002/api/streams

# Get school streams
curl http://localhost:8002/api/streams/school/123

# Get stream info
curl http://localhost:8002/api/stream/camera1
```

### Laravel Backend APIs (Requires Auth)
```bash
# Validate stream (internal use)
curl -X POST http://localhost:8080/api/media/validate-stream \
  -H "Authorization: Bearer TOKEN" \
  -d '{"stream_key":"camera1"}'

# Get active streams
curl -H "Authorization: Bearer TOKEN" \
  http://localhost:8080/api/media/streams

# Get stream info
curl -H "Authorization: Bearer TOKEN" \
  http://localhost:8080/api/media/stream/camera1

# End stream
curl -X DELETE \
  -H "Authorization: Bearer TOKEN" \
  http://localhost:8080/api/media/stream/camera1
```

## ⚙️ Configuration (.env)

```env
# Development
NODE_ENV=development
AUTH_ENABLED=false
LOG_LEVEL=debug

# Production
NODE_ENV=production
AUTH_ENABLED=true
PUBLIC_HOST=media.yourdomain.com
BACKEND_API_URL=http://app:8080/api
```

## 🐛 Troubleshooting Commands

```bash
# Check if server is running
docker ps | grep media_server

# View logs
docker logs -f schoolsavvy_media_server_local

# Test health
curl http://localhost:8002/health

# Check active streams
curl http://localhost:8002/api/streams

# Enter container
docker exec -it schoolsavvy_media_server_local sh

# Restart server
docker-compose restart media-server

# Rebuild container
docker-compose build media-server
docker-compose up -d media-server
```

## 📊 Monitoring

```bash
# Docker stats
docker stats schoolsavvy_media_server_local

# Continuous health check
watch -n 5 'curl -s http://localhost:8002/health | jq'

# Follow logs
tail -f media_server/logs/media-server.log

# Check disk usage
docker exec schoolsavvy_media_server_local du -sh /app/media
```

## 🔥 Common Issues

### Can't connect to stream
```bash
# Check firewall (Windows)
netsh advfirewall firewall add rule name="RTMP" dir=in action=allow protocol=TCP localport=1935

# Check if port is in use
netstat -ano | findstr :1935

# Verify server is running
curl http://localhost:8002/health
```

### Playback not working
```bash
# Check stream is active
curl http://localhost:8002/api/stream/YOUR_KEY

# Test with VLC directly
vlc http://localhost:8002/live/YOUR_KEY.flv

# Check CORS in browser console
```

### Authentication issues
```bash
# Check auth setting
docker exec schoolsavvy_media_server cat /app/.env | grep AUTH

# Test backend connectivity
docker exec schoolsavvy_media_server nc -zv app 8080

# Check Laravel logs
docker logs schoolsavvy_app | grep media
```

## 📁 Important Files

| File | Purpose |
|------|---------|
| `.env` | Configuration |
| `rtmp-server.js` | Main server code |
| `config.js` | Config loader |
| `test-player.html` | Web testing tool |
| `logs/media-server.log` | Application logs |
| `media/` | HLS segments |
| `recordings/` | Stream recordings (if enabled) |

## 📖 Documentation

| Document | Description |
|----------|-------------|
| `README.md` | Complete documentation (3000+ lines) |
| `QUICKSTART.md` | Quick setup guide |
| `DEPLOYMENT.md` | Production deployment |
| `INTEGRATION.md` | Integration summary |
| `SETUP_COMPLETE.md` | Setup completion guide |

## 🎯 Quick Test

```bash
# 1. Start server
cd media_server && npm run dev

# 2. Open test player
start test-player.html

# 3. Stream from OBS
# Server: rtmp://localhost:1935/live
# Key: test

# 4. In test player:
# - Enter key: test
# - Click Play Stream
```

## 🚀 Production Deployment

```bash
# 1. Update config
nano media_server/.env

# 2. Set production values
NODE_ENV=production
PUBLIC_HOST=media.yourdomain.com
AUTH_ENABLED=true

# 3. Deploy
docker-compose build media-server
docker-compose up -d media-server

# 4. Verify
curl https://media.yourdomain.com/health
```

## 🔐 Enable Authentication

```bash
# 1. Update .env
AUTH_ENABLED=true
BACKEND_API_URL=http://app:8080/api

# 2. Restart
docker-compose restart media-server

# 3. Stream with token
rtmp://server:1935/live/key?token=YOUR_TOKEN
```

## 📊 Performance Tuning

```env
# High performance settings
MAX_STREAMS_PER_SCHOOL=20
WORKER_THREADS=8
MAX_CONNECTIONS=2000
HLS_ENABLED=false  # Disable if not needed
LOG_LEVEL=warn     # Reduce logging
```

## 🎓 Protocols Comparison

| Protocol | Latency | Compatibility | Best For |
|----------|---------|---------------|----------|
| HTTP-FLV | ~2-3s | Chrome, Firefox, Edge | Web apps (lowest latency) |
| HLS | ~10-15s | All browsers, iOS, Android | Mobile apps, iOS |
| RTMP | ~1-2s | Flash players (legacy) | Legacy systems |

## 💡 Pro Tips

1. **Use HTTP-FLV for web** - Lowest latency
2. **Use HLS for mobile** - Best compatibility
3. **Test locally first** - Before production
4. **Monitor bandwidth** - Streaming is heavy
5. **Set stream limits** - Prevent abuse
6. **Always use HTTPS** - In production (via nginx)
7. **Enable auth** - In production always
8. **Rotate logs** - Prevent disk full

## 🎬 Example Stream Keys

```
Format: {school_id}_{location}_{device}

Examples:
- school123_entrance_camera1
- school456_playground_mobile
- school789_classroom_webcam
```

## 📱 Mobile App Integration

### React Native
```javascript
<Video 
  source={{ uri: 'http://server:8002/live/key.flv' }}
  controls={true}
/>
```

### Flutter
```dart
VideoPlayerController.network(
  'http://server:8002/live/key/index.m3u8'
)
```

## 🎯 Port Reference

| Port | Protocol | Purpose |
|------|----------|---------|
| 1935 | RTMP | Stream input |
| 8000 | HTTP | API/Playback (standalone) |
| 8001 | HTTP | Internal server |
| 8002 | HTTP | API/Playback (Docker) |

## ✅ Pre-Go-Live Checklist

- [ ] Server starts without errors
- [ ] Health endpoint responds
- [ ] Can stream from mobile
- [ ] Can stream from desktop
- [ ] Playback works in browser
- [ ] Playback works in VLC
- [ ] Multiple streams work
- [ ] Authentication works
- [ ] Laravel integration works
- [ ] Docker deployment works
- [ ] SSL/HTTPS configured
- [ ] Monitoring set up
- [ ] Backups configured
- [ ] Documentation shared

---

**Quick Help:**
- Health: `curl http://localhost:8002/health`
- Logs: `docker logs -f schoolsavvy_media_server_local`
- Docs: Check `media_server/README.md`

**🎥 Happy Streaming!**
