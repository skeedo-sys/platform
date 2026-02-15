# Skeedo Docker Deployment Guide

This guide explains how to build, deploy, and run the Skeedo platform using Docker.

## Prerequisites

- Docker (version 20.10+)
- Docker Compose (version 1.29+)
- Minimum 4GB RAM allocated to Docker
- 20GB available disk space

## Quick Start

### 1. Clone the Repository

```bash
git clone <repository-url>
cd platform
```

### 2. Configure Environment

Copy the example Docker environment file:

```bash
cp .env.docker .env
```

Edit `.env` and update:
- `APP_DOMAIN`: Your domain (default: app.skeedo.cloud)
- `DB_PASSWORD`: Secure database password
- `MYSQL_ROOT_PASSWORD`: Secure root password
- `JWT_TOKEN`: Generate with `openssl rand -base64 32`
- API keys for external services (OpenAI, Stripe, etc.)

### 3. Build and Start

Using the helper script:

```bash
./docker-helper.sh build
./docker-helper.sh start
```

Or using Docker Compose directly:

```bash
docker-compose up -d --build
```

### 4. Verify Installation

```bash
docker-compose ps
docker-compose logs app
```

Visit http://localhost:8080 in your browser.

## Docker Services

The docker-compose.yml includes:

- **app**: PHP 8.2-FPM application server
- **nginx**: Reverse proxy and web server
- **mysql**: MySQL 8.0 database
- **redis**: Redis cache and session storage

### Service Details

#### PHP Application (app)

- PHP 8.2-FPM
- All required PHP extensions pre-installed
- Composer dependencies auto-installed
- Assets built with Vite
- Logs streamed to stdout

#### Nginx Reverse Proxy

- Serves static assets with optimal caching
- Handles PHP requests via FastCGI
- SSL/TLS support for production
- Gzip compression enabled
- Security headers configured

#### MySQL Database

- MySQL 8.0 Alpine
- Automatic database creation
- Health checks enabled
- Persistent volume storage
- Port 3306 exposed locally

#### Redis Cache

- Redis 7 Alpine
- Session and cache storage
- Auto-persistence
- Health checks enabled
- Port 6379 exposed locally

## Common Commands

### Using Helper Script

```bash
# View help
./docker-helper.sh help

# Build Docker image
./docker-helper.sh build

# Start containers
./docker-helper.sh start

# Stop containers
./docker-helper.sh stop

# Restart containers
./docker-helper.sh restart

# View logs
./docker-helper.sh logs
./docker-helper.sh logs app
./docker-helper.sh logs nginx

# Open shell
./docker-helper.sh shell

# Run Composer
./docker-helper.sh composer install
./docker-helper.sh composer update

# Run PHP
./docker-helper.sh php -v

# Run NPM
./docker-helper.sh npm install
./docker-helper.sh npm run dev

# Run database migrations
./docker-helper.sh migrate

# Clean up (remove containers and volumes)
./docker-helper.sh clean
```

### Using Docker Compose Directly

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f

# Execute commands in app container
docker-compose exec app php -v
docker-compose exec app composer install

# Open shell
docker-compose exec app sh
docker-compose exec app bash

# View container status
docker-compose ps

# Remove everything (including volumes)
docker-compose down -v
```

## Production Deployment

### 1. SSL/TLS Configuration

Create `docker/ssl/` directory with your certificates:

```bash
mkdir -p docker/ssl
cp /path/to/your/certs/cert.pem docker/ssl/
cp /path/to/your/certs/key.pem docker/ssl/
```

Update `.env`:

```env
APP_HTTPS_PORT=443
APP_DOMAIN=your-domain.com
```

### 2. Environment for Production

Update `.env` with production settings:

```env
ENVIRONMENT=prod
DEBUG=false
CACHE=true
```

### 3. Database Backup Strategy

Regular backups are essential:

```bash
# Create backup
docker-compose exec mysql mysqldump -u skeedo -p$(grep DB_PASSWORD .env | cut -d '=' -f2) skeedo > backup-$(date +%Y%m%d_%H%M%S).sql

# Restore from backup
docker-compose exec -T mysql mysql -u skeedo -p$(grep DB_PASSWORD .env | cut -d '=' -f2) skeedo < backup-file.sql
```

### 4. Resource Limits

Update `docker-compose.yml` for production:

```yaml
services:
  app:
    deploy:
      resources:
        limits:
          cpus: '2'
          memory: 2G
        reservations:
          cpus: '1'
          memory: 1G
  nginx:
    deploy:
      resources:
        limits:
          cpus: '1'
          memory: 512M
  mysql:
    deploy:
      resources:
        limits:
          cpus: '2'
          memory: 2G
```

## Troubleshooting

### Application won't start

```bash
# Check logs
docker-compose logs app

# Check health status
docker-compose ps

# Restart containers
docker-compose restart
```

### Database connection issues

```bash
# Check MySQL is running
docker-compose exec mysql mysqladmin ping -uroot -p$(grep MYSQL_ROOT_PASSWORD .env | cut -d '=' -f2)

# Check connectivity from app
docker-compose exec app ping mysql
```

### Out of memory errors

```bash
# Increase Docker memory allocation in Docker Desktop settings
# Or update resource limits in docker-compose.yml
```

### Slow performance

```bash
# Check container resource usage
docker stats

# Clear Redis cache
./docker-helper.sh php -r "redis_flushall();"

# Rebuild opcache
./docker-helper.sh php bin/console.php cache:clear
```

## Advanced Configuration

### Custom PHP Settings

Edit `docker/php.ini` to modify:
- Memory limits
- Upload sizes
- Timeout values
- Error reporting

### Custom Nginx Settings

Edit `docker/nginx.conf` to modify:
- SSL configuration
- Cache headers
- Security headers
- Rate limiting

### Custom PHP-FPM Settings

Edit `docker/php-fpm.conf` to modify:
- Worker process count
- Request handling
- Logging

## Security Best Practices

1. **Change default passwords** in `.env`
2. **Use strong JWT tokens** - generate with `openssl rand -base64 32`
3. **Enable SSL/TLS** in production
4. **Restrict database access** - don't expose MySQL port to public
5. **Keep Docker images updated** - regularly pull latest base images
6. **Monitor logs** - review security logs regularly
7. **Use secrets management** - avoid committing `.env` to version control
8. **Regular backups** - automate database backups

## Monitoring and Logging

### View Logs

```bash
# All containers
docker-compose logs -f

# Specific service
docker-compose logs -f app
docker-compose logs -f nginx
docker-compose logs -f mysql

# Last 100 lines
docker-compose logs --tail=100 app
```

### Health Checks

All services have health checks configured:

```bash
# Check health status
docker-compose ps

# Monitor in real-time
watch docker-compose ps
```

### Performance Monitoring

```bash
# CPU and memory usage
docker stats

# Container resource statistics
docker top skeedo_app
```

## Maintenance

### Regular Updates

```bash
# Pull latest base images
docker-compose pull

# Rebuild and restart
docker-compose up -d --build
```

### Database Optimization

```bash
# Run database optimization
docker-compose exec mysql mysql -u skeedo -p$(grep DB_PASSWORD .env | cut -d '=' -f2) skeedo -e "OPTIMIZE TABLE;"
```

### Clean Up

```bash
# Remove unused images
docker image prune

# Remove unused volumes
docker volume prune

# Full cleanup (use with caution)
./docker-helper.sh clean
```

## Support and Resources

- Documentation: https://docs.app.skeedo.cloud
- GitHub Issues: [project-repository]/issues
- Community: [discussion forum/chat]

## License

See LICENSE file in the project root.
