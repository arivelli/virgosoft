#!/usr/bin/env bash
set -euo pipefail

# Virgosoft Project - Development Start Script
# This script sets up and starts the Docker development environment

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
PROJECT_DIR=$(cd "${SCRIPT_DIR}/.." && pwd)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Docker is running
check_docker() {
    if ! docker info >/dev/null 2>&1; then
        print_error "Docker is not running. Please start Docker first."
        exit 1
    fi
    print_success "Docker is running"
}

# Check if docker-compose is available
check_docker_compose() {
    if ! command -v docker-compose >/dev/null 2>&1; then
        if ! docker compose version >/dev/null 2>&1; then
            print_error "docker-compose is not available. Please install docker-compose."
            exit 1
        fi
        DOCKER_COMPOSE="docker compose"
    else
        DOCKER_COMPOSE="docker-compose"
    fi
    print_success "Using: $DOCKER_COMPOSE"
}

# Create .env file if it doesn't exist
setup_env() {
    if [ ! -f "$PROJECT_DIR/.env" ]; then
        print_warning ".env file not found. Creating from .env.example"
        cp "$PROJECT_DIR/.env.example" "$PROJECT_DIR/.env"
        print_success "Created .env file from .env.example"
        print_warning "Please review and update the .env file if needed"
    else
        print_success ".env file exists"
    fi
}

# Create necessary directories
create_directories() {
    print_status "Creating necessary directories..."
    
    mkdir -p "$PROJECT_DIR/logs/nginx"
    mkdir -p "$PROJECT_DIR/logs/mysql"
    mkdir -p "$PROJECT_DIR/logs/php"
    
    print_success "Directories created"
}

# Build and start containers
start_containers() {
    print_status "Building and starting Docker containers..."
    
    cd "$PROJECT_DIR"
    
    # Build containers
    $DOCKER_COMPOSE build
    
    # Start containers
    $DOCKER_COMPOSE up -d
    
    print_success "Containers started successfully"
}

# Wait for services to be ready
wait_for_services() {
    print_status "Waiting for services to be ready..."
    
    # Wait for MySQL
    print_status "Waiting for MySQL..."
    timeout 60 bash -c 'until docker exec ${PROJECT_NAME:-virgosoft}_mysql mysqladmin ping -h"localhost" --silent; do sleep 1; done'
    
    # Wait for Redis
    print_status "Waiting for Redis..."
    timeout 30 bash -c 'until docker exec ${PROJECT_NAME:-virgosoft}_redis redis-cli ping; do sleep 1; done'
    
    # Wait for PHP-FPM
    print_status "Waiting for PHP-FPM..."
    timeout 30 bash -c 'until docker exec ${PROJECT_NAME:-virgosoft}_api php-fpm-healthcheck; do sleep 1; done'
    
    print_success "All services are ready"
}

# Show status
show_status() {
    print_status "Container status:"
    cd "$PROJECT_DIR"
    $DOCKER_COMPOSE ps
    
    echo ""
    print_status "Application URLs:"
    echo "  Main application: http://${DOMAIN:-virgosoft.local.xima.com.ar}"
    echo "  API endpoint: http://${API_DOMAIN:-api.virgosoft.local.xima.com.ar}"
    
    echo ""
    print_status "Useful commands:"
    echo "  View logs: $DOCKER_COMPOSE logs -f [service_name]"
    echo "  Stop services: $DOCKER_COMPOSE down"
    echo "  Restart services: $DOCKER_COMPOSE restart"
    echo "  Access PHP container: $DOCKER_COMPOSE exec api bash"
    echo "  Access MySQL: $DOCKER_COMPOSE exec mysql mysql -u${DB_USERNAME:-virgosoft} -p${DB_PASSWORD:-secret} ${DB_DATABASE:-virgosoft}"
    echo "  Access Redis: $DOCKER_COMPOSE exec redis redis-cli"
}

# Main execution
main() {
    print_status "Starting Virgosoft development environment..."
    echo ""
    
    check_docker
    check_docker_compose
    setup_env
    create_directories
    start_containers
    wait_for_services
    show_status
    
    echo ""
    print_success "Development environment is ready! 🚀"
}

# Run main function
main "$@"
