# 🎥 SchoolSavvy Media Server

A production-ready RTMP media server for real-time camera streaming in the SchoolSavvy platform. Built with Node.js, supporting multi-tenant architecture with **simplified Sanctum token authentication**, stream management, and multiple playback protocols.

## 🚀 Features

- **RTMP Streaming**: Real-time streaming protocol support for mobile camera apps
- **Multi-Protocol Playback**: HTTP-FLV, HLS (when enabled), and RTMP playback options
- **Multi-Tenant Support**: School-isolated stream management
- **Simplified Authentication**: Uses existing Laravel Sanctum tokens (no separate key generation needed!)
- **Stream Management**: API for monitoring and managing active streams
- **Auto-Transcoding**: FFmpeg-based HLS transcoding for iOS/Safari support (optional)
- **CORS Enabled**: Full cross-origin support for web applications
- **Rate Limiting**: Protection against abuse
- **Comprehensive Logging**: Winston-based structured logging
- **Health Monitoring**: Built-in health check endpoint
- **Docker Ready**: Full containerization with health checks

## ⚡ Quick Start

**New users?** Check out our simplified guides:
- **[Quick Start (2 minutes)](./QUICK_START_STREAMING.md)** - Get streaming in 2 minutes
- **[Full Guide](./STREAMING_WITH_AUTH_TOKEN.md)** - Complete authentication flow
- **[Postman Collection](../postman_collections/SchoolSavvy_Media_Streaming_Token_Based.postman_collection.json)** - Test the API

## 📋 Requirements

- Node.js 18+ (Alpine in Docker)
- FFmpeg (for HLS transcoding)
- Docker & Docker Compose (for deployment)

## 🔧 Installation

### Local Development

```bash
cd media_server

# Install dependencies
npm install

# Copy environment file
cp .env.example .env

# Edit .env with your configuration
nano .env

# Create directories
npm run setup

# Start server
npm run dev
```

### Docker Deployment

The media server is integrated into the main SchoolSavvy Docker setup:

```bash
# Production
docker-compose up -d media-server

# Local development
docker-compose -f docker-compose-local.yml up -d media-server
```

## ⚙️ Configuration

### Environment Variables

Create a `.env` file based on `.env.example`:

| Variable | Description | Default |
|----------|-------------|---------|
| `NODE_ENV` | Environment (production/development) | `production` |
| `HTTP_PORT` | HTTP/API server port | `8000` |
| `RTMP_PORT` | RTMP streaming port | `1935` |
| `INTERNAL_HTTP_PORT` | Internal HTTP port | `8001` |
| `SERVER_HOST` | Server bind address | `0.0.0.0` |
| `PUBLIC_HOST` | Public hostname/IP for URLs | `localhost` |
| `AUTH_ENABLED` | Enable authentication | `true` |
| `AUTH_SECRET` | Secret for auth | `schoolsaavy_media_secret_2025` |
| `BACKEND_API_URL` | Laravel API URL | `http://app:8080/api` |
| `BACKEND_API_TOKEN` | Backend API token | - |
| `MAX_STREAMS_PER_SCHOOL` | Max concurrent streams per school | `10` |
| `HLS_ENABLED` | Enable HLS transcoding | `true` |
| `LOG_LEVEL` | Logging level (debug/info/warn/error) | `info` |
| `CORS_ORIGINS` | Allowed CORS origins | `*` |

### Production Configuration

For production deployment, update these in your `.env` or Docker Compose:

```env
NODE_ENV=production
PUBLIC_HOST=media.schoolsavvy.com
AUTH_ENABLED=true
BACKEND_API_URL=https://api.schoolsavvy.com/api
BACKEND_API_TOKEN=your_secure_token_here
LOG_LEVEL=info
```

## 📱 Usage - Simplified!

### ✨ New Simplified Flow

**No need to generate separate stream keys!** Just use your existing login token.

#### Step 1: Login (Get Your Token)
```bash
POST https://api.schoolsaavy.com/api/auth/login
{
  "email": "teacher@school.com",
  "password": "password"
}
```

Save the `token` from response.

#### Step 2: Get Streaming Credentials
```bash
POST https://api.schoolsaavy.com/api/media/streaming-credentials
Authorization: Bearer YOUR_TOKEN
{
  "camera_name": "My Classroom Camera"
}
```

Response includes complete RTMP URL with your token embedded:
```json
{
  "stream_key": "5_12",
  "rtmp_url": "rtmp://stream.schoolsaavy.com/live/5_12?token=YOUR_TOKEN",
  "playback_urls": {
    "flv": "https://stream.schoolsaavy.com/live/5_12.flv",
    "hls": "https://stream.schoolsaavy.com/hls/5_12/index.m3u8"
  }
}
```

#### Step 3: Start Streaming
Copy the `rtmp_url` into your streaming app (Larix/OBS) and start streaming!

### Streaming from Mobile (Larix Broadcaster)

1. **Get your RTMP URL** from Step 2 above
2. **Open Larix Broadcaster**
3. **Settings** → **Connections** → **New Connection**
4. **Paste the complete RTMP URL** (including `?token=...`)
5. **Start streaming** 🎥

### Streaming from Desktop (OBS Studio)

1. **Get your RTMP URL** from Step 2 above
2. **Open OBS** → **Settings** → **Stream**
3. **Server**: `rtmp://stream.schoolsaavy.com/live/5_12`
4. **Stream Key**: `?token=YOUR_TOKEN`
5. **Start streaming** 🎥

### Playback URLs

Once a stream is active, access it via:

```
# HTTP-FLV (Best for web - low latency)
http://your-server-ip:8002/live/[stream_key].flv

# HLS (iOS/Safari compatible)
http://your-server-ip:8002/live/[stream_key]/index.m3u8

# RTMP (Legacy players)
rtmp://your-server-ip:1935/live/[stream_key]
```

### Example with flv.js (Web Player)

```html
<script src="https://cdn.jsdelivr.net/npm/flv.js/dist/flv.min.js"></script>
<video id="videoElement" controls width="640" height="480"></video>

<script>
  if (flvjs.isSupported()) {
    var videoElement = document.getElementById('videoElement');
    var flvPlayer = flvjs.createPlayer({
      type: 'flv',
      url: 'http://your-server-ip:8002/live/stream_key.flv',
      isLive: true,
      hasAudio: true,
      hasVideo: true
    });
    flvPlayer.attachMediaElement(videoElement);
    flvPlayer.load();
    flvPlayer.play();
  }
</script>
```

## 🔌 API Endpoints

### Health Check
```http
GET /health
```

Response:
```json
{
  "status": "healthy",
  "timestamp": "2025-10-06T12:00:00.000Z",
  "uptime": 12345,
  "activeStreams": 5,
  "config": {
    "authEnabled": true,
    "rtmpPort": 1935,
    "httpPort": 8000
  }
}
```

### Get All Active Streams
```http
GET /api/streams
Authorization: Bearer YOUR_TOKEN (if auth enabled)
```

Response:
```json
{
  "status": "success",
  "data": {
    "total": 3,
    "streams": [
      {
        "streamKey": "camera1",
        "schoolId": "123",
        "duration": 125000,
        "playbackUrls": {
          "rtmp": "rtmp://server:1935/live/camera1",
          "flv": "http://server:8002/live/camera1.flv",
          "hls": "http://server:8002/live/camera1/index.m3u8"
        }
      }
    ]
  }
}
```

### Get School Streams
```http
GET /api/streams/school/:schoolId
```

### Get Stream Info
```http
GET /api/stream/:streamKey
```

## 🔐 Authentication - Simplified!

### How It Works (New Approach)

When `AUTH_ENABLED=true`, authentication uses your existing Laravel Sanctum token:

1. **User logs in** to Laravel API → receives Sanctum token
2. **User requests streaming credentials** → gets RTMP URL with token embedded
3. **User starts streaming** → Media server validates token with Laravel
4. **Stream is allowed** if token is valid and user has permissions

### Stream Key Format
```
{school_id}_{user_id}
```

**Example**: `5_12`
- School ID: 5
- User ID: 12

**Benefits**:
- ✅ Simple and predictable
- ✅ No random generation needed
- ✅ Unique per user
- ✅ No expiration management

### Backend Validation Flow

Your Laravel `MediaController` implements:

```php
POST /api/media/validate-stream
Body: {
  "streamKey": "5_12",
  "token": "1|abcdef..."
}

Response (on success):
{
  "success": true,
  "school_id": "5",
  "user_id": "12",
  "metadata": {
    "camera_name": "Main Entrance"
  }
}
```

The media server calls this endpoint to validate each streaming attempt.

### No Separate Keys Needed!

**Old way (complex)**:
1. Login → Get auth token
2. Generate stream key → Get separate key + token
3. Manage key expiration
4. Delete old keys

**New way (simple)**:
1. Login → Get auth token
2. Get streaming credentials (reuses same token)
3. Stream! No extra management needed

### Security Features

- ✅ **Token validation**: Every stream validated against Laravel
- ✅ **School isolation**: Users can only stream to their school
- ✅ **Role-based**: Only teachers/admins can stream
- ✅ **Module check**: Live-streaming module must be active
- ✅ **One stream per user**: Prevents abuse

## 🏗️ Architecture

```
┌─────────────────┐
│  Mobile Camera  │
│  (Larix/OBS)    │
└────────┬────────┘
         │ RTMP (1935)
         ▼
┌─────────────────────────┐
│  Node Media Server      │
│  - RTMP Ingest          │
│  - Authentication       │
│  - Stream Management    │
│  - FFmpeg Transcoding   │
└────────┬────────────────┘
         │
    ┌────┴─────┐
    │          │
    ▼          ▼
┌─────┐   ┌──────┐
│ FLV │   │ HLS  │
└──┬──┘   └───┬──┘
   │          │
   │    ┌─────┴──────────┐
   │    │                │
   ▼    ▼                ▼
┌──────────┐      ┌──────────┐
│  Web App │      │  Mobile  │
│ (flv.js) │      │  (HLS)   │
└──────────┘      └──────────┘
```

## 📊 Monitoring

### Logs

Logs are written to `./logs/media-server.log` and console:

```bash
# Follow logs
tail -f logs/media-server.log

# Docker logs
docker logs -f schoolsavvy_media_server
```

### Metrics

Monitor via health endpoint:

```bash
curl http://localhost:8002/health
```

### Stream Management

Check active streams:

```bash
curl http://localhost:8002/api/streams
```

## 🐳 Docker Configuration

### Ports Exposed

- **1935**: RTMP streaming input
- **8002**: HTTP API and playback (mapped from internal 8000)

### Volumes

- `media_data`: HLS segments and media files
- `media_logs`: Application logs
- `media_recordings`: (Optional) Stream recordings

### Environment in Docker Compose

```yaml
media-server:
  environment:
    - NODE_ENV=production
    - PUBLIC_HOST=media.yourdomain.com
    - AUTH_ENABLED=true
    - BACKEND_API_URL=http://app:8080/api
```

## 🔧 Troubleshooting

### Stream Won't Connect

1. **Check authentication**: Ensure token is valid
2. **Verify firewall**: Port 1935 must be open
3. **Check logs**: `docker logs schoolsavvy_media_server`
4. **Test health**: `curl http://localhost:8002/health`

### Playback Issues

1. **FLV not playing**: Ensure flv.js is loaded correctly
2. **HLS not working**: Check if FFmpeg is installed
3. **CORS errors**: Verify CORS_ORIGINS setting
4. **High latency**: Use HTTP-FLV instead of HLS

### Performance Issues

1. **Too many streams**: Check MAX_STREAMS_PER_SCHOOL limit
2. **CPU usage**: Disable HLS transcoding if not needed
3. **Memory leaks**: Restart container periodically
4. **Network bandwidth**: Monitor with `docker stats`

## 📝 Development

### Adding Features

1. Modify `rtmp-server.js` for core functionality
2. Update `config.js` for new settings
3. Add environment variables to `.env.example`
4. Update this README

### Testing Locally

```bash
# Start in dev mode
npm run dev

# Test RTMP with OBS Studio
# Server: rtmp://localhost:1935/live
# Stream Key: test

# Test playback
# Open: http://localhost:8000/live/test.flv
```

### Building Docker Image

```bash
cd media_server
docker build -t schoolsavvy-media-server .
docker run -p 1935:1935 -p 8000:8000 schoolsavvy-media-server
```

## 📚 Dependencies

- **node-media-server**: Core RTMP server
- **express**: HTTP API server
- **axios**: HTTP client for backend validation
- **winston**: Structured logging
- **helmet**: Security headers
- **express-rate-limit**: API rate limiting
- **cors**: CORS handling
- **morgan**: HTTP request logging
- **dotenv**: Environment configuration

## 🔒 Security Considerations

1. **Always enable authentication** in production
2. **Use HTTPS** for API endpoints (via nginx reverse proxy)
3. **Restrict CORS origins** to your domains only
4. **Use strong tokens** for stream keys
5. **Monitor logs** for suspicious activity
6. **Keep dependencies updated**: `npm audit fix`
7. **Limit stream duration** to prevent abuse
8. **Implement rate limiting** on API endpoints

## 📄 License

MIT License - Part of the SchoolSavvy Platform

## 👥 Support

For issues or questions:
- Check logs: `docker logs schoolsavvy_media_server`
- Review configuration: `docker exec schoolsavvy_media_server cat /app/.env`
- Test health: `curl http://localhost:8002/health`

## 🚀 Production Deployment Checklist

- [ ] Set `NODE_ENV=production`
- [ ] Configure `PUBLIC_HOST` with your domain
- [ ] Enable authentication: `AUTH_ENABLED=true`
- [ ] Set secure `AUTH_SECRET`
- [ ] Configure `BACKEND_API_URL` and `BACKEND_API_TOKEN`
- [ ] Set appropriate `MAX_STREAMS_PER_SCHOOL`
- [ ] Configure CORS origins (not `*`)
- [ ] Set up SSL/TLS via nginx reverse proxy
- [ ] Configure firewall to allow port 1935
- [ ] Set up log rotation for `logs/media-server.log`
- [ ] Configure backups for media volumes
- [ ] Set up monitoring and alerts
- [ ] Test streaming and playback
- [ ] Document stream keys for users

---

**Built with ❤️ for SchoolSavvy Platform**
