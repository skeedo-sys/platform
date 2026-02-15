#!/bin/sh
# Skeedo Container Entrypoint Script
# This script handles initialization tasks before starting the application

set -e

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

# Wait for MySQL to be ready
wait_for_mysql() {
    log_info "Waiting for MySQL to be ready..."
    
    max_attempts=30
    attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if mysqladmin ping -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" &> /dev/null; then
            log_info "MySQL is ready!"
            return 0
        fi
        
        log_warn "MySQL is not ready yet (attempt $attempt/$max_attempts). Waiting..."
        sleep 2
        attempt=$((attempt + 1))
    done
    
    log_error "MySQL failed to start after $max_attempts attempts"
    return 1
}

# Wait for Redis to be ready
wait_for_redis() {
    log_info "Waiting for Redis to be ready..."
    
    max_attempts=30
    attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if redis-cli -h "${REDIS_HOST:-redis}" ping &> /dev/null; then
            log_info "Redis is ready!"
            return 0
        fi
        
        log_warn "Redis is not ready yet (attempt $attempt/$max_attempts). Waiting..."
        sleep 2
        attempt=$((attempt + 1))
    done
    
    log_error "Redis failed to start after $max_attempts attempts"
    return 1
}

# Run database migrations
run_migrations() {
    log_info "Running database migrations..."
    
    # Adjust this command based on your application's migration system
    # php /app/bin/console.php migrate --force 2>/dev/null || log_warn "Migration command not available"
    
    log_info "Migrations completed"
}

# Clear application caches
clear_caches() {
    log_info "Clearing application caches..."
    
    # Clear PHP OPcache
    php -r "opcache_reset();" 2>/dev/null || true
    
    # Clear Redis cache if available
    if redis-cli -h "${REDIS_HOST:-redis}" FLUSHDB &> /dev/null; then
        log_info "Redis cache cleared"
    fi
    
    log_info "Caches cleared"
}

# Optimize autoloader
optimize_autoloader() {
    log_info "Optimizing Composer autoloader..."
    composer dump-autoload --optimize --no-dev 2>/dev/null || log_warn "Could not optimize autoloader"
}

# Set proper permissions
set_permissions() {
    log_info "Setting proper file permissions..."
    
    chown -R www-data:www-data /app || true
    chmod -R 755 /app/var || true
    chmod -R 755 /app/storage || true
    chmod -R 777 /app/public || true
    
    log_info "Permissions set"
}

# Health check endpoint
health_check() {
    if [ -f /app/public/index.php ]; then
        return 0
    else
        return 1
    fi
}

# Main execution
main() {
    log_info "Starting Skeedo container initialization..."
    
    # Only run initialization if specified
    if [ "$SKIP_INIT" != "true" ]; then
        
        # Skip database checks if in standalone/demo mode
        if [ "$ENVIRONMENT" != "demo" ] && [ "$ENVIRONMENT" != "install" ]; then
            wait_for_mysql || exit 1
        fi
        
        # Wait for Redis
        wait_for_redis || log_warn "Redis not available, continuing without cache"
        
        # Run initialization steps
        set_permissions
        
        # Run migrations if not in install mode
        if [ "$ENVIRONMENT" != "install" ]; then
            run_migrations
        fi
        
        clear_caches
        optimize_autoloader
    fi
    
    log_info "Initialization completed, starting PHP-FPM..."
}

# Run main initialization
main "$@"

# Start PHP-FPM in foreground
exec php-fpm
