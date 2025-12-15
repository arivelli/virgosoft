# Trading Platform

A Laravel 12 API with Vue 3 + Tailwind CSS frontend for cryptocurrency trading with real-time order matching.

## Features

- **Backend**: RESTful API with Sanctum authentication
- **Trading Engine**: Order matching with 1.5% commission
- **Real-time**: Pusher WebSocket broadcasting for order matches
- **Frontend**: Vue 3 SPA with Tailwind CSS
- **Database**: MySQL with BCMath for precise financial calculations
- **Tests**: Comprehensive unit and feature tests

## Tech Stack

- **Backend**: Laravel 12, PHP 8.2+, MySQL
- **Frontend**: Vue 3, Vite, Tailwind CSS, Pinia, Vue Router
- **Real-time**: Pusher WebSockets
- **Authentication**: Laravel Sanctum
- **Testing**: PHPUnit, Laravel Testing Tools

## Quick Start

### Prerequisites

- Docker & Docker Compose
- Node.js 18+ (for frontend builds)
- Composer

### Setup

```bash
# Clone and setup the project
git clone <repository-url>
cd virgosoft

# Start the application (builds, starts, and migrates everything)
make run
```

### Access Points

- **Frontend**: https://virgosoft.local.xima.com.ar
- **API**: https://api.virgosoft.local.xima.com.ar
- **API Health**: https://api.virgosoft.local.xima.com.ar/api/health

### Frontend Development

```bash
# Install dependencies
npm install

# Build assets
npm run build

# Development mode
npm run dev
```

## Pusher Configuration (Required for Real-time Features)

1. Sign up at [Pusher.com](https://pusher.com) and create a new Channels app
2. Update the `.env` file with your Pusher credentials:
   ```env
   PUSHER_APP_ID=your-app-id
   PUSHER_APP_KEY=your-app-key
   PUSHER_APP_SECRET=your-app-secret
   PUSHER_APP_CLUSTER=your-cluster
   VITE_PUSHER_APP_KEY=your-app-key
   VITE_PUSHER_APP_CLUSTER=your-cluster
   ```
3. Restart the API container: `docker restart virgosoft_api`
4. Rebuild frontend assets: `npm run build`

## API Endpoints

### Authentication
- `POST /api/login` - Login and receive token
- `POST /api/logout` - Logout
- `GET /api/me` - Get current user

### Trading
- `GET /api/profile` - User balance and assets
- `GET /api/orders?symbol=BTC-USD` - Orderbook (all open orders)
- `POST /api/orders` - Create new order
- `POST /api/orders/{id}/cancel` - Cancel order

### Broadcasting
- `POST /api/broadcasting/auth` - Pusher authentication

## Frontend Features

- **Login/Logout** - Secure authentication flow
- **Dashboard** - Trading interface with:
  - Balance display
  - Assets list (available and locked amounts)
  - Order form (buy/sell)
  - Orders list with cancel functionality
- **Real-time Updates** - Order matches via Pusher
- **Responsive Design** - Mobile-friendly UI

## Testing

### Run All Tests
```bash
make test
```

### Test Users
After running `make migrate && make seed`:
- alice@example.com / password
- bob@example.com / password

### Run Specific Tests
```bash
# Unit tests for matching engine
docker exec virgosoft_api php artisan test --testsuite=Unit

# Feature tests for API
docker exec virgosoft_api php artisan test --testsuite=Feature
```

## Architecture

### Matching Engine
- Full order matching only (no partial fills)
- Price-time priority for order execution
- 1.5% commission on USD value
- Row-level locking for concurrency safety
- Event broadcasting after transaction commit

### Security
- Sanctum token-based authentication
- Private channel authorization for Pusher
- SQL injection protection with parameterized queries
- Transaction isolation for financial operations

## Configuration

### Environment

Copy `.env.example` to `.env` and configure your settings:

```bash
cp .env.example .env

# Important: APP_KEY must be generated inside the container
docker compose exec api php artisan key:generate
```

Update the following variables:
```env
# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=virgosoft_test
DB_USERNAME=root
DB_PASSWORD=root

# Pusher (for real-time updates)
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=mt1

# Frontend (for Vite)
VITE_PUSHER_APP_KEY=your-app-key
VITE_PUSHER_APP_CLUSTER=mt1
```

### Database Setup

```bash
# Run migrations and seed data
make migrate
make seed
```

## Development Commands

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

## Docker Services

- **api** - PHP-FPM application server
- **nginx** - Web server and reverse proxy
- **mysql** - Database server
- **redis** - Cache server
- **scheduler** - Laravel task scheduler (optional)

## Troubleshooting

### Frontend Not Loading
- Ensure assets are built: `npm run build`
- Check nginx container is running: `docker ps | grep nginx`

### Tests Failing
- Verify MySQL test database exists
- Check BCMath extension is installed
- Ensure proper database permissions

### Real-time Updates Not Working
- Verify Pusher credentials in `.env`
- Check broadcasting configuration
- Ensure user is authenticated for private channels

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

## License

MIT
