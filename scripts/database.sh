#!/bin/bash

# Database Operations Script
# Handles complex database operations

set -e

# Load environment variables
if [ -f "$(dirname "$0")/../.env" ]; then
    source "$(dirname "$0")/../.env"
fi

# Database configuration with environment variable fallbacks
DB_CONTAINER="${PROJECT_NAME:-virgosoft}_mysql"
DB_USER="${DB_USERNAME:-virgosoft}"
DB_PASSWORD="${DB_PASSWORD:-secret}"
DB_NAME="${DB_DATABASE:-virgosoft}"
BACKUP_DIR="backups"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Function to create backup
backup_database() {
    print_status "Creating database backup..."
    mkdir -p $BACKUP_DIR
    timestamp=$(date +%Y%m%d_%H%M%S)
    docker compose exec mysql mysqldump -u$DB_USER -p$DB_PASSWORD $DB_NAME > $BACKUP_DIR/backup_$timestamp.sql
    print_success "Backup created: $BACKUP_DIR/backup_$timestamp.sql"
}

# Function to reset database completely
reset_database() {
    print_warning "This will completely reset the database. Continue? (y/N)"
    read -r confirm
    if [[ $confirm =~ ^[Yy]$ ]]; then
        backup_database
        print_status "Resetting database..."
        docker compose exec api php artisan migrate:fresh --seed
        print_success "Database reset completed"
    else
        print_status "Operation cancelled"
    fi
}

# Function to check database status
check_status() {
    print_status "Database Status:"
    docker compose exec api php artisan migrate:status
    
    print_status "\nRecord Counts:"
    docker compose exec api php artisan tinker --execute="echo 'Users: ' . App\Models\User::count(); echo PHP_EOL; echo 'Plans: ' . App\Models\Plan::count();"
}

case "$1" in
    "backup")
        backup_database
        ;;
    "reset")
        reset_database
        ;;
    "status")
        check_status
        ;;
    *)
        echo "Usage: $0 {backup|reset|status}"
        echo ""
        echo "Commands:"
        echo "  backup  - Create database backup"
        echo "  reset   - Reset database with backup"
        echo "  status  - Show database status"
        exit 1
        ;;
esac
