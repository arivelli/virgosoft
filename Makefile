# Virgosoft Project Makefile
# Provides convenient commands for development and deployment

.PHONY: help setup run start stop restart build clean logs shell db redis install laravel-setup artisan composer npm tinker migrate seed clear test phpunit optimize lint pint security ssl-setup ssl-renew install-ca install-ca-linux install-ca-firefox install-ca-chrome install-ca-postman install-ca-docker install-ca-all bundle-ca setup-ssl test-ssl setup-hosts scheduler-start scheduler-stop scheduler-logs scheduler-status analyse ci github-actions

# Default target
help:
	@echo "Virgosoft Project - Available Commands:"
	@echo ""
	@echo "  run      - Start the development environment (same as start)"
	@echo "  setup    - Complete project setup (install + docker + migrations)"
	@echo "  install  - Install PHP dependencies and configure Laravel"
	@echo "  start    - Start all Docker containers"
	@echo "  stop     - Stop all Docker containers"
	@echo "  restart  - Restart all Docker containers"
	@echo "  build    - Build Docker images"
	@echo "  clean    - Stop containers and remove volumes"
	@echo "  logs     - Show logs for all services"
	@echo "  shell    - Access the PHP container shell"
	@echo "  db       - Access MySQL shell"
	@echo "  redis    - Access Redis CLI"
	@echo ""
	@echo "Laravel Development:"
	@echo "  install  - Install Laravel dependencies and setup"
	@echo "  laravel-setup - Complete Laravel installation script"
	@echo "  artisan  - Run Laravel artisan commands"
	@echo "  composer - Run Composer commands"
	@echo "  npm      - Run NPM commands"
	@echo "  tinker   - Start Laravel Tinker"
	@echo "  migrate  - Run database migrations"
	@echo "  seed     - Run database seeders"
	@echo "  test     - Run Laravel tests"
	@echo "  phpunit  - Run PHPUnit tests"
	@echo "  optimize - Optimize Laravel for production"
	@echo "  lint     - Fix code style with PHP CS Fixer"
	@echo "  pint     - Fix code style with Laravel Pint"
	@echo "  security - Run Composer security audit"
	@echo "  analyse  - Run PHPStan static analysis"
	@echo "  ci       - Run full CI pipeline locally"
	@echo "  github-actions - Test GitHub Actions workflows locally using act"
	@echo ""
	@echo "Scheduler Management:"
	@echo "  scheduler-start  - Start Laravel scheduler container"
	@echo "  scheduler-stop   - Stop Laravel scheduler container"
	@echo "  scheduler-logs   - Show scheduler container logs"
	@echo "  scheduler-status - Check scheduler container status"
	@echo ""
	@echo "SSL Certificate Management:"
	@echo "  ssl-setup       - Create SSL certificates and local CA"
	@echo "  ssl-renew       - Renew SSL certificates"
	@echo "  install-ca      - Install CA in all systems"
	@echo "  install-ca-linux    - Install CA in Linux system"
	@echo "  install-ca-firefox  - Install CA in Firefox"
	@echo "  install-ca-chrome   - Install CA in Chrome/Chromium"
	@echo "  install-ca-postman  - Install CA in Postman"
	@echo "  install-ca-docker   - Install CA in Docker containers"
	@echo "  install-ca-all      - Install CA in all systems"
	@echo "  bundle-ca       - Create CA installation bundle"
	@echo "  setup-ssl       - Configure Nginx for SSL"
	@echo "  test-ssl        - Test SSL configuration"
	@echo "  setup-hosts     - Configure domain in /etc/hosts"

# Main run target (as required by the assessment)
run: 
	@echo "Starting Virgosoft development environment..."
	@./scripts/start.sh
	@echo "Waiting for database to be fully ready..."
	@sleep 10
	@echo "Running database migrations..."
	@docker compose exec api php artisan migrate --force
	@echo "✅ Application is ready!"

# Start the development environment
start:
	@echo "Starting Virgosoft development environment..."
	@./scripts/start.sh

# Stop the development environment
stop:
	@echo "Stopping Virgosoft development environment..."
	@./scripts/stop.sh

# Restart all containers
restart: stop start

# Build Docker images
build:
	@echo "Building Docker images..."
	@docker compose build

# Clean up (stop and remove volumes)
clean:
	@echo "Cleaning up Docker environment..."
	@./scripts/stop.sh --clean

# Show logs
logs:
	@docker compose logs -f

# Show logs for specific service
logs-api:
	@docker compose logs -f api

logs-nginx:
	@docker compose logs -f nginx

logs-mysql:
	@docker compose logs -f mysql

logs-redis:
	@docker compose logs -f redis

# Access PHP container shell
shell:
	@docker compose exec api bash

# Access MySQL shell
db:
	@docker compose exec mysql mysql -ubase -psecret base

# Access Redis CLI
redis:
	@docker compose exec redis redis-cli

# Show container status
status:
	@docker compose ps

# Show Docker resource usage
stats:
	@docker stats

# Backup database
backup:
	@mkdir -p backups
	@docker compose exec mysql mysqldump -ubase -psecret base > backups/backup_$(shell date +%Y%m%d_%H%M%S).sql
	@echo "Database backed up to backups/"

# Restore database from backup
restore:
	@echo "Available backups:"
	@ls -la backups/*.sql 2>/dev/null || echo "No backups found"
	@echo "Usage: make restore BACKUP=backups/filename.sql"

# Development helpers
php:
	@docker compose exec api php $(ARGS)

# Laravel Development Commands
setup: install
	@echo "Setting up environment..."
	@docker compose build
	@docker compose up -d
	@sleep 10
	@docker compose exec api php artisan migrate
	@echo "✅ Setup complete! Use 'make run' to start the development server."

install:
	@echo "Installing Laravel dependencies..."
	@composer install
	@if [ ! -f ".env" ]; then \
		cp .env.example .env; \
	fi
	@php artisan key:generate
	@php artisan storage:link

laravel-setup:
	@echo "Setting up Laravel framework..."
	@./scripts/laravel-setup.sh

artisan:
	@docker compose exec api php artisan $(ARGS)

composer:
	@docker compose exec api composer $(ARGS)

npm:
	@docker compose exec api npm $(ARGS)

tinker:
	@docker compose exec api php artisan tinker

migrate:
	@echo "Running database migrations..."
	@docker compose exec api php artisan migrate

seed:
	@echo "Running database seeders..."
	@docker compose exec api php artisan db:seed

clear:
	@echo "Clearing Laravel caches..."
	@docker compose exec api php artisan cache:clear
	@docker compose exec api php artisan config:clear
	@docker compose exec api php artisan route:clear
	@docker compose exec api php artisan view:clear

test:
	@docker compose exec api php artisan test

phpunit:
	@docker compose exec api vendor/bin/phpunit

optimize:
	@echo "Optimizing Laravel for production..."
	@docker compose exec api php artisan config:cache
	@docker compose exec api php artisan route:cache
	@docker compose exec api php artisan view:cache

lint:
	@docker compose exec api ./vendor/laravel/pint/builds/pint --dirty

pint:
	@docker compose exec api vendor/laravel/pint/builds/pint --dirty

security:
	@docker compose exec api composer audit

# SSL Certificate Management
ssl-setup:
	@echo "Setting up SSL certificates and local CA..."
	@./scripts/ssl-setup.sh

ssl-renew:
	@echo "Renewing SSL certificates..."
	@rm -f etc/ssl/certs/*.cert.pem etc/ssl/certs/*.csr.pem etc/ssl/certs/*.ext.cnf
	@./scripts/ssl-setup.sh

install-ca:
	@echo "Installing CA certificate in all systems..."
	@./scripts/install-ca.sh all

install-ca-linux:
	@echo "Installing CA certificate in Linux system..."
	@./scripts/install-ca.sh linux

install-ca-firefox:
	@echo "Installing CA certificate in Firefox..."
	@./scripts/install-ca.sh firefox

install-ca-chrome:
	@echo "Installing CA certificate in Chrome/Chromium..."
	@./scripts/install-ca.sh chrome

install-ca-postman:
	@echo "Installing CA certificate in Postman..."
	@./scripts/install-ca.sh postman

install-ca-docker:
	@echo "Installing CA certificate in Docker containers..."
	@./scripts/install-ca.sh docker

install-ca-all:
	@echo "Installing CA certificate in all systems..."
	@./scripts/install-ca.sh all

bundle-ca:
	@echo "Creating CA installation bundle..."
	@./scripts/install-ca.sh bundle

setup-ssl:
	@echo "Setting up SSL configuration for Nginx..."
	@if [ ! -f "${SSL_CERT_PATH:-/etc/nginx/ssl/certs/virgosoft.local.xima.com.ar.cert.pem}" ]; then \
		echo "SSL certificates not found. Please run 'make ssl-setup' first."; \
		exit 1; \
	fi
	@# Create SSL configuration for Nginx
	@mkdir -p etc/nginx/conf.d
	@cat etc/nginx/conf.d/default.conf > etc/nginx/conf.d/default.conf.bak
	@# This will be updated by the SSL setup script
	@echo "SSL configuration prepared. Restart services with 'make restart'"

test-ssl:
	@echo "Testing SSL configuration..."
	@curl -I https://virgosoft.local.xima.com.ar 2>/dev/null || echo "❌ SSL test failed - check certificates and installation"
	@curl -I https://api.virgosoft.local.xima.com.ar 2>/dev/null || echo "❌ API SSL test failed - check certificates and installation"
	@echo "SSL test completed. Check results above."

# Configure domain in /etc/hosts
setup-hosts:
	@echo "Configuring domain in /etc/hosts..."
	@echo "This will add entries for all containers to your /etc/hosts file."
	@echo "Current entries in /etc/hosts:"
	@grep -n "virgosoft.local.xima.com.ar\|api.virgosoft.local.xima.com.ar\|mysql.virgosoft.local.xima.com.ar\|redis.virgosoft.local.xima.com.ar" /etc/hosts 2>/dev/null || echo "  No entries found"
	@echo ""
	@echo "Adding domains to /etc/hosts..."
	@if grep -q "virgosoft.local.xima.com.ar\|api.virgosoft.local.xima.com.ar\|mysql.virgosoft.local.xima.com.ar\|redis.virgosoft.local.xima.com.ar" /etc/hosts; then \
		echo "Updating existing entries..."; \
		sudo sed -i '/virgosoft.local.xima.com.ar\|api.virgosoft.local.xima.com.ar\|mysql.virgosoft.local.xima.com.ar\|redis.virgosoft.local.xima.com.ar/d' /etc/hosts; \
	fi
	@echo "# Virgosoft Project Containers" | sudo tee -a /etc/hosts
	@docker compose exec -T nginx hostname -i | tr -d '\n' | xargs -I {} echo {} virgosoft.local.xima.com.ar api.virgosoft.local.xima.com.ar | sudo tee -a /etc/hosts
	@docker inspect $$(docker compose ps -q mysql) | grep -o '"IPAddress": "[^"]*"' | grep -o '[0-9.]*' | xargs -I {} echo {} mysql.virgosoft.local.xima.com.ar | sudo tee -a /etc/hosts
	@docker compose exec -T redis hostname -i | tr -d '\n' | xargs -I {} echo {} redis.virgosoft.local.xima.com.ar | sudo tee -a /etc/hosts
	@echo "✅ Domains added to /etc/hosts"
	@echo "You can now access:"
	@echo "  - http://virgosoft.local.xima.com.ar (Web application)"
	@echo "  - http://api.virgosoft.local.xima.com.ar (API endpoints)"
	@echo "  - mysql://mysql.virgosoft.local.xima.com.ar:3306 (MySQL database)"
	@echo "  - redis://redis.virgosoft.local.xima.com.ar:6379 (Redis cache)"

# Remove domain entries from /etc/hosts
clean-hosts:
	@echo "Removing Virgosoft Project entries from /etc/hosts..."
	@if grep -q "virgosoft.local.xima.com.ar\|api.virgosoft.local.xima.com.ar\|mysql.virgosoft.local.xima.com.ar\|redis.virgosoft.local.xima.com.ar" /etc/hosts; then \
		echo "Found entries, removing..."; \
		sudo sed -i '/virgosoft.local.xima.com.ar\|api.virgosoft.local.xima.com.ar\|mysql.virgosoft.local.xima.com.ar\|redis.virgosoft.local.xima.com.ar/d' /etc/hosts; \
		echo "✅ Entries removed from /etc/hosts"; \
	else \
		echo "No Virgosoft Project entries found in /etc/hosts"; \
	fi

# Scheduler Management Commands
scheduler-start:
	@echo "Starting Laravel scheduler container..."
	@docker compose up -d scheduler

scheduler-stop:
	@echo "Stopping Laravel scheduler container..."
	@docker compose stop scheduler

scheduler-logs:
	@echo "Showing scheduler container logs..."
	@docker compose logs -f scheduler

scheduler-status:
	@echo "Checking scheduler container status..."
	@docker compose ps scheduler

# CI/CD Commands
analyse:
	@echo "Running PHPStan static analysis..."
	@docker compose exec api ./vendor/phpstan/phpstan/phpstan analyse app --level=1 --memory-limit=2G --no-progress

ci:
	@echo "Running full CI pipeline locally..."
	@echo "Step 1: Code style check..."
	@docker compose exec api ./vendor/laravel/pint/builds/pint --test
	@echo "Step 2: Static analysis..."
	@docker compose exec api ./vendor/phpstan/phpstan/phpstan analyse app --level=1 --memory-limit=2G --no-progress
	@echo "Step 3: Security audit..."
	@docker compose exec api composer audit
	@echo "Step 4: Running tests..."
	@docker compose exec api php artisan test
	@echo "✅ CI pipeline completed successfully!"

github-actions:
	@echo "Testing GitHub Actions workflows locally using act..."
	@echo "🚀 Running Local Test CI workflow..."
	@act -j local-test -W .github/workflows/local-test-success.yml
	@echo ""
	@echo "✅ GitHub Actions verification completed successfully!"
	@echo ""
	@echo "📋 Available workflows:"
	@echo "  make github-actions              # Run local test workflow (recommended)"
	@echo "  act -j quick-test -W .github/workflows/quick-ci.yml    # Quick CI workflow"
	@echo "  act -j test -W .github/workflows/ci-cd.yml             # Full CI/CD workflow"
