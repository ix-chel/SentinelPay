# SentinelPay

SentinelPay is a payment API built with **Laravel 12**, **PostgreSQL**, **Redis**, and **RabbitMQ**. It uses row-level locking and append-only ledger entries to keep balance mutations and transfer history consistent.

## Key Architecture & Features

### 1. PostgreSQL Transfer Integrity
Transfers are executed inside a PostgreSQL transaction and use row-level locks on both accounts.
- Concurrent debits are serialized with `SELECT ... FOR UPDATE`.
- Each successful transfer writes paired debit and credit ledger entries.
- The `audit:ledger` command verifies that account balances match ledger totals.

### 2. Idempotency & Fault Tolerance
The `POST /api/v1/transfers` endpoint enforces idempotency using the `Idempotency-Key` header.
- Safely retry failed network requests without the risk of double-charging.
- Idempotent responses are persisted and cached so retries return the same transfer without replaying balance mutations.

### 3. API Key Management & Security
- **Hashed Storage**: API keys are generated as cryptographically secure strings and safely stored via SHA-256 hashes.
- **Scoping & Rate Limiting**: Keys can be restricted to specific endpoints and are rate-limited per minute.
- **Request Signing (HMAC)**: Webhook deliveries to merchants are signed (`Stripe-Signature` style) using HMAC-SHA256 to allow merchants to verify payload authenticity.

### 4. Webhook Eventing
- Async delivery of events (e.g., `transfer.succeeded`) to registered merchant endpoints.
- Managed by Laravel Queues featuring exponential backoff for failed deliveries.

### 5. Automated API Documentation
- Zero-config OpenAPI specifications are automatically generated.
- Accessible via Swagger UI at `http://localhost:8080/docs/api`.

## Getting Started

### Prerequisites
- [Docker](https://www.docker.com/) & [Docker Compose](https://docs.docker.com/compose/)
- [Composer](https://getcomposer.org/) (for installing dependencies initially)

### Installation & Setup

1. **Clone & Install Dependencies**
   ```bash
   git clone https://github.com/your-username/SentinelPay.git
   cd SentinelPay
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

2. **Start the Infrastructure**
   ```bash
   docker compose up -d --build
   ```
   *This starts Nginx, PHP-FPM, PostgreSQL, Redis, and RabbitMQ.*

3. **Migrate & Seed Data**
   ```bash
   docker compose exec app php artisan migrate:fresh --seed --force
   ```

4. **Access the Dashboard**
   Navigate to `http://localhost:8080/dashboard` in your browser to view the seeded merchant, API key, transfers, and webhook logs.

5. **Test the API**
   Use the seeded API key `sp_live_demo1234567890` against `http://localhost:8080/api/v1/...`.

## Running Tests & Audits
Run the Pest/PHPUnit test suite:
```bash
docker compose exec app php artisan test
```

Run the Ledger Audit tool to perform a financial integrity check:
```bash
php artisan audit:ledger
```

## Useful Links
- **API Reference**: `http://localhost:8080/docs/api`
- **Postman Collection**: Locate the JSON export in the `docs/` folder.
