require('dotenv').config();

module.exports = {
  // Environment
  env: process.env.NODE_ENV || 'development',
  
  // Server Configuration
  server: {
    httpPort: parseInt(process.env.HTTP_PORT) || 8000,
    rtmpPort: parseInt(process.env.RTMP_PORT) || 1935,
    internalHttpPort: parseInt(process.env.INTERNAL_HTTP_PORT) || 8001,
    host: process.env.SERVER_HOST || '0.0.0.0',
    publicHost: process.env.PUBLIC_HOST || 'localhost',
  },
  
  // RTMP Configuration
  rtmp: {
    port: parseInt(process.env.RTMP_PORT) || 1935,
    chunk_size: parseInt(process.env.RTMP_CHUNK_SIZE) || 60000,
    gop_cache: process.env.RTMP_GOP_CACHE === 'true',
    ping: parseInt(process.env.RTMP_PING) || 30,
    ping_timeout: parseInt(process.env.RTMP_PING_TIMEOUT) || 60,
  },
  
  // HTTP Configuration
  http: {
    port: parseInt(process.env.INTERNAL_HTTP_PORT) || 8001,
    allow_origin: '*',
    mediaroot: process.env.MEDIA_ROOT || './media',
    api: false, // We handle API through Express
  },
  
  // HLS Configuration
  trans: {
    ffmpeg: '/usr/bin/ffmpeg',
    tasks: [
      {
        app: 'live',
        hls: process.env.HLS_ENABLED === 'true',
        hlsFlags: `[hls_time=${process.env.HLS_SEGMENT_TIME || 2}:hls_list_size=${process.env.HLS_LIST_SIZE || 3}:hls_flags=delete_segments]`,
        dash: process.env.DASH_ENABLED === 'true',
        dashFlags: '[f=dash:window_size=3:extra_window_size=5]'
      }
    ]
  },
  
  // Authentication
  auth: {
    enabled: process.env.AUTH_ENABLED === 'true',
    secret: process.env.AUTH_SECRET || 'schoolsaavy_media_secret_2025',
    backendApiUrl: process.env.BACKEND_API_URL || 'http://localhost:8080/api',
    backendApiToken: process.env.BACKEND_API_TOKEN || '',
  },
  
  // Stream Settings
  stream: {
    maxStreamsPerSchool: parseInt(process.env.MAX_STREAMS_PER_SCHOOL) || 10,
    timeout: parseInt(process.env.STREAM_TIMEOUT) || 300000, // 5 minutes
  },
  
  // CORS Settings
  cors: {
    origins: process.env.CORS_ORIGINS ? process.env.CORS_ORIGINS.split(',') : ['*'],
    credentials: process.env.CORS_CREDENTIALS === 'true',
  },
  
  // Logging
  logging: {
    level: process.env.LOG_LEVEL || 'info',
    file: process.env.LOG_FILE || './logs/media-server.log',
  },
  
  // Storage
  storage: {
    mediaRoot: process.env.MEDIA_ROOT || './media',
    recordingsEnabled: process.env.RECORDINGS_ENABLED === 'true',
    recordingsPath: process.env.RECORDINGS_PATH || './recordings',
  },
  
  // Performance
  performance: {
    maxConnections: parseInt(process.env.MAX_CONNECTIONS) || 1000,
    workerThreads: parseInt(process.env.WORKER_THREADS) || 4,
  }
};
