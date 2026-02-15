#!/bin/bash

# Skeedo Docker Helper Script
# This script helps with common Docker operations for the Skeedo platform

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_NAME=${PROJECT_NAME:-skeedo}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

echo_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

echo_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if .env file exists
check_env() {
    if [ ! -f .env ]; then
        echo_warn ".env file not found. Creating from .env.docker..."
        cp .env.docker .env
        echo_warn "Please configure .env file with your settings."
    fi
}

# Function to build Docker image
build() {
    echo_info "Building Docker image..."
    docker build -t $PROJECT_NAME:latest .
    echo_info "Build completed successfully!"
}

# Function to start containers
start() {
    check_env
    echo_info "Starting containers..."
    docker-compose -p $PROJECT_NAME up -d
    echo_info "Containers started successfully!"
    echo_info "Application available at http://localhost:${APP_PORT:-8080}"
}

# Function to stop containers
stop() {
    echo_info "Stopping containers..."
    docker-compose -p $PROJECT_NAME down
    echo_info "Containers stopped!"
}

# Function to restart containers
restart() {
    echo_info "Restarting containers..."
    stop
    sleep 2
    start
}

# Function to view logs
logs() {
    docker-compose -p $PROJECT_NAME logs -f ${1:-}
}

# Function to run Composer commands
composer() {
    docker-compose -p $PROJECT_NAME exec -u www-data app composer $@
}

# Function to run PHP commands
php() {
    docker-compose -p $PROJECT_NAME exec -u www-data app php $@
}

# Function to run Artisan commands
artisan() {
    docker-compose -p $PROJECT_NAME exec -u www-data app php artisan $@
}

# Function to run NPM commands
npm() {
    docker-compose -p $PROJECT_NAME exec app npm $@
}

# Function to run database migrations
migrate() {
    echo_info "Running database migrations..."
    php bin/console.php migrate
    echo_info "Migrations completed!"
}

# Function to seed database
seed() {
    echo_info "Seeding database..."
    php bin/console.php db:seed
    echo_info "Database seeded!"
}

# Function to install dependencies
install() {
    echo_info "Installing dependencies..."
    composer install
    npm install
    npm run build
    echo_info "Installation completed!"
}

# Function to show help
show_help() {
    cat << EOF
Skeedo Docker Helper Script

Usage: ./docker-helper.sh [command]

Commands:
    build       Build Docker image
    start       Start all containers
    stop        Stop all containers
    restart     Restart all containers
    logs        View container logs (optional: specify service name)
    shell       Open shell in app container
    composer    Run composer commands
    php         Run PHP commands
    npm         Run NPM commands
    migrate     Run database migrations
    seed        Seed the database
    install     Install all dependencies
    clean       Remove containers and volumes
    help        Show this help message

Examples:
    ./docker-helper.sh build
    ./docker-helper.sh start
    ./docker-helper.sh logs app
    ./docker-helper.sh composer install
    ./docker-helper.sh php -v
    ./docker-helper.sh npm run dev

EOF
}

# Function to open shell
shell() {
    docker-compose -p $PROJECT_NAME exec -u www-data app sh
}

# Function to clean up
clean() {
    echo_warn "This will remove all containers and volumes. Are you sure? (y/N)"
    read -r response
    if [[ "$response" =~ ^[Yy]$ ]]; then
        echo_info "Cleaning up..."
        docker-compose -p $PROJECT_NAME down -v
        echo_info "Cleanup completed!"
    else
        echo_info "Cleanup cancelled."
    fi
}

# Main command dispatcher
case "${1:-help}" in
    build) build ;;
    start) start ;;
    stop) stop ;;
    restart) restart ;;
    logs) logs "$2" ;;
    shell) shell ;;
    composer) shift; composer "$@" ;;
    php) shift; php "$@" ;;
    npm) shift; npm "$@" ;;
    migrate) migrate ;;
    seed) seed ;;
    install) install ;;
    clean) clean ;;
    help) show_help ;;
    *)
        echo_error "Unknown command: $1"
        show_help
        exit 1
        ;;
esac
