# SchoolSavvy Docker Deployment with RoadRunner

This setup provides a high-performance Docker deployment for SchoolSavvy using RoadRunner with external MySQL database.

## Prerequisites

- Docker and Docker Compose installed
- External MySQL server accessible
- MySQL database created for SchoolSavvy

## Architecture

- **App Container**: Laravel with RoadRunner for high performance
- **Redis**: For caching and sessions
- **Nginx**: Load balancer and reverse proxy
- **Queue Worker**: Background job processing
- **Scheduler**: Laravel cron jobs
- **External MySQL**: Your existing MySQL server

## Quick Start

### 1. Environment Configuration

Copy the environment template:
```bash
cp .env.production.example .env.production
```

Update `.env.production` with your MySQL credentials:
```env
DB_HOST=your_mysql_server_host
DB_PORT=3306
DB_DATABASE=schoolsavvy
DB_USERNAME=your_mysql_username
DB_PASSWORD=your_mysql_password
```

### 2. Deploy

**Linux/macOS:**
```bash
chmod +x deploy.sh
./deploy.sh
```

**Windows PowerShell:**
```powershell
.\deploy.ps1
```

**Manual deployment:**
```bash
docker-compose up -d
docker-compose exec app php artisan migrate --force
```

## Services

| Service | Internal Port | External Port | Description |
|---------|---------------|---------------|-------------|
| app | 8080 | 8080 | Laravel with RoadRunner |
| nginx | 80 | 80 | Reverse proxy |
| redis | 6379 | 6379 | Cache and sessions |
| queue | - | - | Background jobs |
| scheduler | - | - | Cron jobs |

## Access Points

- **Main Application**: http://localhost:8080
- **Load Balanced**: http://localhost (via Nginx)
- **Health Check**: http://localhost:8080/up

## Management Commands

### View Logs
```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f app
docker-compose logs -f queue
```

### Access Container Shell
```bash
docker-compose exec app sh
```

### Laravel Commands
```bash
# Migrations
docker-compose exec app php artisan migrate

# Clear caches
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear

# Queue management
docker-compose exec app php artisan queue:restart
```

### Container Management
```bash
# Stop all services
docker-compose down

# Restart specific service
docker-compose restart app

# Rebuild and restart
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

## Performance Tuning

### RoadRunner Configuration
Edit `.rr.yaml` to adjust:
- `num_workers`: Number of PHP workers (default: 4)
- `max_worker_memory`: Memory limit per worker (default: 128MB)

### Scaling
Scale individual services:
```bash
# Multiple queue workers
docker-compose up -d --scale queue=3

# Multiple app instances (requires load balancer config)
docker-compose up -d --scale app=2
```

## Monitoring

### Health Checks
- Application: `curl http://localhost:8080/up`
- RoadRunner metrics: `curl http://localhost:2112/metrics`
- RoadRunner status: `curl http://localhost:2114`

### Resource Usage
```bash
# Container stats
docker stats

# Specific service
docker stats schoolsavvy_app
```

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Verify MySQL credentials in `.env.production`
   - Ensure MySQL server is accessible from Docker
   - Check firewall settings

2. **RoadRunner Won't Start**
   - Check RoadRunner logs: `docker-compose logs app`
   - Verify `.rr.yaml` configuration
   - Ensure adequate memory allocation

3. **Performance Issues**
   - Increase RoadRunner workers in `.rr.yaml`
   - Monitor container resources with `docker stats`
   - Check MySQL query performance

### Debug Mode
Enable debug mode temporarily:
```bash
docker-compose exec app php artisan config:set app.debug true
docker-compose restart app
```

## Security Considerations

1. **Database Security**
   - Use strong passwords
   - Limit MySQL access to Docker network
   - Enable MySQL SSL if possible

2. **Application Security**
   - Keep `APP_DEBUG=false` in production
   - Use strong `APP_KEY`
   - Regular security updates

3. **Container Security**
   - Regular image updates
   - Scan for vulnerabilities
   - Limit container privileges

## Backup Strategy

### Database Backups
```bash
# Manual backup
mysqldump -h your_mysql_host -u username -p database_name > backup.sql

# Automated backup (add to cron)
docker-compose exec app php artisan backup:run
```

### File Storage Backups
```bash
# Backup storage directory
tar -czf storage-backup.tar.gz storage/
```

## Production Deployment

For production deployment:

1. Use environment-specific configurations
2. Set up SSL certificates for Nginx
3. Configure proper logging and monitoring
4. Implement automated backups
5. Set up alerting for critical metrics

## Support

For issues and questions:
- Check container logs: `docker-compose logs`
- Review Laravel logs: `storage/logs/laravel.log`
- Monitor RoadRunner metrics at `:2112/metrics`
