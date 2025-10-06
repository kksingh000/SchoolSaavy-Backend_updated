const NodeMediaServer = require('node-media-server');
const express = require('express');
const cors = require('cors');
const axios = require('axios');
const morgan = require('morgan');
const helmet = require('helmet');
const rateLimit = require('express-rate-limit');
const winston = require('winston');
const fs = require('fs');
const path = require('path');
const config = require('./config');

// ============================
// Logger Setup
// ============================
const logDir = path.dirname(config.logging.file);
if (!fs.existsSync(logDir)) {
  fs.mkdirSync(logDir, { recursive: true });
}

const logger = winston.createLogger({
  level: config.logging.level,
  format: winston.format.combine(
    winston.format.timestamp({ format: 'YYYY-MM-DD HH:mm:ss' }),
    winston.format.errors({ stack: true }),
    winston.format.splat(),
    winston.format.json()
  ),
  defaultMeta: { service: 'media-server' },
  transports: [
    new winston.transports.File({ filename: config.logging.file }),
    new winston.transports.Console({
      format: winston.format.combine(
        winston.format.colorize(),
        winston.format.simple()
      )
    })
  ]
});

// ============================
// Stream Management
// ============================
class StreamManager {
  constructor() {
    this.activeStreams = new Map(); // streamKey -> { schoolId, userId, startTime, metadata }
    this.streamsBySchool = new Map(); // schoolId -> Set of streamKeys
  }

  addStream(streamKey, schoolId, userId, metadata = {}) {
    // Check school stream limit
    const schoolStreams = this.streamsBySchool.get(schoolId) || new Set();
    if (schoolStreams.size >= config.stream.maxStreamsPerSchool) {
      logger.warn(`School ${schoolId} reached max stream limit`);
      return false;
    }

    this.activeStreams.set(streamKey, {
      schoolId,
      userId,
      startTime: Date.now(),
      metadata
    });

    schoolStreams.add(streamKey);
    this.streamsBySchool.set(schoolId, schoolStreams);

    logger.info(`Stream added: ${streamKey} for school ${schoolId}`);
    return true;
  }

  removeStream(streamKey) {
    const stream = this.activeStreams.get(streamKey);
    if (stream) {
      const schoolStreams = this.streamsBySchool.get(stream.schoolId);
      if (schoolStreams) {
        schoolStreams.delete(streamKey);
        if (schoolStreams.size === 0) {
          this.streamsBySchool.delete(stream.schoolId);
        }
      }
      this.activeStreams.delete(streamKey);
      logger.info(`Stream removed: ${streamKey}`);
    }
  }

  getStream(streamKey) {
    return this.activeStreams.get(streamKey);
  }

  getSchoolStreams(schoolId) {
    const streamKeys = this.streamsBySchool.get(schoolId) || new Set();
    return Array.from(streamKeys).map(key => ({
      streamKey: key,
      ...this.activeStreams.get(key)
    }));
  }

  getAllStreams() {
    return Array.from(this.activeStreams.entries()).map(([key, value]) => ({
      streamKey: key,
      ...value
    }));
  }

  isStreamActive(streamKey) {
    return this.activeStreams.has(streamKey);
  }
}

const streamManager = new StreamManager();

// ============================
// Authentication Helper
// ============================

/**
 * Parse stream key to extract school ID, user ID, and camera ID
 * 
 * Supported Formats:
 * 1. Full Format: {school_id}_{user_id}_{camera_id}
 *    Example: 1_5_2 -> schoolId: 1, userId: 5, cameraId: 2
 * 
 * 2. Short Format (No User ID): {school_id}_{camera_id}
 *    Example: 1_2 -> schoolId: 1, userId: 'camera', cameraId: 2
 *    Use Case: Direct IP camera streaming without user tracking
 * 
 * 3. Single ID: {school_id}
 *    Example: 1 -> schoolId: 1, userId: 'unknown', cameraId: 'unknown'
 */
function parseStreamKey(streamKey) {
  try {
    const parts = streamKey.split('_');
    
    // Format 1: Full format with user ID (school_user_camera)
    if (parts.length >= 3) {
      logger.info(`[Parse] Full format: "${streamKey}" -> School: ${parts[0]}, User: ${parts[1]}, Camera: ${parts[2]}`);
      return {
        schoolId: parts[0],
        userId: parts[1],
        cameraId: parts[2],
        format: 'full',
        valid: true
      };
    }
    
    // Format 2: Short format without user ID (school_camera)
    if (parts.length === 2) {
      logger.info(`[Parse] Short format: "${streamKey}" -> School: ${parts[0]}, Camera: ${parts[1]} (no user tracking)`);
      return {
        schoolId: parts[0],
        userId: 'camera', // Mark as direct camera stream
        cameraId: parts[1],
        format: 'short',
        valid: true
      };
    }
    
    // Format 3: Single school ID
    if (parts.length === 1 && !isNaN(parts[0])) {
      logger.info(`[Parse] School only: "${streamKey}" -> School: ${parts[0]}`);
      return {
        schoolId: parts[0],
        userId: 'unknown',
        cameraId: 'unknown',
        format: 'school-only',
        valid: true
      };
    }
    
    // Fallback for non-standard stream keys
    logger.warn(`[Parse] Non-standard format: "${streamKey}" - using as-is with default school`);
    return {
      schoolId: 'default',
      userId: 'unknown',
      cameraId: streamKey,
      format: 'legacy',
      valid: false
    };
  } catch (error) {
    logger.error('Error parsing stream key:', error);
    return {
      schoolId: 'default',
      userId: 'unknown',
      cameraId: streamKey,
      format: 'error',
      valid: false
    };
  }
}

async function validateStreamToken(streamKey, token) {
  if (!config.auth.enabled) {
    // Parse stream key to get school ID even without auth
    const parsed = parseStreamKey(streamKey);
    logger.info(`[No Auth] Parsed stream key "${streamKey}" -> schoolId: ${parsed.schoolId}, userId: ${parsed.userId}, cameraId: ${parsed.cameraId}`);
    
    return { 
      valid: true, 
      schoolId: parsed.schoolId, 
      userId: parsed.userId,
      metadata: { cameraId: parsed.cameraId }
    };
  }

  try {
    const response = await axios.post(
      `${config.auth.backendApiUrl}/media/validate-stream`,
      { stream_key: streamKey },
      {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        timeout: 5000
      }
    );

    if (response.data.status === 'success') {
      return {
        valid: true,
        schoolId: response.data.data.school_id,
        userId: response.data.data.user_id,
        metadata: response.data.data.metadata || {}
      };
    }
    return { valid: false, error: 'Invalid token' };
  } catch (error) {
    logger.error('Stream validation error:', error.message);
    return { valid: false, error: error.message };
  }
}

// ============================
// Express Server Setup
// ============================
const app = express();

// Security middleware
app.use(helmet({
  crossOriginResourcePolicy: { policy: "cross-origin" }
}));

// CORS configuration
const corsOptions = {
  origin: config.cors.origins[0] === '*' ? '*' : config.cors.origins,
  methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Origin', 'X-Requested-With', 'Content-Type', 'Accept', 'Authorization', 'Cache-Control'],
  credentials: config.cors.credentials
};
app.use(cors(corsOptions));

// Logging
app.use(morgan('combined', {
  stream: { write: message => logger.info(message.trim()) }
}));

// Body parser
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Rate limiting
const limiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100, // limit each IP to 100 requests per windowMs
  message: 'Too many requests from this IP, please try again later.'
});
app.use('/api/', limiter);

// Static files for media (with CORS headers)
app.use('/media', express.static(config.storage.mediaRoot, {
  setHeaders: (res) => {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
    res.setHeader('Cache-Control', 'no-cache');
  }
}));

// ============================
// API Routes
// ============================

// Health check
app.get('/health', (req, res) => {
  res.json({
    status: 'healthy',
    timestamp: new Date().toISOString(),
    uptime: process.uptime(),
    activeStreams: streamManager.getAllStreams().length,
    config: {
      authEnabled: config.auth.enabled,
      rtmpPort: config.rtmp.port,
      httpPort: config.server.httpPort
    }
  });
});

// Get active streams
app.get('/api/streams', async (req, res) => {
  try {
    // const authHeader = req.headers.authorization;
    // if (!authHeader && config.auth.enabled) {
    //   return res.status(401).json({ error: 'Authorization required' });
    // }

    const streams = streamManager.getAllStreams();
    res.json({
      status: 'success',
      data: {
        total: streams.length,
        streams: streams.map(s => ({
          streamKey: s.streamKey,
          schoolId: s.schoolId,
          duration: Date.now() - s.startTime,
          playbackUrls: {
            rtmp: `rtmp://${config.server.publicHost}:${config.rtmp.port}/live/${s.streamKey}`,
            flv: `http://${config.server.publicHost}:${config.server.httpPort}/live/${s.streamKey}.flv`,
            hls: `http://${config.server.publicHost}:${config.server.httpPort}/live/${s.streamKey}/index.m3u8`
          }
        }))
      }
    });
  } catch (error) {
    logger.error('Error fetching streams:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
});

// Get streams by school
app.get('/api/streams/school/:schoolId', async (req, res) => {
  try {
    const { schoolId } = req.params;
    const streams = streamManager.getSchoolStreams(schoolId);
    
    res.json({
      status: 'success',
      data: {
        schoolId,
        total: streams.length,
        maxAllowed: config.stream.maxStreamsPerSchool,
        streams: streams.map(s => ({
          streamKey: s.streamKey,
          duration: Date.now() - s.startTime,
          playbackUrls: {
            rtmp: `rtmp://${config.server.publicHost}:${config.rtmp.port}/live/${s.streamKey}`,
            flv: `http://${config.server.publicHost}:${config.server.httpPort}/live/${s.streamKey}.flv`,
            hls: `http://${config.server.publicHost}:${config.server.httpPort}/live/${s.streamKey}/index.m3u8`
          }
        }))
      }
    });
  } catch (error) {
    logger.error('Error fetching school streams:', error);
    res.status(500).json({ error: 'Internal server error' });
  }
});

// Stream info
app.get('/api/stream/:streamKey', (req, res) => {
  const { streamKey } = req.params;
  const stream = streamManager.getStream(streamKey);
  
  if (!stream) {
    return res.status(404).json({ error: 'Stream not found' });
  }

  res.json({
    status: 'success',
    data: {
      streamKey,
      schoolId: stream.schoolId,
      isActive: true,
      duration: Date.now() - stream.startTime,
      playbackUrls: {
        rtmp: `rtmp://${config.server.publicHost}:${config.rtmp.port}/live/${streamKey}`,
        flv: `http://${config.server.publicHost}:${config.server.httpPort}/live/${streamKey}.flv`,
        hls: `http://${config.server.publicHost}:${config.server.httpPort}/live/${streamKey}/index.m3u8`
      }
    }
  });
});

// FLV stream proxy with CORS
app.get('/live/:stream.flv', (req, res) => {
  const streamName = req.params.stream;
  const targetUrl = `http://127.0.0.1:${config.server.internalHttpPort}/live/${streamName}.flv`;
  
  // Set CORS headers
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept');
  res.setHeader('Cache-Control', 'no-cache');
  
  // Proxy the request
  const http = require('http');
  const proxyReq = http.request(targetUrl, (proxyRes) => {
    res.writeHead(proxyRes.statusCode, proxyRes.headers);
    proxyRes.pipe(res);
  });
  
  proxyReq.on('error', (err) => {
    logger.error('Proxy error:', err);
    if (!res.headersSent) {
      res.status(500).send('Stream not available');
    }
  });
  
  req.pipe(proxyReq);
});

// HLS stream proxy with CORS
app.get('/live/:stream/:file', (req, res) => {
  const { stream, file } = req.params;
  const targetUrl = `http://127.0.0.1:${config.server.internalHttpPort}/live/${stream}/${file}`;
  
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
  res.setHeader('Cache-Control', 'no-cache');
  
  const http = require('http');
  const proxyReq = http.request(targetUrl, (proxyRes) => {
    res.writeHead(proxyRes.statusCode, proxyRes.headers);
    proxyRes.pipe(res);
  });
  
  proxyReq.on('error', (err) => {
    logger.error('HLS proxy error:', err);
    if (!res.headersSent) {
      res.status(500).send('Stream segment not available');
    }
  });
  
  req.pipe(proxyReq);
});

// Start Express server
app.listen(config.server.httpPort, config.server.host, () => {
  logger.info(`🌐 HTTP Server running on ${config.server.host}:${config.server.httpPort}`);
});

// ============================
// Node Media Server Event Handlers Setup
// ============================
function setupEventHandlers(mediaServer) {
  mediaServer.on('preConnect', (id, args) => {
    logger.debug(`[preConnect] id=${id} args=${JSON.stringify(args)}`);
  });

  mediaServer.on('postConnect', (id, args) => {
    logger.debug(`[postConnect] id=${id} args=${JSON.stringify(args)}`);
  });

  mediaServer.on('doneConnect', (id, args) => {
    logger.debug(`[doneConnect] id=${id}`);
  });

  mediaServer.on('prePublish', async (id, StreamPath, args) => {
    logger.info(`[prePublish] id=${id} StreamPath=${StreamPath} args=${JSON.stringify(args)}`);
    
    const streamKey = StreamPath.split('/').pop();
    
    // Validate authentication
    if (config.auth.enabled) {
      const token = args.token || args.key;
      
      if (!token) {
        logger.warn(`[prePublish] No token provided for stream: ${streamKey}`);
        const session = mediaServer.getSession(id);
        session.reject();
        return;
      }

      const validation = await validateStreamToken(streamKey, token);
      
      if (!validation.valid) {
        logger.warn(`[prePublish] Invalid token for stream: ${streamKey}`);
        const session = mediaServer.getSession(id);
        session.reject();
        return;
      }

      // Add stream to manager
      const added = streamManager.addStream(
        streamKey, 
        validation.schoolId, 
        validation.userId,
        validation.metadata
      );

      if (!added) {
        logger.warn(`[prePublish] Could not add stream: ${streamKey} (limit reached)`);
        const session = mediaServer.getSession(id);
        session.reject();
        return;
      }
    } else {
      // No auth - parse stream key and add stream
      const parsed = parseStreamKey(streamKey);
      logger.info(`[No Auth] Adding stream "${streamKey}" for school ${parsed.schoolId}`);
      streamManager.addStream(streamKey, parsed.schoolId, parsed.userId, { cameraId: parsed.cameraId });
    }
  });

  mediaServer.on('postPublish', (id, StreamPath, args) => {
    const streamKey = StreamPath.split('/').pop();
    logger.info(`[postPublish] Stream started: ${streamKey}`);
    logger.info(`📺 Playback URLs:`);
    logger.info(`   - RTMP: rtmp://${config.server.publicHost}:${config.rtmp.port}/live/${streamKey}`);
    logger.info(`   - FLV:  http://${config.server.publicHost}:${config.server.httpPort}/live/${streamKey}.flv`);
    logger.info(`   - HLS:  http://${config.server.publicHost}:${config.server.httpPort}/live/${streamKey}/index.m3u8`);
  });

  mediaServer.on('donePublish', (id, StreamPath, args) => {
    const streamKey = StreamPath.split('/').pop();
    logger.info(`[donePublish] Stream ended: ${streamKey}`);
    streamManager.removeStream(streamKey);
  });

  mediaServer.on('prePlay', (id, StreamPath, args) => {
    logger.debug(`[prePlay] id=${id} StreamPath=${StreamPath}`);
  });

  mediaServer.on('postPlay', (id, StreamPath, args) => {
    logger.debug(`[postPlay] id=${id} StreamPath=${StreamPath}`);
  });

  mediaServer.on('donePlay', (id, StreamPath, args) => {
    logger.debug(`[donePlay] id=${id} StreamPath=${StreamPath}`);
  });
}

// ============================
// Node Media Server Setup
// ============================
const nmsConfig = {
  rtmp: config.rtmp,
  http: config.http,
  auth: {
    play: false, // We'll handle auth in prePublish
    publish: false,
    secret: config.auth.secret
  }
};

// Only add transcoding if HLS is enabled
// Note: Transcoding requires FFmpeg to be properly installed
if (config.trans.tasks[0].hls || config.trans.tasks[0].dash) {
  try {
    // Check if FFmpeg is available before enabling transcoding
    const { execSync } = require('child_process');
    try {
      const ffmpegVersion = execSync('ffmpeg -version', { encoding: 'utf8' });
      if (ffmpegVersion) {
        nmsConfig.trans = config.trans;
        logger.info('✅ HLS/DASH transcoding enabled (FFmpeg detected)');
      }
    } catch (ffmpegError) {
      logger.warn('⚠️  FFmpeg not found or not working properly. Disabling HLS/DASH transcoding.');
      logger.warn('   Falling back to HTTP-FLV and RTMP only');
      logger.debug('FFmpeg check error:', ffmpegError.message);
    }
  } catch (error) {
    logger.error('Error checking FFmpeg availability:', error);
    logger.warn('Disabling transcoding due to FFmpeg check failure');
  }
} else {
  logger.info('ℹ️  HLS/DASH transcoding disabled (HTTP-FLV and RTMP only)');
}

let nms = new NodeMediaServer(nmsConfig);

// Wrap nms.run() in try-catch to handle any initialization errors
let serverStarted = false;
try {
  nms.run();
  serverStarted = true;
  logger.info('✅ Node Media Server started successfully');
} catch (error) {
  logger.error('❌ Failed to start Node Media Server:', error);
  
  // If transcoding was enabled and it failed, try again without it
  if (nmsConfig.trans) {
    logger.warn('🔄 Retrying without transcoding...');
    delete nmsConfig.trans;
    try {
      nms = new NodeMediaServer(nmsConfig);
      nms.run();
      serverStarted = true;
      logger.info('✅ Media server started successfully without transcoding');
    } catch (retryError) {
      logger.error('❌ Failed to start media server even without transcoding:', retryError);
      process.exit(1);
    }
  } else {
    logger.error('❌ Media server failed to start');
    process.exit(1);
  }
}

// Setup event handlers
setupEventHandlers(nms);

// ============================
// Start Media Server
// ============================
nms.run();

// ============================
// Startup Information
// ============================
console.log('\n' + '='.repeat(80));
console.log('🎥 SchoolSavvy Media Server Started Successfully!');
console.log('='.repeat(80));
console.log(`\n� Configuration:`);
console.log(`   Environment: ${config.env}`);
console.log(`   Authentication: ${config.auth.enabled ? '✅ Enabled' : '❌ Disabled'}`);
console.log(`   Max Streams per School: ${config.stream.maxStreamsPerSchool}`);
console.log(`\n🔌 Server Endpoints:`);
console.log(`   RTMP Server: rtmp://${config.server.publicHost}:${config.rtmp.port}/live`);
console.log(`   HTTP Server: http://${config.server.publicHost}:${config.server.httpPort}`);
console.log(`   Health Check: http://${config.server.publicHost}:${config.server.httpPort}/health`);
console.log(`   API Endpoint: http://${config.server.publicHost}:${config.server.httpPort}/api/streams`);
console.log(`\n� How to Stream (Example):`);
console.log(`   1. RTMP Publish URL: rtmp://${config.server.publicHost}:${config.rtmp.port}/live`);
console.log(`   2. Stream Key: your_unique_stream_key`);
if (config.auth.enabled) {
  console.log(`   3. Add token parameter: ?token=your_auth_token`);
}
console.log(`\n📺 Playback URLs (replace [stream_key] with your key):`);
console.log(`   - HTTP-FLV: http://${config.server.publicHost}:${config.server.httpPort}/live/[stream_key].flv`);
console.log(`   - HLS: http://${config.server.publicHost}:${config.server.httpPort}/live/[stream_key]/index.m3u8`);
console.log(`   - RTMP: rtmp://${config.server.publicHost}:${config.rtmp.port}/live/[stream_key]`);
console.log('\n' + '='.repeat(80) + '\n');

logger.info('Media server initialized successfully');

// Graceful shutdown
process.on('SIGTERM', () => {
  logger.info('SIGTERM signal received: closing HTTP server');
  nms.stop();
  process.exit(0);
});

process.on('SIGINT', () => {
  logger.info('SIGINT signal received: closing HTTP server');
  nms.stop();
  process.exit(0);
});