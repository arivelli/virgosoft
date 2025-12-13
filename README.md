# Virgosoft

Welcome to the Virgosoft project! This is a clean Laravel application with Docker infrastructure, ready for development.

## About

This project provides a solid foundation for building web applications with Laravel, Docker, and modern development tools. It includes a complete development environment with PHP, MySQL, Redis, and Nginx.

## Features

- **Laravel 12** - Modern PHP framework
- **Docker** - Containerized development environment
- **MySQL 8.0** - Database
- **Redis** - Caching and session storage
- **Nginx** - Web server with SSL support
- **Makefile** - Convenient commands for development
- **PHP 8.3** - Latest PHP version with extensions

## Quick Start

### Prerequisites

- Docker and Docker Compose
- Make (optional, for convenient commands)

### Setup

```bash
# Clone and setup the project
git clone <repository-url>
cd virgosoft

# Start the application (builds, starts, and migrates everything)
make run
```

### Access Points

- **Main site**: https://virgosoft.local.xima.com.ar
- **API**: https://api.virgosoft.local.xima.com.ar
- **API Health**: https://api.virgosoft.local.xima.com.ar/api/health

### Development Commands

```bash
# Start the development environment
make run

# Stop all services
make stop

# Access the PHP container
make shell

# Run Laravel artisan commands
make artisan <command>

# View logs
make logs

# Database access
make db

# Redis CLI
make redis
```

## Project Structure

```
virgosoft/
├── app/                    # Laravel application code
├── config/                 # Laravel configuration
├── database/               # Database migrations and seeders
├── routes/                 # API and web routes
├── etc/                    # Docker configuration files
│   ├── nginx/             # Nginx configuration
│   ├── mysql/             # MySQL configuration
│   ├── redis/             # Redis configuration
│   └── ssl/               # SSL certificates
├── scripts/               # Helper scripts
├── docker-compose.yml     # Docker services definition
├── Dockerfile            # PHP container configuration
├── Makefile              # Development commands
└── README.md             # This file
```

## Configuration

### Environment

Copy `.env.example` to `.env` and configure your settings:

```bash
cp .env.example .env

# Important: APP_KEY must be generated inside the container
docker compose exec api php artisan key:generate
```

If you prefer to generate the key on the host and paste it into `.env`:

```bash
docker compose exec -T api php artisan key:generate --show
```

### Domain Setup

For local development with custom domains:

```bash
# Add domains to /etc/hosts
make setup-hosts
```

If you run `setup-hosts` before containers are up, it will not be able to resolve container IPs.

### SSL Setup (Optional)

For HTTPS development:

```bash
# Generate SSL certificates
make ssl-setup

# Install CA certificates (Linux)
make install-ca-linux

# Prepare Nginx SSL configuration
make setup-ssl
```

Note: ports 80/443 must be free (stop other local stacks using those ports).

## Available API Endpoints

- `GET /api/health` - Health check endpoint

## Development

### Adding New Routes

1. Add routes in `routes/api.php` for API endpoints
2. Add routes in `routes/web.php` for web pages
3. Create controllers in `app/Http/Controllers/`

### Database

```bash
# Create a new migration
make artisan make:migration create_table_name

# Run migrations
make migrate

# Create a seeder
make artisan make:seeder SeederName

# Run seeders
make seed
```

### Testing

```bash
# Run tests
make test

# Run PHPUnit
make phpunit
```

## Docker Services

The project includes the following services:

- **api** - PHP-FPM application server
- **nginx** - Web server and reverse proxy
- **mysql** - Database server
- **redis** - Cache server
- **scheduler** - Laravel task scheduler (optional)

## Production Deployment

For production deployment:

1. Update environment variables
2. Configure proper SSL certificates
3. Set up proper database credentials
4. Configure Redis for production
5. Optimize Laravel:

```bash
make optimize
```

## Need Help?

If you have any questions or issues, please check the documentation or create an issue in the repository.


---
