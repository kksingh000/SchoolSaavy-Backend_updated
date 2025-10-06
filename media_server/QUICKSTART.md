# SchoolSavvy Media Server - Quick Start Guide

## 🚀 Quick Setup

### Local Development (Without Docker)

```bash
# 1. Navigate to media server directory
cd media_server

# 2. Install dependencies
npm install

# 3. Setup environment
cp .env.example .env

# 4. Create directories
mkdir -p media logs recordings

# 5. Start server
npm run dev
```

Server will be available at:
- RTMP: `rtmp://localhost:1935/live`
- HTTP: `http://localhost:8000`
- Health: `http://localhost:8000/health`

### Docker Development

```bash
# Start with existing SchoolSavvy stack
docker-compose -f docker-compose-local.yml up -d media-server

# View logs
docker logs -f schoolsavvy_media_server_local

# Check health
curl http://localhost:8002/health
```

### Production Deployment

```bash
# 1. Build and start
docker-compose up -d media-server

# 2. Verify
curl http://your-server-ip:8002/health

# 3. Check logs
docker logs schoolsavvy_media_server
```

## 📱 Stream from Mobile

### Using Larix Broadcaster (Recommended)

1. **Install**: Download Larix Broadcaster from App Store/Play Store

2. **Configure Connection**:
   - Open Larix → Settings → Connections
   - Create New Connection
   - Name: `SchoolSavvy`
   - URL: `rtmp://YOUR_SERVER_IP:1935/live`
   - Stream Key: `camera1` (or any unique key)

3. **Start Streaming**:
   - Go back to main screen
   - Select your connection
   - Press the broadcast button

### Using OBS Studio (Desktop)

1. **Settings → Stream**:
   - Service: Custom
   - Server: `rtmp://YOUR_SERVER_IP:1935/live`
   - Stream Key: `test`

2. **Start Streaming**

## 📺 Watch Stream

### Web Browser (Recommended - HTTP-FLV)

```html
<!DOCTYPE html>
<html>
<head>
    <title>SchoolSavvy Camera Stream</title>
    <script src="https://cdn.jsdelivr.net/npm/flv.js/dist/flv.min.js"></script>
</head>
<body>
    <video id="player" controls width="800" height="600"></video>
    
    <script>
        const player = flvjs.createPlayer({
            type: 'flv',
            url: 'http://localhost:8002/live/camera1.flv',
            isLive: true
        });
        player.attachMediaElement(document.getElementById('player'));
        player.load();
        player.play();
    </script>
</body>
</html>
```

### VLC Media Player

1. Open VLC → Media → Open Network Stream
2. Enter URL:
   - HTTP-FLV: `http://localhost:8002/live/camera1.flv`
   - HLS: `http://localhost:8002/live/camera1/index.m3u8`
   - RTMP: `rtmp://localhost:1935/live/camera1`

## 🔌 API Examples

### Check Active Streams

```bash
curl http://localhost:8002/api/streams
```

### Get Stream Info

```bash
curl http://localhost:8002/api/stream/camera1
```

### Get School Streams

```bash
curl http://localhost:8002/api/streams/school/123
```

## ⚙️ Configuration

### Disable Authentication (Development)

In `.env`:
```env
AUTH_ENABLED=false
```

### Change Ports

In `.env`:
```env
HTTP_PORT=8000
RTMP_PORT=1935
```

In `docker-compose-local.yml`:
```yaml
ports:
  - "1935:1935"  # RTMP
  - "8002:8000"  # HTTP
```

### Enable Stream Recordings

In `.env`:
```env
RECORDINGS_ENABLED=true
RECORDINGS_PATH=./recordings
```

## 🐛 Troubleshooting

### Can't Connect to Stream

```bash
# Check if server is running
docker ps | grep media_server

# Check logs
docker logs schoolsavvy_media_server_local

# Test health endpoint
curl http://localhost:8002/health
```

### Playback Not Working

1. **Check stream is active**:
   ```bash
   curl http://localhost:8002/api/stream/your_stream_key
   ```

2. **Try different protocols**:
   - HTTP-FLV: `http://localhost:8002/live/camera1.flv`
   - HLS: `http://localhost:8002/live/camera1/index.m3u8`

3. **Check CORS**: Open browser console for errors

### High CPU Usage

1. **Disable HLS** if not needed:
   ```env
   HLS_ENABLED=false
   ```

2. **Reduce stream quality** in Larix settings

3. **Limit concurrent streams**:
   ```env
   MAX_STREAMS_PER_SCHOOL=5
   ```

## 📊 Monitoring

### Docker Stats

```bash
docker stats schoolsavvy_media_server
```

### View Logs

```bash
# Docker logs
docker logs -f schoolsavvy_media_server_local

# File logs
tail -f media_server/logs/media-server.log
```

### Health Check

```bash
# Basic health
curl http://localhost:8002/health

# Formatted with jq
curl -s http://localhost:8002/health | jq
```

## 🔐 Enable Authentication

### Step 1: Update Media Server Config

In `.env`:
```env
AUTH_ENABLED=true
AUTH_SECRET=your_secure_secret_here
BACKEND_API_URL=http://app:8080/api
```

### Step 2: Implement Laravel Backend API

Create endpoint in Laravel: `POST /api/media/validate-stream`

```php
// routes/api.php
Route::post('media/validate-stream', [MediaController::class, 'validateStream'])
    ->middleware('auth:sanctum');

// app/Http/Controllers/MediaController.php
public function validateStream(Request $request)
{
    $streamKey = $request->input('stream_key');
    $user = $request->user();
    
    // Validate user has permission to stream
    return response()->json([
        'status' => 'success',
        'data' => [
            'school_id' => $user->school_id,
            'user_id' => $user->id,
            'metadata' => [
                'camera_name' => 'Main Camera',
            ]
        ]
    ]);
}
```

### Step 3: Stream with Token

```
rtmp://your-server:1935/live/camera1?token=USER_SANCTUM_TOKEN
```

## 🎯 Common Use Cases

### Case 1: Single Camera Stream

1. Start media server
2. Stream from mobile: `rtmp://server:1935/live/entrance`
3. Watch: `http://server:8002/live/entrance.flv`

### Case 2: Multiple School Cameras

1. Each school gets unique stream keys
2. Example: `school123_entrance`, `school123_playground`
3. Backend validates school ownership
4. Max 10 streams per school (configurable)

### Case 3: Event Live Streaming

1. Stream school event from mobile
2. Embed player in web app
3. Parents watch in real-time
4. Auto-cleanup after stream ends

## 📱 Mobile App Integration

### React Native Example

```javascript
import { RTCView } from 'react-native-webrtc';
import Video from 'react-native-video';

<Video
  source={{ uri: 'http://server:8002/live/camera1.flv' }}
  style={{ width: '100%', height: 300 }}
  controls={true}
  resizeMode="contain"
/>
```

### Flutter Example

```dart
import 'package:video_player/video_player.dart';

VideoPlayerController.network(
  'http://server:8002/live/camera1/index.m3u8'
)
```

## 🚀 Production Tips

1. **Use nginx reverse proxy** for HTTPS
2. **Set up CDN** for better performance
3. **Monitor bandwidth** usage
4. **Rotate logs** to prevent disk full
5. **Backup recordings** if enabled
6. **Set resource limits** in Docker
7. **Use dedicated server** for better performance

## 📞 Need Help?

1. **Check logs** first
2. **Test health endpoint**
3. **Verify network connectivity**
4. **Review configuration**
5. **Check firewall rules**

## 🎓 Next Steps

- [ ] Set up nginx reverse proxy with SSL
- [ ] Implement stream recording feature
- [ ] Add stream thumbnails/previews
- [ ] Set up monitoring and alerts
- [ ] Configure CDN for global distribution
- [ ] Add stream quality analytics
- [ ] Implement stream chat feature

---

**Ready to stream! 🎥**
