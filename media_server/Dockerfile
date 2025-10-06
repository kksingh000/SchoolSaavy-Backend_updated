FROM node:18-alpine

# Install FFmpeg for transcoding
RUN apk add --no-cache ffmpeg

# Create app directory
WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci --only=production

# Copy application files
COPY . .

# Create necessary directories
RUN mkdir -p media logs recordings

# Expose ports
# 1935 - RTMP
# 8000 - HTTP/API
# 8001 - Internal HTTP
EXPOSE 1935 8000 8001

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
  CMD node -e "require('http').get('http://localhost:8000/health', (res) => { process.exit(res.statusCode === 200 ? 0 : 1); }).on('error', () => { process.exit(1); });"

# Start the server
CMD ["npm", "start"]
