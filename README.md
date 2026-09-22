# SentinelPay 2.0

> **High-Availability Distributed Payment API & React Dashboard**
> Built on Laravel 12 · Supabase PostgreSQL · React · Vite · Tailwind CSS

A production-grade payment platform demonstrating:
- **ACID-compliant** fund transfers with PostgreSQL
- **Pessimistic row-level locking** (`SELECT FOR UPDATE`) to eliminate race conditions
- **PostgreSQL-backed idempotency** with cache acceleration to prevent double-charging on retries
- **HMAC-SHA256 request signing** to prevent payload tampering
- **Immutable append-only ledger** enforced at application and database trigger levels
- **Synchronous queue processing** locally with decoupled asynchronous job architecture
- **Modern React + TypeScript** frontend foundation

---

## Target Architecture

```
React + Vite (Frontend)
       │
       │ HTTP / JSON (Axios via resources/js/lib/api.ts)
       ▼
 Laravel 12 API (Authoritative Backend)
       │
       │ PostgreSQL (TCP 5432 / 6543, SSL require)
       ▼
Supabase PostgreSQL (Accounts, Transactions, Ledgers)
```

> **Security Note**: React communicates solely with the Laravel API (`/api/v1/*`). Database connection credentials and Supabase service keys are never exposed to the client application.

### Core Data Model

| Table          | Key Columns                                                                 | Notes                                      |
|----------------|-----------------------------------------------------------------------------|--------------------------------------------|
| `accounts`     | `id` UUID, `user_id` UUID, `balance` DECIMAL(20,2), `currency`             | DB-level `CHECK (balance >= 0)`            |
| `transactions` | `id` UUID, `idempotency_key` UNIQUE, `sender_id`, `receiver_id`, `status`, `signature` | `CHECK (amount > 0)` |
| `ledgers`      | `id` BIGINT, `account_id`, `transaction_id`, `type` ENUM(debit/credit), `amount`, `balance_after` | **Append-only** — PG triggers prevent UPDATE/DELETE |

---

## Local Native Development Setup

### System Prerequisites
```
Local Development
├── PHP 8.2+ (with pdo_pgsql, bcmath, openssl, mbstring)
├── Composer
├── Node.js 18+ & npm
├── PostgreSQL or Supabase project credentials
└── Laravel 12 + React
```

### Installation Steps

1. **Clone repository and install dependencies:**
   ```powershell
   composer install
   npm install
   ```

2. **Environment Configuration:**
   ```powershell
   # Windows PowerShell
   Copy-Item .env.example .env
   php artisan key:generate
   ```

3. **Configure Database (Supabase PostgreSQL):**
   In your `.env`, specify your Supabase PostgreSQL connection details:
   ```ini
   DB_CONNECTION=pgsql
   DB_HOST=aws-0-xx.pooler.supabase.com
   DB_PORT=5432
   DB_DATABASE=postgres
   DB_USERNAME=postgres.your-project-id
   DB_PASSWORD=your-secure-supabase-password
   DB_SSLMODE=require

   # Local Cache and Session
   CACHE_STORE=file
   SESSION_DRIVER=file
   QUEUE_CONNECTION=sync
   ```

4. **Run Migrations:**
   ```powershell
   php artisan migrate
   ```

5. **Start Development Servers:**
   Terminal 1 (Backend API):
   ```powershell
   php artisan serve
   ```
   Terminal 2 (Vite Frontend):
   ```powershell
   npm run dev
   ```

6. **Access Application:**
   - Frontend UI: `http://localhost:8000`
   - API Health Check: `http://localhost:8000/api/v1/health`

---

## Security: HMAC-SHA256 Signing

Every mutating payment request (`POST /api/v1/transfers`) must include an `X-Signature` header:

```bash
BODY='{"sender_account_id":"...","receiver_account_id":"...","amount":"100.00","currency":"USD","idempotency_key":"unique-key-here"}'
SIG=$(echo -n "$BODY" | openssl dgst -sha256 -hmac "your-hmac-secret" | awk '{print $2}')

curl -X POST http://localhost:8000/api/v1/transfers \
  -H "Content-Type: application/json" \
  -H "X-Signature: $SIG" \
  -d "$BODY"
```

The middleware uses `hash_equals()` for timing-safe comparison to mitigate timing attacks.

---

## Concurrency & Idempotency Guarantees

### Pessimistic Row Locking
```php
// TransferService.php — core locking pattern
DB::transaction(function () use ($senderAccountId, $receiverAccountId) {
    // Sort UUIDs to prevent DEADLOCKS between concurrent bidirectional transfers
    $lockIds = [$senderAccountId, $receiverAccountId];
    sort($lockIds);

    // SELECT ... FOR UPDATE acquires exclusive row locks
    $accounts = Account::whereIn('id', $lockIds)
        ->lockForUpdate()
        ->get()
        ->keyBy('id');

    // ... balance deduction, credit, and immutable ledger entries
});
```

### PostgreSQL-Backed Idempotency
- Primary source of truth: PostgreSQL `UNIQUE (idempotency_key)` constraint on the `transactions` table.
- Cache layer (`CACHE_STORE=file` locally, Redis in multi-node clusters) serves as a fast-path read accelerator.
- Invariant: `same idempotency key → same transaction → never double-charge`.

---

## Testing

Tests run using PostgreSQL (as SQLite cannot simulate PostgreSQL `SELECT FOR UPDATE` locking and PL/pgSQL triggers).

```powershell
# Clear configurations
php artisan config:clear
php artisan cache:clear

# Run Unit tests
php artisan test tests/Unit

# Run Feature tests (requires isolated test DB e.g. sentinelpay_test)
php artisan test tests/Feature/ExampleTest.php
php artisan test
```

> **Note on Concurrency Testing**: `TransferTest.php` contains sequential loop tests verifying balance integrity and overdraft prevention across repeated attempts. True multi-process parallel concurrency testing with distinct OS threads is isolated in `tests/Concurrent/ConcurrencyTest.php` (using `proc_open`).

---

## Deferred Infrastructure (Future Phases)

Docker, Redis, and RabbitMQ configuration files remain preserved for upcoming infrastructure milestones:
- **Phase 4**: Redis idempotency clustering and RabbitMQ queue worker orchestration.
- **Phase 5**: Containerization with Podman / Docker Compose.

The preserved configuration files are located at:
- `docker-compose.yml`
- `docker/app/`
- `docker/nginx/`

---

## License

MIT © SentinelPay
