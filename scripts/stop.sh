#!/usr/bin/env bash
set -euo pipefail

# Virgosoft Project - Development Stop Script
# This script stops the Docker development environment

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
}

# Stop containers
stop_containers() {
    print_status "Stopping Docker containers..."
    
    cd "$PROJECT_DIR"
    $DOCKER_COMPOSE down
    
    print_success "Containers stopped successfully"
}

# Optional: Clean up volumes (add --clean flag)
cleanup_volumes() {
    if [ "${1:-}" = "--clean" ]; then
        print_warning "Removing Docker volumes (this will delete all data)..."
        cd "$PROJECT_DIR"
        $DOCKER_COMPOSE down -v
        print_success "Docker volumes removed"
    fi
}

# Show status
show_status() {
    print_status "Container status after stopping:"
    cd "$PROJECT_DIR"
    $DOCKER_COMPOSE ps
    
    echo ""
    print_status "To start the environment again, run:"
    echo "  ./scripts/start.sh"
}

# Main execution
main() {
    print_status "Stopping Virgosoft development environment..."
    echo ""
    
    check_docker_compose
    stop_containers
    cleanup_volumes "$1"
    show_status
    
    echo ""
    print_success "Development environment stopped successfully!"
}

# Run main function
main "$@"
