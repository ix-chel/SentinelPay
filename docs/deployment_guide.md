# SentinelPay Deployment Guide

This guide covers how to deploy the SentinelPay backend API using Docker compose for production or staging environments.

## Prerequisites
- Docker Engine & Docker Compose
- Minimum 2GB RAM
- `openssl` (for testing/generating HMAC secrets)

## Environment Configuration

Copy the example environment file and configure it:
```bash
cp .env.example .env
```

### Critical Variables
Ensure the following variables are properly set in your `.env` file before starting the containers:

| Variable | Description | Default |
| -------- | ----------- | ------- |
| `APP_ENV` | Environment type (`production`, `local`) | `production` |
| `DB_CONNECTION` | Database driver (Must be `pgsql`) | `pgsql` |
| `REDIS_HOST` | Redis Server hostname | `redis` |
| `RABBITMQ_HOST` | RabbitMQ Server hostname | `rabbitmq` |
| `QUEUE_CONNECTION` | Default queue driver | `rabbitmq` |
| `HMAC_SECRET` | 32+ character crypto-secure string | *(Generate a new one)* |

Generate a secure HMAC key using `openssl`:
```bash
openssl rand -hex 32
```

## Running the Application

1. **Start the containers in detached mode:**
   ```bash
   docker compose up -d --build
   ```

2. **Generate the application key:**
   ```bash
   docker compose exec app php artisan key:generate
   ```

3. **Run database migrations:**
   ```bash
   docker compose exec app php artisan migrate --force
   ```

## Infrastructure Components

- **App (PHP-FPM + Nginx)**: Handles incoming HTTP traffic. Runs on port `8080`.
- **PostgreSQL**: The relational database. Runs on port `5432`.
- **Redis**: Handles Idempotency checks and Cache. Runs on port `6379`.
- **RabbitMQ**: Message queue. Runs on port `5672` (Broker) and `15672` (Management UI).

## Monitoring & System Integrity

### Ledger Audit
To ensure no database manipulation has occurred, run the periodic ledger audit command:
```bash
docker compose exec app php artisan audit:ledger
```
This tool recalculates every account balance based strictly on the immutable `ledgers` table log, verifying that it precisely matches the instantaneous balance stored on the `accounts` table.

### RabbitMQ Management
You can monitor queue health and unprocessed webhook jobs via the RabbitMQ UI at `http://localhost:15672` (Default credentials: guest / guest).
