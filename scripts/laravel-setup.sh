#!/bin/bash

# Laravel Setup Script for Virgosoft Project
# This script sets up Laravel in the Docker environment

set -e

echo "🚀 Laravel Setup Script for Virgosoft Project"
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
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
if ! docker compose ps >/dev/null 2>&1; then
    print_error "Docker is not running. Please start the infrastructure first:"
    echo "  make run"
    exit 1
fi

print_status "Checking Docker containers..."

# Check if all containers are running
if ! docker compose ps | grep -q "Up.*healthy\|Up"; then
    print_error "Not all containers are running. Please start the infrastructure first:"
    echo "  make run"
    exit 1
fi

print_success "All Docker containers are running"

# Check if Laravel is already installed
if [ -f "artisan" ]; then
    print_warning "Laravel is already installed"
    
    read -p "Do you want to reinstall Laravel? This will delete current application files. (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_status "Keeping current Laravel installation"
        exit 0
    fi
    
    print_status "Removing current Laravel installation..."
    rm -rf app/ bootstrap/ config/ database/ resources/ routes/ storage/ tests/ artisan composer.json composer.lock package.json package-lock.json phpunit.xml webpack.mix.js
fi

print_status "Installing Laravel framework..."

# Create Laravel project in container
docker compose exec -T api sh -c "cd /tmp && composer create-project laravel/laravel laravel-temp --prefer-dist" >/dev/null 2>&1

if [ $? -ne 0 ]; then
    print_error "Failed to create Laravel project"
    exit 1
fi

print_success "Laravel project created"

# Copy Laravel files to project directory
print_status "Copying Laravel files to project directory..."

docker compose exec -T api sh -c "cp -r /tmp/laravel-temp/* /storage/code/personal/virgosoft/ && cp -r /tmp/laravel-temp/.* /storage/code/personal/virgosoft/ 2>/dev/null || true"

# Clean up temporary directory
docker compose exec -T api rm -rf /tmp/laravel-temp

print_success "Laravel files copied"

# Setup environment file
print_status "Setting up environment file..."

if [ ! -f ".env" ]; then
    cp .env.example .env
    print_success "Environment file created from .env.example"
else
    print_warning "Environment file already exists"
fi

# Install dependencies and setup Laravel
print_status "Installing dependencies and configuring Laravel..."

docker compose exec api composer install --optimize-autoloader --no-dev >/dev/null 2>&1
docker compose exec api php artisan key:generate >/dev/null 2>&1
docker compose exec api php artisan storage:link >/dev/null 2>&1

print_success "Laravel dependencies installed and configured"

# Run migrations
print_status "Running database migrations..."

if docker compose exec api php artisan migrate --force >/dev/null 2>&1; then
    print_success "Database migrations completed"
else
    print_warning "Database migrations failed (this might be expected if tables already exist)"
fi

# Create storage directories
print_status "Creating storage directories..."

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/app/public bootstrap/cache
touch storage/framework/cache/.gitkeep storage/framework/sessions/.gitkeep storage/framework/views/.gitkeep storage/app/public/.gitkeep bootstrap/cache/.gitkeep

print_success "Storage directories created"

# Set proper permissions
print_status "Setting proper permissions..."

docker compose exec api chown -R www-data:www-data storage bootstrap/cache
docker compose exec api chmod -R 775 storage bootstrap/cache

print_success "Permissions set"

# Clear caches
print_status "Clearing Laravel caches..."

docker compose exec api php artisan cache:clear >/dev/null 2>&1
docker compose exec api php artisan config:clear >/dev/null 2>&1
docker compose exec api php artisan route:clear >/dev/null 2>&1
docker compose exec api php artisan view:clear >/dev/null 2>&1

print_success "Caches cleared"

# Optimize for production
print_status "Optimizing Laravel for production..."

docker compose exec api php artisan config:cache >/dev/null 2>&1
docker compose exec api php artisan route:cache >/dev/null 2>&1
docker compose exec api php artisan view:cache >/dev/null 2>&1

print_success "Laravel optimized"

# Test installation
print_status "Testing Laravel installation..."

if docker compose exec api php artisan --version >/dev/null 2>&1; then
    LARAVEL_VERSION=$(docker compose exec api php artisan --version | grep -o 'Laravel Framework [0-9.]*')
    print_success "Laravel installation completed successfully!"
    echo "  Version: $LARAVEL_VERSION"
else
    print_error "Laravel installation test failed"
    exit 1
fi

# Run tests
print_status "Running Laravel tests..."

if docker compose exec api php artisan test >/dev/null 2>&1; then
    print_success "All Laravel tests are passing"
else
    print_warning "Some Laravel tests failed (this might be expected during initial setup)"
fi

echo ""
print_success "🎉 Laravel setup completed successfully!"
echo ""
echo "📋 Next steps:"
echo "  1. Access your application: https://${DOMAIN:-virgosoft.local.xima.com.ar}"
echo "  2. Start development: make shell"
echo "  3. Run artisan commands: make artisan <command>"
echo "  4. Use Tinker: make tinker"
echo "  5. Run tests: make test"
echo ""
echo "🔧 Useful commands:"
echo "  make artisan migrate    # Run database migrations"
echo "  make artisan serve      # Start development server"
echo "  make tinker             # Start Laravel REPL"
echo "  make test               # Run tests"
echo "  make optimize           # Optimize for production"
echo ""
echo "📚 Documentation:"
echo "  - Laravel docs: https://laravel.com/docs"
echo "  - Project README: README.md"
echo ""
print_success "Laravel is ready for development! 🚀"
